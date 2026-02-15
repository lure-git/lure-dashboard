<?php
require_once 'config.php';
$db = getDB();
$stats = [];

// Total snared (30D)
$result = $db->query("SELECT COUNT(*) as count FROM lure_logs");
$stats['total_attacks'] = $result->fetch(PDO::FETCH_ASSOC)['count'];

// Unique IPs
$result = $db->query("SELECT COUNT(DISTINCT src_ip) as count FROM lure_logs");
$stats['unique_ips'] = $result->fetch(PDO::FETCH_ASSOC)['count'];

// Snared last 24h
$result = $db->query("
    SELECT COUNT(*) as count
    FROM lure_logs
    WHERE syslog_ts > strftime('%Y-%m-%dT%H:%M:%S', 'now', '-24 hours')
");
$stats['attacks_24h'] = $result->fetch(PDO::FETCH_ASSOC)['count'];

echo json_encode($stats);
?>
