<?php
require_once 'config.php';

header('Content-Type: application/json');

$db = getDB();

// Get lure health
$stmt = $db->query("
    SELECT 
        lure_id,
        hostname,
        ip_address,
        status,
        last_check,
        ssh_ok,
        rsyslog_ok,
        nftables_ok,
        disk_percent,
        memory_percent,
        uptime,
        load,
        last_log_received,
        error_message
    FROM lure_health
    ORDER BY lure_id
");

$lures = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get EM health
$stmt = $db->query("
    SELECT 
        hostname,
        status,
        last_check,
        ssh_ok,
        rsyslog_ok,
        nginx_ok,
        disk_percent,
        memory_percent,
        uptime,
        load,
        ssl_status,
        ssl_days_left,
        ssl_expiry,
        error_message
    FROM em_health
    LIMIT 1
");

$em = $stmt->fetch(PDO::FETCH_ASSOC);

// Add summary stats for lures
$summary = [
    'total' => count($lures),
    'online' => 0,
    'offline' => 0,
    'degraded' => 0
];

foreach ($lures as $lure) {
    if ($lure['status'] === 'online') $summary['online']++;
    elseif ($lure['status'] === 'offline') $summary['offline']++;
    else $summary['degraded']++;
}

echo json_encode([
    'summary' => $summary,
    'lures' => $lures,
    'em' => $em,
    'checked_at' => date('Y-m-d H:i:s')
]);
?>
