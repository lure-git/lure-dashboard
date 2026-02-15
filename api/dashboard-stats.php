<?php
require_once 'config.php';
$db = getDB();
$stats = [];
$today = date('Y-m-d\T00:00:00');
$result = $db->prepare("
    SELECT (SELECT SUM(count) FROM daily_totals WHERE date < date('now')) +
           (SELECT COUNT(*) FROM lure_logs WHERE syslog_ts >= :today) as count
");
$result->execute([':today' => $today]);
$stats['total_attacks'] = $result->fetch(PDO::FETCH_ASSOC)['count'];
$result = $db->prepare("
    SELECT COUNT(*) as count FROM (
        SELECT DISTINCT src_ip FROM daily_ip_stats WHERE date < date('now')
        UNION
        SELECT DISTINCT src_ip FROM lure_logs WHERE syslog_ts >= :today
    )
");
$result->execute([':today' => $today]);
$stats['unique_ips'] = $result->fetch(PDO::FETCH_ASSOC)['count'];
$result = $db->query("
    SELECT COUNT(*) as count
    FROM lure_logs
    WHERE syslog_ts > strftime('%Y-%m-%dT%H:%M:%S', 'now', '-24 hours')
");
$stats['attacks_24h'] = $result->fetch(PDO::FETCH_ASSOC)['count'];
echo json_encode($stats);
?>
