<?php
require_once 'config.php';
$db = getDB();

$lastDate = $db->query("SELECT MAX(date) || 'T' as cutoff FROM daily_totals")->fetch(PDO::FETCH_ASSOC)['cutoff'];

$stmt = $db->prepare("
    SELECT protocol, SUM(cnt) as count FROM (
        SELECT UPPER(proto) as protocol, SUM(count) as cnt FROM daily_totals GROUP BY UPPER(proto)
        UNION ALL
        SELECT UPPER(proto) as protocol, COUNT(*) as cnt FROM lure_logs
        WHERE syslog_ts > :cutoff
        GROUP BY UPPER(proto)
    ) GROUP BY protocol ORDER BY count DESC
");
$stmt->execute([':cutoff' => $lastDate]);
$protocols = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo json_encode($protocols);
?>
