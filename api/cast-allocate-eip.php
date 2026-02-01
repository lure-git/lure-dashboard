<?php
/**
 * Cast Allocate EIP API - Allocate a new Elastic IP for the BAIT pool
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

$region = cast_get_config('aws', 'region', 'us-east-2');

try {
    // Allocate new EIP
    $cmd = "aws ec2 allocate-address --region $region --domain vpc " .
           "--tag-specifications 'ResourceType=elastic-ip,Tags=[{Key=Name,Value=LURE-Pool},{Key=Project,Value=LURE}]' " .
           "--output json 2>&1";
    
    $output = shell_exec($cmd);
    $result = json_decode($output, true);
    
    if (empty($result['PublicIp']) || empty($result['AllocationId'])) {
        throw new Exception("Failed to allocate EIP: " . ($output ?? 'Unknown error'));
    }
    
    $eip = $result['PublicIp'];
    $allocation_id = $result['AllocationId'];
    
    // Save to database
    db_query(
        "INSERT INTO cast_eips (eip, allocation_id, eip_type, assigned_to) VALUES (:eip, :alloc, 'bait-pool', NULL)",
        [':eip' => $eip, ':alloc' => $allocation_id]
    );
    
    audit_log('cast_allocate_eip', 'eip', $allocation_id, "Allocated new EIP: $eip");
    
    echo json_encode([
        'success' => true,
        'eip' => $eip,
        'allocation_id' => $allocation_id
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
