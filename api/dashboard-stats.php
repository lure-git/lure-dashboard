<?php
require_once 'config.php';

$db = getDB();
$stats = [];

// Total attacks
$result = $db->query("SELECT COUNT(*) as count FROM lure_logs");
$stats['total_attacks'] = $result->fetch(PDO::FETCH_ASSOC)['count'];

// Unique IPs
$result = $db->query("SELECT COUNT(DISTINCT src_ip) as count FROM lure_logs");
$stats['unique_ips'] = $result->fetch(PDO::FETCH_ASSOC)['count'];

// Most common protocol with percentage
$result = $db->query("
    SELECT proto, COUNT(*) as count 
    FROM lure_logs 
    GROUP BY proto 
    ORDER BY count DESC 
    LIMIT 1
");
$top_proto = $result->fetch(PDO::FETCH_ASSOC);
$stats['top_protocol'] = $top_proto['proto'] ?? 'N/A';
$stats['top_protocol_percent'] = $stats['total_attacks'] > 0 
    ? round(($top_proto['count'] / $stats['total_attacks']) * 100) 
    : 0;

// Attacks last 24h
$result = $db->query("
    SELECT COUNT(*) as count 
    FROM lure_logs 
    WHERE datetime(syslog_ts) > datetime('now', '-24 hours')
");
$stats['attacks_24h'] = $result->fetch(PDO::FETCH_ASSOC)['count'];

echo json_encode($stats);
?>
