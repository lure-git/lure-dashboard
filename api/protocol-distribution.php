<?php
require_once 'config.php';
$db = getDB();
$today = date('Y-m-d\T00:00:00');
$stmt = $db->prepare("
    SELECT protocol, SUM(cnt) as count FROM (
        SELECT UPPER(proto) as protocol, SUM(count) as cnt FROM daily_totals WHERE date < date('now') GROUP BY UPPER(proto)
        UNION ALL
        SELECT UPPER(proto) as protocol, COUNT(*) as cnt FROM lure_logs
        WHERE syslog_ts >= :today
        GROUP BY UPPER(proto)
    ) GROUP BY protocol ORDER BY count DESC
");
$stmt->execute([':today' => $today]);
$protocols = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo json_encode($protocols);
?>
