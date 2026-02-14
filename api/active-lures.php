<?php
require_once 'config.php';

$db = getDB();

// Count distinct lure_host values in last 24 hours
$stmt = $db->query("
    SELECT COUNT(DISTINCT lure_host) as active_lures
    FROM lure_logs
    WHERE syslog_ts > strftime('%Y-%m-%dT%H:%M:%S', 'now', '-24 hours')
");

$result = $stmt->fetch(PDO::FETCH_ASSOC);

echo json_encode([
    'active_lures' => $result['active_lures'] ?? 0
]);
?>
