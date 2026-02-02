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
            $cmd = "aws sts get-caller-identity --region " . escapeshellarg($region) . " --output json 2>&1";
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
                throw new Exception('VPC ID not configured. Please set it in the AWS tab first.');
            }
            
            $count = 0;
            $db = get_db();
            
            switch ($type) {
                case 'subnets':
                    $cmd = "aws ec2 describe-subnets --region " . escapeshellarg($region) . 
                           " --filters Name=vpc-id,Values=" . escapeshellarg($vpc_id) . " --output json 2>&1";
                    $output = shell_exec($cmd);
                    $data = json_decode($output, true);
                    
                    if (!isset($data['Subnets'])) {
                        throw new Exception('AWS error: ' . ($output ?? 'No response'));
                    }
                    
                    foreach ($data['Subnets'] as $s) {
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
                        
                        // Check if already exists
                        $existing = db_fetch_one(
                            'SELECT id FROM cast_subnets WHERE subnet_id = :subnet_id',
                            [':subnet_id' => $s['SubnetId']]
                        );
                        
                        if (!$existing) {
                            db_query(
                                'INSERT INTO cast_subnets (subnet_id, name, subnet_type, az, cidr) VALUES (:subnet_id, :name, :subnet_type, :az, :cidr)',
                                [
                                    ':subnet_id' => $s['SubnetId'],
                                    ':name' => $name,
                                    ':subnet_type' => $stype,
                                    ':az' => $s['AvailabilityZone'],
                                    ':cidr' => $s['CidrBlock']
                                ]
                            );
                            $count++;
                        }
                    }
                    break;
                    
                case 'sgs':
                    $cmd = "aws ec2 describe-security-groups --region " . escapeshellarg($region) . 
                           " --filters Name=vpc-id,Values=" . escapeshellarg($vpc_id) . " --output json 2>&1";
                    $output = shell_exec($cmd);
                    $data = json_decode($output, true);
                    
                    if (!isset($data['SecurityGroups'])) {
                        throw new Exception('AWS error: ' . ($output ?? 'No response'));
                    }
                    
                    foreach ($data['SecurityGroups'] as $sg) {
                        $stype = 'bastion';
                        $nl = strtolower($sg['GroupName']);
                        if (strpos($nl, 'mgt') !== false) $stype = 'lure-mgt';
                        elseif (strpos($nl, 'bait') !== false) $stype = 'lure-bait';
                        elseif (strpos($nl, 'em') !== false) $stype = 'em';
                        elseif (strpos($nl, 'proxy') !== false) $stype = 'proxy';
                        
                        // Check if already exists
                        $existing = db_fetch_one(
                            'SELECT id FROM cast_security_groups WHERE sg_id = :sg_id',
                            [':sg_id' => $sg['GroupId']]
                        );
                        
                        if (!$existing) {
                            db_query(
                                'INSERT INTO cast_security_groups (sg_id, name, sg_type, description) VALUES (:sg_id, :name, :sg_type, :description)',
                                [
                                    ':sg_id' => $sg['GroupId'],
                                    ':name' => $sg['GroupName'],
                                    ':sg_type' => $stype,
                                    ':description' => $sg['Description'] ?? ''
                                ]
                            );
                            $count++;
                        }
                    }
                    break;
                    
                case 'eips':
                    $cmd = "aws ec2 describe-addresses --region " . escapeshellarg($region) . " --output json 2>&1";
                    $output = shell_exec($cmd);
                    $data = json_decode($output, true);
                    
                    if (!isset($data['Addresses'])) {
                        throw new Exception('AWS error: ' . ($output ?? 'No response'));
                    }
                    
                    foreach ($data['Addresses'] as $e) {
                        $etype = 'bait-pool';
                        $assigned = '';
                        foreach ($e['Tags'] ?? [] as $t) {
                            if ($t['Key'] === 'Name') {
                                $assigned = $t['Value'];
                                $nl = strtolower($t['Value']);
                                if (strpos($nl, 'bastion') !== false || strpos($nl, 'jump') !== false) $etype = 'bastion';
                                elseif (strpos($nl, 'proxy') !== false) $etype = 'proxy';
                                break;
                            }
                        }
                        
                        // Check if already exists
                        $existing = db_fetch_one(
                            'SELECT id FROM cast_eips WHERE allocation_id = :allocation_id',
                            [':allocation_id' => $e['AllocationId'] ?? '']
                        );
                        
                        if (!$existing && !empty($e['PublicIp'])) {
                            db_query(
                                'INSERT INTO cast_eips (eip, allocation_id, eip_type, assigned_to) VALUES (:eip, :allocation_id, :eip_type, :assigned_to)',
                                [
                                    ':eip' => $e['PublicIp'],
                                    ':allocation_id' => $e['AllocationId'] ?? '',
                                    ':eip_type' => $etype,
                                    ':assigned_to' => $assigned
                                ]
                            );
                            $count++;
                        }
                    }
                    break;
                    
                default:
                    throw new Exception('Unknown import type: ' . $type);
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
            
            switch ($type) {
                case 'subnet':
                    if ($id) {
                        db_query(
                            'UPDATE cast_subnets SET subnet_id=:subnet_id, name=:name, subnet_type=:subnet_type, az=:az, cidr=:cidr WHERE id=:id',
                            [
                                ':subnet_id' => $_POST['subnet_id'],
                                ':name' => $_POST['name'],
                                ':subnet_type' => $_POST['subnet_type'],
                                ':az' => $_POST['az'],
                                ':cidr' => $_POST['cidr'],
                                ':id' => $id
                            ]
                        );
                    } else {
                        db_query(
                            'INSERT INTO cast_subnets (subnet_id, name, subnet_type, az, cidr) VALUES (:subnet_id, :name, :subnet_type, :az, :cidr)',
                            [
                                ':subnet_id' => $_POST['subnet_id'],
                                ':name' => $_POST['name'],
                                ':subnet_type' => $_POST['subnet_type'],
                                ':az' => $_POST['az'],
                                ':cidr' => $_POST['cidr']
                            ]
                        );
                    }
                    break;
                    
                case 'pair':
                    if ($id) {
                        db_query(
                            'UPDATE cast_subnet_pairs SET name=:name, mgt_subnet_id=:mgt_subnet_id, bait_subnet_id=:bait_subnet_id, az=:az WHERE id=:id',
                            [
                                ':name' => $_POST['name'],
                                ':mgt_subnet_id' => $_POST['mgt_subnet_id'],
                                ':bait_subnet_id' => $_POST['bait_subnet_id'],
                                ':az' => $_POST['az'],
                                ':id' => $id
                            ]
                        );
                    } else {
                        db_query(
                            'INSERT INTO cast_subnet_pairs (name, mgt_subnet_id, bait_subnet_id, az) VALUES (:name, :mgt_subnet_id, :bait_subnet_id, :az)',
                            [
                                ':name' => $_POST['name'],
                                ':mgt_subnet_id' => $_POST['mgt_subnet_id'],
                                ':bait_subnet_id' => $_POST['bait_subnet_id'],
                                ':az' => $_POST['az']
                            ]
                        );
                    }
                    break;
                    
                case 'sg':
                    if ($id) {
                        db_query(
                            'UPDATE cast_security_groups SET sg_id=:sg_id, name=:name, sg_type=:sg_type, description=:description WHERE id=:id',
                            [
                                ':sg_id' => $_POST['sg_id'],
                                ':name' => $_POST['name'],
                                ':sg_type' => $_POST['sg_type'],
                                ':description' => $_POST['description'],
                                ':id' => $id
                            ]
                        );
                    } else {
                        db_query(
                            'INSERT INTO cast_security_groups (sg_id, name, sg_type, description) VALUES (:sg_id, :name, :sg_type, :description)',
                            [
                                ':sg_id' => $_POST['sg_id'],
                                ':name' => $_POST['name'],
                                ':sg_type' => $_POST['sg_type'],
                                ':description' => $_POST['description']
                            ]
                        );
                    }
                    break;
                    
                case 'eip':
                    if ($id) {
                        db_query(
                            'UPDATE cast_eips SET eip=:eip, allocation_id=:allocation_id, eip_type=:eip_type WHERE id=:id',
                            [
                                ':eip' => $_POST['eip'],
                                ':allocation_id' => $_POST['allocation_id'],
                                ':eip_type' => $_POST['eip_type'],
                                ':id' => $id
                            ]
                        );
                    } else {
                        db_query(
                            'INSERT INTO cast_eips (eip, allocation_id, eip_type) VALUES (:eip, :allocation_id, :eip_type)',
                            [
                                ':eip' => $_POST['eip'],
                                ':allocation_id' => $_POST['allocation_id'],
                                ':eip_type' => $_POST['eip_type']
                            ]
                        );
                    }
                    break;
                    
                case 'instance_type':
                    if ($id) {
                        db_query(
                            'UPDATE cast_instance_types SET instance_type=:instance_type, description=:description WHERE id=:id',
                            [
                                ':instance_type' => $_POST['instance_type'],
                                ':description' => $_POST['description'],
                                ':id' => $id
                            ]
                        );
                    } else {
                        db_query(
                            'INSERT INTO cast_instance_types (instance_type, description) VALUES (:instance_type, :description)',
                            [
                                ':instance_type' => $_POST['instance_type'],
                                ':description' => $_POST['description']
                            ]
                        );
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
            
            // Special handling for EIPs - release from AWS first
            if ($type === 'eip') {
                $eip = db_fetch_one('SELECT * FROM cast_eips WHERE id = :id', [':id' => $id]);
                
                if (!$eip) {
                    throw new Exception('EIP not found');
                }
                
                // Check if EIP is assigned to an instance
                if (!empty($eip['assigned_to'])) {
                    throw new Exception('Cannot delete EIP - it is currently assigned to ' . $eip['assigned_to'] . '. Unassign it first.');
                }
                
                // Release the EIP from AWS
                $region = cast_get_config('aws', 'region', 'us-east-2');
                $allocation_id = $eip['allocation_id'];
                
                if (!empty($allocation_id)) {
                    $cmd = "aws ec2 release-address --allocation-id " . escapeshellarg($allocation_id) . 
                           " --region " . escapeshellarg($region) . " 2>&1";
                    $output = shell_exec($cmd);
                    
                    // Check for errors (successful release returns empty string)
                    if (!empty($output) && stripos($output, 'error') !== false) {
                        throw new Exception('AWS error releasing EIP: ' . $output);
                    }
                }
                
                // Hard delete from database (not soft delete)
                db_query("DELETE FROM cast_eips WHERE id = :id", [':id' => $id]);
                echo json_encode(['success' => true, 'message' => 'EIP released from AWS and removed']);
                
            } else {
                // Soft delete for other resource types
                db_query("UPDATE {$tables[$type]} SET is_active = 0 WHERE id = :id", [':id' => $id]);
                echo json_encode(['success' => true]);
            }
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
            throw new Exception('Unknown action: ' . $action);
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
