<?php
/**
 * Cast Terminate API - Terminate a lure instance
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
if (empty($hostname)) {
    die(json_encode(['success' => false, 'error' => 'Hostname required']));
}

$region = cast_get_config('aws', 'region', 'us-east-2');

try {
    // Find instance by hostname tag
    $cmd = "aws ec2 describe-instances --region $region " .
           "--filters \"Name=tag:Name,Values=$hostname\" \"Name=instance-state-name,Values=running,stopped,pending\" " .
           "--query \"Reservations[0].Instances[0].[InstanceId,NetworkInterfaces[?Attachment.DeviceIndex==\\`1\\`].Association.AllocationId|[0]]\" " .
           "--output json 2>&1";
    
    $output = shell_exec($cmd);
    $result = json_decode($output, true);
    
    if (empty($result) || empty($result[0]) || $result[0] === null) {
        throw new Exception("Instance not found for: $hostname");
    }
    
    $instance_id = $result[0];
    $eip_alloc_id = $result[1] ?? null;
    
    // Disassociate EIP if attached
    if ($eip_alloc_id) {
        $assoc_cmd = "aws ec2 describe-addresses --region $region --allocation-ids $eip_alloc_id " .
                     "--query \"Addresses[0].AssociationId\" --output text 2>&1";
        $assoc_id = trim(shell_exec($assoc_cmd));
        
        if ($assoc_id && $assoc_id !== 'None') {
            shell_exec("aws ec2 disassociate-address --region $region --association-id $assoc_id 2>&1");
        }
        
        db_query("UPDATE cast_eips SET assigned_to = NULL WHERE allocation_id = :alloc",
                 [':alloc' => $eip_alloc_id]);
    }
    
    // Terminate instance
    shell_exec("aws ec2 terminate-instances --region $region --instance-ids $instance_id 2>&1");
    
    // Update lure_health
    db_query("UPDATE lure_health SET status = 'terminated', last_check = datetime('now') WHERE hostname = :hostname",
             [':hostname' => $hostname]);
    
    audit_log('cast_terminate', 'lure', $hostname, "Terminated $hostname ($instance_id)");
    
    echo json_encode([
        'success' => true,
        'hostname' => $hostname,
        'instance_id' => $instance_id,
        'eip_released' => $eip_alloc_id ? true : false
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
