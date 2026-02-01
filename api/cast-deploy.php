<?php
/**
 * Cast Deploy API - Launch and configure a new lure
 */
header('Content-Type: application/json');

require_once __DIR__ . '/../dashboard/includes/db.php';
require_once __DIR__ . '/../dashboard/includes/cast.php';
require_once __DIR__ . '/../dashboard/includes/auth.php';

if (!is_logged_in()) {
    http_response_code(401);
    die(json_encode(['success' => false, 'error' => 'Unauthorized']));
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die(json_encode(['success' => false, 'error' => 'Method not allowed']));
}

$hostname = trim($_POST['hostname'] ?? '');
$instance_type = trim($_POST['instance_type'] ?? '');
$eip_alloc_id = trim($_POST['eip_allocation_id'] ?? '');
$mgt_subnet = trim($_POST['mgt_subnet'] ?? '');
$bait_subnet = trim($_POST['bait_subnet'] ?? '');

if (empty($hostname) || empty($instance_type) || empty($eip_alloc_id) || empty($mgt_subnet) || empty($bait_subnet)) {
    die(json_encode(['success' => false, 'error' => 'Missing required fields']));
}

$region = cast_get_config('aws', 'region', 'us-east-2');
$ami_id = cast_get_config('aws', 'lure_ami_id', '');
$key_name = cast_get_config('aws', 'key_pair_name', '');
$iam_role = cast_get_config('aws', 'iam_role', '');

if (empty($ami_id) || empty($key_name)) {
    die(json_encode(['success' => false, 'error' => 'AMI or Key Pair not configured']));
}

$mgt_sg = db_fetch_one("SELECT sg_id FROM cast_security_groups WHERE sg_type='lure-mgt' AND is_active=1");
$bait_sg = db_fetch_one("SELECT sg_id FROM cast_security_groups WHERE sg_type='lure-bait' AND is_active=1");

if (!$mgt_sg || !$bait_sg) {
    die(json_encode(['success' => false, 'error' => 'Security groups not configured']));
}

try {
    $mgt_sg_id = $mgt_sg['sg_id'];
    $bait_sg_id = $bait_sg['sg_id'];
    
    $net_ifaces = [
        ['DeviceIndex' => 0, 'SubnetId' => $mgt_subnet, 'Groups' => [$mgt_sg_id], 'DeleteOnTermination' => true],
        ['DeviceIndex' => 1, 'SubnetId' => $bait_subnet, 'Groups' => [$bait_sg_id], 'DeleteOnTermination' => true]
    ];
    $net_json = json_encode($net_ifaces);
    
    $iam_arg = $iam_role ? "--iam-instance-profile Name=$iam_role" : '';
    
    $cmd = "aws ec2 run-instances --region $region " .
           "--image-id $ami_id " .
           "--instance-type $instance_type " .
           "--key-name \"$key_name\" " .
           "--network-interfaces '$net_json' " .
           "--tag-specifications 'ResourceType=instance,Tags=[{Key=Name,Value=$hostname},{Key=Project,Value=LURE}]' " .
           "$iam_arg " .
           "--query 'Instances[0].InstanceId' --output text 2>&1";
    
    $instance_id = trim(shell_exec($cmd));
    
    if (empty($instance_id) || strpos($instance_id, 'i-') !== 0) {
        throw new Exception("Launch failed: $instance_id");
    }
    
    // Wait for running
    shell_exec("aws ec2 wait instance-running --region $region --instance-ids $instance_id 2>&1");
    
    // Wait a bit more for ENIs to be fully attached
    sleep(5);
    
    // Get BAIT ENI with retry
    $bait_eni = null;
    for ($i = 0; $i < 5; $i++) {
        $eni_cmd = "aws ec2 describe-instances --region $region --instance-ids $instance_id " .
                   "--query \"Reservations[0].Instances[0].NetworkInterfaces[?Attachment.DeviceIndex==\\`1\\`].NetworkInterfaceId\" " .
                   "--output text 2>&1";
        $bait_eni = trim(shell_exec($eni_cmd));
        
        if (!empty($bait_eni) && strpos($bait_eni, 'eni-') === 0) {
            break;
        }
        sleep(3);
    }
    
    if (empty($bait_eni) || strpos($bait_eni, 'eni-') !== 0) {
        throw new Exception("Failed to get BAIT ENI after retries");
    }
    
    // Attach EIP to BAIT
    shell_exec("aws ec2 associate-address --region $region --allocation-id $eip_alloc_id --network-interface-id $bait_eni 2>&1");
    
    // Get IPs
    $mgt_ip = trim(shell_exec("aws ec2 describe-instances --region $region --instance-ids $instance_id " .
              "--query \"Reservations[0].Instances[0].NetworkInterfaces[?Attachment.DeviceIndex==\\`0\\`].PrivateIpAddress\" --output text 2>&1"));
    
    $bait_eip = trim(shell_exec("aws ec2 describe-addresses --region $region --allocation-ids $eip_alloc_id " .
                "--query \"Addresses[0].PublicIp\" --output text 2>&1"));
    
    // Update database
    db_query("UPDATE cast_eips SET assigned_to = :hostname WHERE allocation_id = :alloc",
             [':hostname' => $hostname, ':alloc' => $eip_alloc_id]);
    
    db_query("INSERT INTO lure_health (lure_id, hostname, ip_address, status, last_check, ssh_ok, rsyslog_ok, nftables_ok, disk_percent, memory_percent, uptime, last_log_received, error_message, load) 
              VALUES (:id, :hostname, :ip, 'provisioning', datetime('now'), 0, 0, 0, NULL, NULL, NULL, NULL, NULL, NULL)",
             [':id' => $hostname, ':hostname' => $hostname, ':ip' => $mgt_ip]);
    
    audit_log('cast_deploy', 'lure', $hostname, "Deployed $hostname ($instance_id) - MGT: $mgt_ip, BAIT: $bait_eip");
    
    // Trigger post-deploy config (async)
    $post_deploy = '/usr/local/share/lure/cast/lure-post-deploy.sh';
    $configuring = false;
    
    if (file_exists($post_deploy) && is_executable($post_deploy)) {
	    exec("nohup sudo -u lure $post_deploy " . escapeshellarg($mgt_ip) . " " . escapeshellarg($hostname) .
     " >> /var/log/lures/post-deploy.log 2>&1 &");
    $configuring = true;
}

    echo json_encode([
        'success' => true,
        'instance_id' => $instance_id,
        'hostname' => $hostname,
        'mgt_ip' => $mgt_ip,
        'bait_eip' => $bait_eip,
        'configuring' => $configuring
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
