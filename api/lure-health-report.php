<?php
/**
 * Lure Health Report API
 * Receives health status from lures via mTLS
 * 
 * Location: /var/www/lure/api/lure-health-report.php
 */

header('Content-Type: application/json');

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die(json_encode(['success' => false, 'error' => 'Method not allowed']));
}

// Verify client certificate was provided and validated by nginx
$client_verify = $_SERVER['SSL_CLIENT_VERIFY'] ?? '';
$client_dn = $_SERVER['SSL_CLIENT_S_DN'] ?? '';

if ($client_verify !== 'SUCCESS') {
    http_response_code(403);
    die(json_encode(['success' => false, 'error' => 'Client certificate required']));
}

// Extract CN from DN (format: CN=hostname,O=LURE)
$client_cn = '';
if (preg_match('/CN=([^,\/]+)/', $client_dn, $matches)) {
    $client_cn = $matches[1];
}

if (empty($client_cn)) {
    http_response_code(403);
    die(json_encode(['success' => false, 'error' => 'Invalid client certificate - no CN found']));
}

// Get JSON payload
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data) {
    http_response_code(400);
    die(json_encode(['success' => false, 'error' => 'Invalid JSON payload']));
}

// Validate required fields
$required = ['hostname', 'disk_percent', 'memory_percent', 'load', 'uptime', 'rsyslog_ok', 'nftables_ok', 'ssh_ok'];
foreach ($required as $field) {
    if (!isset($data[$field])) {
        http_response_code(400);
        die(json_encode(['success' => false, 'error' => "Missing field: $field"]));
    }
}

// Sanitize inputs
$hostname = preg_replace('/[^a-zA-Z0-9_-]/', '', $data['hostname']);
$disk_percent = intval($data['disk_percent']);
$memory_percent = intval($data['memory_percent']);
$load = preg_replace('/[^0-9.]/', '', $data['load']);
$uptime = intval($data['uptime']);
$rsyslog_ok = $data['rsyslog_ok'] ? 1 : 0;
$nftables_ok = $data['nftables_ok'] ? 1 : 0;
$ssh_ok = $data['ssh_ok'] ? 1 : 0;
$last_apt_upgrade = isset($data['last_apt_upgrade']) ? trim($data['last_apt_upgrade']) : null;

// Verify hostname matches certificate CN
if ($hostname !== $client_cn) {
    http_response_code(403);
    die(json_encode(['success' => false, 'error' => "Hostname mismatch: $hostname vs cert CN $client_cn"]));
}

// Determine status based on health metrics
$status = 'online';
$error_message = null;

// Check for problems
$problems = [];
if ($disk_percent > 90) {
    $problems[] = "Disk usage critical: {$disk_percent}%";
}
if ($memory_percent > 90) {
    $problems[] = "Memory usage critical: {$memory_percent}%";
}
if (!$rsyslog_ok) {
    $problems[] = "rsyslog not running";
}
if (!$nftables_ok) {
    $problems[] = "nftables not running";
}

if (count($problems) > 0) {
    $status = 'degraded';
    $error_message = implode('; ', $problems);
}

// Format uptime as human readable
$uptime_days = floor($uptime / 86400);
$uptime_hours = floor(($uptime % 86400) / 3600);
$uptime_formatted = "{$uptime_days}d {$uptime_hours}h";

// Database connection
$db_path = '/var/log/lures/lure_logs.db';
try {
    $db = new PDO("sqlite:$db_path");
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    http_response_code(500);
    die(json_encode(['success' => false, 'error' => 'Database connection failed']));
}

// Check if lure exists
$stmt = $db->prepare("SELECT lure_id FROM lure_health WHERE hostname = :hostname");
$stmt->execute([':hostname' => $hostname]);
$existing = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$existing) {
    http_response_code(404);
    die(json_encode(['success' => false, 'error' => 'Unknown lure: ' . $hostname]));
}

// Update health record
$sql = "UPDATE lure_health SET 
            status = :status,
            last_check = datetime('now'),
            disk_percent = :disk_percent,
            memory_percent = :memory_percent,
            load = :load,
            uptime = :uptime,
            rsyslog_ok = :rsyslog_ok,
            nftables_ok = :nftables_ok,
            ssh_ok = :ssh_ok,
            last_apt_upgrade = :last_apt_upgrade,
            error_message = :error_message
        WHERE hostname = :hostname";

$stmt = $db->prepare($sql);
$result = $stmt->execute([
    ':status' => $status,
    ':disk_percent' => $disk_percent,
    ':memory_percent' => $memory_percent,
    ':load' => $load,
    ':uptime' => $uptime_formatted,
    ':rsyslog_ok' => $rsyslog_ok,
    ':nftables_ok' => $nftables_ok,
    ':ssh_ok' => $ssh_ok,
    ':last_apt_upgrade' => $last_apt_upgrade,
    ':error_message' => $error_message,
    ':hostname' => $hostname
]);

if (!$result) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to update database']);
    exit;
}

// Build response
$response = [
    'success' => true,
    'message' => 'Health report received',
    'hostname' => $hostname,
    'status' => $status
];

// Check for pending commands for this sensor
$cmd_stmt = $db->prepare("
    SELECT id, command, params
    FROM lure_commands
    WHERE target_host = :hostname AND dispatched = 0
    ORDER BY created_at ASC
    LIMIT 1
");
$cmd_stmt->execute([':hostname' => $hostname]);
$pending = $cmd_stmt->fetch(PDO::FETCH_ASSOC);

if ($pending) {
    $response['command'] = $pending['command'];
    $response['params'] = json_decode($pending['params'], true) ?: new \stdClass();

    // Mark as dispatched
    $upd = $db->prepare("
        UPDATE lure_commands
        SET dispatched = 1, dispatched_at = datetime('now')
        WHERE id = :id
    ");
    $upd->execute([':id' => $pending['id']]);
}

echo json_encode($response);
