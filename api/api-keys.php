<?php
require_once '/var/www/lure/dashboard/includes/auth.php';
require_admin();

header('Content-Type: application/json');

$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {
    case 'create':
        $name = trim($_POST['name'] ?? '');
        $name = preg_replace('/[^a-zA-Z0-9_-]/', '', $name);
        $notes = trim($_POST['notes'] ?? '');
        
        if (empty($name) || strlen($name) < 3) {
            echo json_encode(['error' => 'Name must be at least 3 characters']);
            exit;
        }
        
        $api_key = bin2hex(random_bytes(32));
        $user = current_user();
        
        try {
            db_query(
                "INSERT INTO api_keys (name, api_key, created_by, notes) VALUES (:name, :key, :user, :notes)",
                [':name' => $name, ':key' => $api_key, ':user' => $user['username'], ':notes' => $notes]
            );
            exec('sudo /usr/local/share/lure/cert-mgt/refresh-api-keys.sh 2>&1');
            audit_log('apikey_create', 'api_key', $name, null);
            echo json_encode(['success' => true, 'name' => $name, 'api_key' => $api_key]);
        } catch (Exception $e) {
            echo json_encode(['error' => 'Failed: ' . $e->getMessage()]);
        }
        break;
        
    case 'revoke':
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            echo json_encode(['error' => 'Invalid key ID']);
            exit;
        }
        $key = db_fetch_one("SELECT name FROM api_keys WHERE id = :id AND is_active = 1", [':id' => $id]);
        if (!$key) {
            echo json_encode(['error' => 'Key not found or already revoked']);
            exit;
        }
        db_query("UPDATE api_keys SET is_active = 0 WHERE id = :id", [':id' => $id]);
        exec('sudo /usr/local/share/lure/cert-mgt/refresh-api-keys.sh 2>&1');
        audit_log('apikey_revoke', 'api_key', $key['name'], null);
        echo json_encode(['success' => true, 'message' => "Key revoked"]);
        break;
        
    case 'list':
        $keys = db_fetch_all("SELECT id, name, created_at, last_used_at, is_active, created_by, notes FROM api_keys ORDER BY id DESC");
        echo json_encode($keys);
        break;
        
    default:
        echo json_encode(['error' => 'Invalid action']);
}
