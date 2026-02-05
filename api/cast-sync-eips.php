<?php
/**
 * Cast Sync EIPs API - Reconcile cast_eips table with actual AWS state
 * 
 * Queries AWS ec2 describe-addresses and syncs the local cast_eips table:
 * - Adds EIPs that exist in AWS but not in DB
 * - Removes EIPs from DB that no longer exist in AWS
 * - Updates assigned_to status based on actual AWS associations
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
    // 1. Get all EIPs from AWS
    $cmd = "aws ec2 describe-addresses --region " . escapeshellarg($region) .
           " --output json 2>&1";
    $output = shell_exec($cmd);
    $data = json_decode($output, true);

    if (!isset($data['Addresses'])) {
        throw new Exception('AWS error: ' . ($output ?? 'No response'));
    }

    $aws_eips = [];
    foreach ($data['Addresses'] as $addr) {
        $alloc_id = $addr['AllocationId'] ?? '';
        if (empty($alloc_id)) continue;

        // Determine type from EIP tags first
        $eip_type = 'bait-pool';
        $tag_name = '';
        foreach ($addr['Tags'] ?? [] as $t) {
            if ($t['Key'] === 'Name') {
                $tag_name = $t['Value'];
                $nl = strtolower($t['Value']);
                if (strpos($nl, 'bastion') !== false || strpos($nl, 'jump') !== false) $eip_type = 'bastion';
                elseif (strpos($nl, 'proxy') !== false) $eip_type = 'proxy';
                break;
            }
        }

        // Check if EIP is associated to an instance
        $assigned_to = null;
        $instance_id = $addr['InstanceId'] ?? null;
        
        // If no direct InstanceId, check via NetworkInterfaceId
        if (empty($instance_id) && !empty($addr['NetworkInterfaceId'])) {
            $eni_cmd = "aws ec2 describe-network-interfaces --region " . escapeshellarg($region) .
                       " --network-interface-ids " . escapeshellarg($addr['NetworkInterfaceId']) .
                       " --query \"NetworkInterfaces[0].Attachment.InstanceId\" --output text 2>&1";
            $eni_instance = trim(shell_exec($eni_cmd));
            if (!empty($eni_instance) && $eni_instance !== 'None') {
                $instance_id = $eni_instance;
            }
        }
        
        if (!empty($instance_id)) {
            // Look up hostname from the instance Name tag
            $inst_cmd = "aws ec2 describe-instances --region " . escapeshellarg($region) .
                        " --instance-ids " . escapeshellarg($instance_id) .
                        " --query \"Reservations[0].Instances[0].Tags[?Key=='Name'].Value|[0]\" --output text 2>&1";
            $inst_name = trim(shell_exec($inst_cmd));
            if (!empty($inst_name) && $inst_name !== 'None') {
                $assigned_to = $inst_name;
                
                // If EIP tag didn't determine type, check instance name
                if ($eip_type === 'bait-pool') {
                    $inl = strtolower($inst_name);
                    if (strpos($inl, 'bastion') !== false || strpos($inl, 'jump') !== false) $eip_type = 'bastion';
                    elseif (strpos($inl, 'proxy') !== false) $eip_type = 'proxy';
                    elseif (strpos($inl, 'em') !== false) $eip_type = 'em';
                }
            }
        }

        $aws_eips[$alloc_id] = [
            'eip' => $addr['PublicIp'],
            'allocation_id' => $alloc_id,
            'eip_type' => $eip_type,
            'assigned_to' => $assigned_to
        ];
    }

    // 2. Get all EIPs from DB
    $db_rows = db_fetch_all('SELECT * FROM cast_eips WHERE is_active = 1');
    $db_eips = [];
    foreach ($db_rows as $row) {
        $db_eips[$row['allocation_id']] = $row;
    }

    $added = 0;
    $updated = 0;
    $removed = 0;
    $details = [];

    // 3. Add/update: EIPs in AWS
    foreach ($aws_eips as $alloc_id => $aws) {
        if (isset($db_eips[$alloc_id])) {
            // Exists in DB — check if assigned_to or eip_type needs updating
            $db = $db_eips[$alloc_id];
            $db_assigned = $db['assigned_to'] ?: null;
            $aws_assigned = $aws['assigned_to'];
            $db_type = $db['eip_type'];
            $aws_type = $aws['eip_type'];

            if ($db_assigned !== $aws_assigned || $db_type !== $aws_type) {
                db_query(
                    'UPDATE cast_eips SET assigned_to = :assigned, eip_type = :type WHERE allocation_id = :alloc',
                    [':assigned' => $aws_assigned, ':type' => $aws_type, ':alloc' => $alloc_id]
                );
                $updated++;
                $changes = [];
                if ($db_assigned !== $aws_assigned) $changes[] = "assigned_to '{$db_assigned}' → '{$aws_assigned}'";
                if ($db_type !== $aws_type) $changes[] = "type '{$db_type}' → '{$aws_type}'";
                $details[] = "Updated {$aws['eip']}: " . implode(', ', $changes);
            }
        } else {
            // New in AWS, not in DB — add it
            db_query(
                'INSERT INTO cast_eips (eip, allocation_id, eip_type, assigned_to) VALUES (:eip, :alloc, :type, :assigned)',
                [
                    ':eip' => $aws['eip'],
                    ':alloc' => $alloc_id,
                    ':type' => $aws['eip_type'],
                    ':assigned' => $aws['assigned_to']
                ]
            );
            $added++;
            $details[] = "Added {$aws['eip']} ({$alloc_id})";
        }
    }

    // 4. Remove: EIPs in DB but not in AWS (stale records)
    foreach ($db_eips as $alloc_id => $db) {
        if (!isset($aws_eips[$alloc_id])) {
            db_query(
                'DELETE FROM cast_eips WHERE allocation_id = :alloc',
                [':alloc' => $alloc_id]
            );
            $removed++;
            $details[] = "Removed stale {$db['eip']} ({$alloc_id})";
        }
    }

    // 5. Count available after sync
    $available = db_fetch_all(
        "SELECT COUNT(*) as cnt FROM cast_eips WHERE is_active = 1 AND eip_type = 'bait-pool' AND (assigned_to IS NULL OR assigned_to = '')"
    );
    $available_count = $available[0]['cnt'] ?? 0;

    audit_log('cast_sync_eips', 'eip', null, "Synced EIPs: +{$added} added, ~{$updated} updated, -{$removed} removed");

    echo json_encode([
        'success' => true,
        'added' => $added,
        'updated' => $updated,
        'removed' => $removed,
        'available_count' => $available_count,
        'details' => $details
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
