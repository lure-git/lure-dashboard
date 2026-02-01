<?php
/**
 * Cast Reboot API - Reboot a lure instance
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
           "--filters 'Name=tag:Name,Values=$hostname' 'Name=instance-state-name,Values=running' " .
           "--query 'Reservations[0].Instances[0].InstanceId' --output text 2>&1";
    
    $instance_id = trim(shell_exec($cmd));
    
    if (empty($instance_id) || $instance_id === 'None') {
        throw new Exception("Running instance not found for: $hostname");
    }
    
    // Reboot
    $reboot_cmd = "aws ec2 reboot-instances --region $region --instance-ids $instance_id 2>&1";
    $output = shell_exec($reboot_cmd);
    
    // Update status
    db_query("UPDATE lure_health SET status = 'rebooting', last_check = datetime('now') WHERE hostname = :hostname",
             [':hostname' => $hostname]);
    
    audit_log('cast_reboot', 'lure', $hostname, "Rebooted $hostname ($instance_id)");
    
    echo json_encode([
        'success' => true,
        'hostname' => $hostname,
        'instance_id' => $instance_id,
        'message' => 'Reboot initiated'
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
