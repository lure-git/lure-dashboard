<?php
require_once __DIR__ . '/../dashboard/includes/db.php';
require_once __DIR__ . '/../dashboard/includes/auth.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die(json_encode(['success' => false, 'error' => 'Method not allowed']));
}

$hostname = $_POST['hostname'] ?? '';
$since = $_POST['since'] ?? '';

if (empty($hostname)) {
    http_response_code(400);
    die(json_encode(['success' => false, 'error' => 'Hostname required']));
}

$hostname = preg_replace('/[^a-zA-Z0-9_-]/', '', $hostname);

// Verify sensor exists
$row = db_fetch_one("SELECT lure_id FROM lure_health WHERE hostname = :hostname", [':hostname' => $hostname]);
if (!$row) {
    http_response_code(404);
    die(json_encode(['success' => false, 'error' => 'Unknown sensor: ' . $hostname]));
}

// Check for existing pending command
$pending = db_fetch_one("SELECT id FROM lure_commands WHERE target_host = :hostname AND command = 'send_diag_logs' AND dispatched = 0", [':hostname' => $hostname]);
if ($pending) {
    die(json_encode(['success' => false, 'error' => 'Diagnostic request already pending for ' . $hostname]));
}

// Build params
$params = '{}';
if (!empty($since)) {
    $since = preg_replace('/[^0-9T:-]/', '', $since);
    $params = json_encode(['since' => $since]);
}

// Insert command
db_query("INSERT INTO lure_commands (target_host, command, params) VALUES (:hostname, 'send_diag_logs', :params)", [':hostname' => $hostname, ':params' => $params]);
audit_log('request_diag_logs', 'lure', $hostname, 'Requested diagnostic logs' . ($since ? " since $since" : ' (all)'));

echo json_encode([
    'success' => true,
    'message' => 'Diagnostic log request queued for ' . $hostname,
    'note' => 'Sensor will send logs on next health check (up to 5 minutes)'
]);
