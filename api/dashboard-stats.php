<?php
require_once 'config.php';
$db = getDB();
$stats = [];

$lastDate = $db->query("SELECT MAX(date) || 'T' as cutoff FROM daily_ip_stats")->fetch(PDO::FETCH_ASSOC)['cutoff'];

$result = $db->prepare("
    SELECT (SELECT SUM(count) FROM daily_totals) +
           (SELECT COUNT(*) FROM lure_logs WHERE syslog_ts > :cutoff) as count
");
$result->execute([':cutoff' => $lastDate]);
$stats['total_attacks'] = $result->fetch(PDO::FETCH_ASSOC)['count'];

$result = $db->prepare("
    SELECT COUNT(*) as count FROM (
        SELECT DISTINCT src_ip FROM daily_ip_stats
        UNION
        SELECT DISTINCT src_ip FROM lure_logs WHERE syslog_ts > :cutoff
    )
");
$result->execute([':cutoff' => $lastDate]);
$stats['unique_ips'] = $result->fetch(PDO::FETCH_ASSOC)['count'];

$result = $db->query("
    SELECT COUNT(*) as count
    FROM lure_logs
    WHERE syslog_ts > strftime('%Y-%m-%dT%H:%M:%S', 'now', '-24 hours')
");
$stats['attacks_24h'] = $result->fetch(PDO::FETCH_ASSOC)['count'];

echo json_encode($stats);
?>
