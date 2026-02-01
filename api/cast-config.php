<?php
/**
 * Cast Configuration API
 * Handles all config CRUD operations
 */

header('Content-Type: application/json');

// Use the dashboard's db.php for write access
require_once __DIR__ . '/../dashboard/includes/db.php';
require_once __DIR__ . '/../dashboard/includes/cast.php';

$action = $_REQUEST['action'] ?? '';

try {
    switch ($action) {
        
        // ============================
        // Save config category
        // ============================
        case 'save':
            $category = $_POST['category'] ?? '';
            if (!$category) {
                throw new Exception('Missing category');
            }
            
            $allowed = [
                'general' => ['deployment_mode', 'name_prefix', 'name_start'],
                'infra' => ['em_ip', 'em_instance_id', 'rsyslog_port', 'ssh_user', 'ssh_key_path'],
                'aws' => ['region', 'vpc_id', 'lure_ami_id', 'key_pair_name', 'iam_role']
            ];
            
            if (!isset($allowed[$category])) {
                throw new Exception('Invalid category');
            }
            
            foreach ($allowed[$category] as $key) {
                if (isset($_POST[$key])) {
                    cast_set_config($category, $key, trim($_POST[$key]));
                }
            }
            
            echo json_encode(['success' => true]);
            break;
        
        // ============================
        // Test AWS connection
        // ============================
        case 'test_aws':
            $region = cast_get_config('aws', 'region', 'us-east-2');
            $cmd = "aws sts get-caller-identity --region $region --output json 2>&1";
            $output = shell_exec($cmd);
            $data = json_decode($output, true);
            
            if (isset($data['Account'])) {
                echo json_encode(['success' => true, 'account' => $data['Account'], 'arn' => $data['Arn'] ?? '']);
            } else {
                throw new Exception('AWS CLI error: ' . ($output ?? 'Unknown'));
            }
            break;
        
        // ============================
        // Import from AWS
        // ============================
        case 'import':
            $type = $_POST['type'] ?? '';
            $region = cast_get_config('aws', 'region', 'us-east-2');
            $vpc_id = cast_get_config('aws', 'vpc_id', '');
            
            if (!$vpc_id && in_array($type, ['subnets', 'sgs'])) {
                throw new Exception('VPC ID not configured');
            }
            
            $count = 0;
            $db = get_db();
            
            switch ($type) {
                case 'subnets':
                    $cmd = "aws ec2 describe-subnets --region $region --filters Name=vpc-id,Values=$vpc_id --output json 2>&1";
                    $data = json_decode(shell_exec($cmd), true);
                    
                    foreach ($data['Subnets'] ?? [] as $s) {
                        $name = '';
                        foreach ($s['Tags'] ?? [] as $t) {
                            if ($t['Key'] === 'Name') { $name = $t['Value']; break; }
                        }
                        
                        // Guess type from name
                        $stype = 'public';
                        $nl = strtolower($name);
                        if (strpos($nl, 'mgt') !== false) $stype = 'mgt';
                        elseif (strpos($nl, 'bait') !== false) $stype = 'bait';
                        elseif (strpos($nl, 'em') !== false) $stype = 'em';
                        
                        $stmt = $db->prepare('INSERT OR IGNORE INTO cast_subnets (subnet_id, name, subnet_type, az, cidr) VALUES (?, ?, ?, ?, ?)');
                        if ($stmt->execute([$s['SubnetId'], $name, $stype, $s['AvailabilityZone'], $s['CidrBlock']])) {
                            if ($db->changes() > 0) $count++;
                        }
                    }
                    break;
                    
                case 'sgs':
                    $cmd = "aws ec2 describe-security-groups --region $region --filters Name=vpc-id,Values=$vpc_id --output json 2>&1";
                    $data = json_decode(shell_exec($cmd), true);
                    
                    foreach ($data['SecurityGroups'] ?? [] as $sg) {
                        $stype = 'bastion';
                        $nl = strtolower($sg['GroupName']);
                        if (strpos($nl, 'mgt') !== false) $stype = 'lure-mgt';
                        elseif (strpos($nl, 'bait') !== false) $stype = 'lure-bait';
                        elseif (strpos($nl, 'em') !== false) $stype = 'em';
                        elseif (strpos($nl, 'proxy') !== false) $stype = 'proxy';
                        
                        $stmt = $db->prepare('INSERT OR IGNORE INTO cast_security_groups (sg_id, name, sg_type, description) VALUES (?, ?, ?, ?)');
                        if ($stmt->execute([$sg['GroupId'], $sg['GroupName'], $stype, $sg['Description'] ?? ''])) {
                            if ($db->changes() > 0) $count++;
                        }
                    }
                    break;
                    
                case 'eips':
                    $cmd = "aws ec2 describe-addresses --region $region --output json 2>&1";
                    $data = json_decode(shell_exec($cmd), true);
                    
                    foreach ($data['Addresses'] ?? [] as $e) {
                        $etype = 'bait-pool';
                        $assigned = '';
                        foreach ($e['Tags'] ?? [] as $t) {
                            if ($t['Key'] === 'Name') {
                                $assigned = $t['Value'];
                                $nl = strtolower($t['Value']);
                                if (strpos($nl, 'bastion') !== false) $etype = 'bastion';
                                elseif (strpos($nl, 'proxy') !== false) $etype = 'proxy';
                                break;
                            }
                        }
                        
                        $stmt = $db->prepare('INSERT OR IGNORE INTO cast_eips (eip, allocation_id, eip_type, assigned_to) VALUES (?, ?, ?, ?)');
                        if ($stmt->execute([$e['PublicIp'], $e['AllocationId'] ?? '', $etype, $assigned])) {
                            if ($db->changes() > 0) $count++;
                        }
                    }
                    break;
                    
                default:
                    throw new Exception('Unknown import type');
            }
            
            echo json_encode(['success' => true, 'count' => $count]);
            break;
        
        // ============================
        // Get single resource
        // ============================
        case 'get':
            $type = $_GET['type'] ?? '';
            $id = $_GET['id'] ?? '';
            
            $tables = [
                'subnet' => 'cast_subnets',
                'sg' => 'cast_security_groups',
                'eip' => 'cast_eips',
                'pair' => 'cast_subnet_pairs',
                'instance_type' => 'cast_instance_types'
            ];
            
            if (!isset($tables[$type])) {
                throw new Exception('Invalid type');
            }
            
            $row = db_fetch_one("SELECT * FROM {$tables[$type]} WHERE id = :id", [':id' => $id]);
            
            if ($row) {
                echo json_encode(['success' => true, 'data' => $row]);
            } else {
                throw new Exception('Not found');
            }
            break;
        
        // ============================
        // Save resource (add or update)
        // ============================
        case 'save_resource':
            $type = $_POST['type'] ?? '';
            $id = $_POST['id'] ?? '';
            $db = get_db();
            
            switch ($type) {
                case 'subnet':
                    if ($id) {
                        $stmt = $db->prepare('UPDATE cast_subnets SET subnet_id=?, name=?, subnet_type=?, az=?, cidr=? WHERE id=?');
                        $stmt->execute([$_POST['subnet_id'], $_POST['name'], $_POST['subnet_type'], $_POST['az'], $_POST['cidr'], $id]);
                    } else {
                        $stmt = $db->prepare('INSERT INTO cast_subnets (subnet_id, name, subnet_type, az, cidr) VALUES (?, ?, ?, ?, ?)');
                        $stmt->execute([$_POST['subnet_id'], $_POST['name'], $_POST['subnet_type'], $_POST['az'], $_POST['cidr']]);
                    }
                    break;
                    
                case 'pair':
                    if ($id) {
                        $stmt = $db->prepare('UPDATE cast_subnet_pairs SET name=?, mgt_subnet_id=?, bait_subnet_id=?, az=? WHERE id=?');
                        $stmt->execute([$_POST['name'], $_POST['mgt_subnet_id'], $_POST['bait_subnet_id'], $_POST['az'], $id]);
                    } else {
                        $stmt = $db->prepare('INSERT INTO cast_subnet_pairs (name, mgt_subnet_id, bait_subnet_id, az) VALUES (?, ?, ?, ?)');
                        $stmt->execute([$_POST['name'], $_POST['mgt_subnet_id'], $_POST['bait_subnet_id'], $_POST['az']]);
                    }
                    break;
                    
                case 'sg':
                    if ($id) {
                        $stmt = $db->prepare('UPDATE cast_security_groups SET sg_id=?, name=?, sg_type=?, description=? WHERE id=?');
                        $stmt->execute([$_POST['sg_id'], $_POST['name'], $_POST['sg_type'], $_POST['description'], $id]);
                    } else {
                        $stmt = $db->prepare('INSERT INTO cast_security_groups (sg_id, name, sg_type, description) VALUES (?, ?, ?, ?)');
                        $stmt->execute([$_POST['sg_id'], $_POST['name'], $_POST['sg_type'], $_POST['description']]);
                    }
                    break;
                    
                case 'eip':
                    if ($id) {
                        $stmt = $db->prepare('UPDATE cast_eips SET eip=?, allocation_id=?, eip_type=? WHERE id=?');
                        $stmt->execute([$_POST['eip'], $_POST['allocation_id'], $_POST['eip_type'], $id]);
                    } else {
                        $stmt = $db->prepare('INSERT INTO cast_eips (eip, allocation_id, eip_type) VALUES (?, ?, ?)');
                        $stmt->execute([$_POST['eip'], $_POST['allocation_id'], $_POST['eip_type']]);
                    }
                    break;
                    
                case 'instance_type':
                    if ($id) {
                        $stmt = $db->prepare('UPDATE cast_instance_types SET instance_type=?, description=? WHERE id=?');
                        $stmt->execute([$_POST['instance_type'], $_POST['description'], $id]);
                    } else {
                        $stmt = $db->prepare('INSERT INTO cast_instance_types (instance_type, description) VALUES (?, ?)');
                        $stmt->execute([$_POST['instance_type'], $_POST['description']]);
                    }
                    break;
                    
                default:
                    throw new Exception('Invalid type');
            }
            
            echo json_encode(['success' => true]);
            break;
        
        // ============================
        // Delete resource
        // ============================
        case 'delete':
            $type = $_POST['type'] ?? '';
            $id = $_POST['id'] ?? '';
            
            $tables = [
                'subnet' => 'cast_subnets',
                'sg' => 'cast_security_groups',
                'eip' => 'cast_eips',
                'pair' => 'cast_subnet_pairs',
                'instance_type' => 'cast_instance_types'
            ];
            
            if (!isset($tables[$type])) {
                throw new Exception('Invalid type');
            }
            
            db_query("UPDATE {$tables[$type]} SET is_active = 0 WHERE id = :id", [':id' => $id]);
            echo json_encode(['success' => true]);
            break;
        
        // ============================
        // Set default instance type
        // ============================
        case 'set_default_instance':
            $id = $_POST['id'] ?? '';
            $db = get_db();
            $db->exec('UPDATE cast_instance_types SET is_default = 0');
            db_query('UPDATE cast_instance_types SET is_default = 1 WHERE id = :id', [':id' => $id]);
            echo json_encode(['success' => true]);
            break;
        
        default:
            throw new Exception('Unknown action');
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
