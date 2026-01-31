<?php
require_once 'config.php';

$db = getDB();

$stmt = $db->query("
    SELECT 
        lure_host,
        COUNT(*) as snares
    FROM lure_logs
    WHERE datetime(syslog_ts) > datetime('now', '-7 days')
    GROUP BY lure_host
    ORDER BY snares DESC
    LIMIT 10
");

$activity = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode($activity);
?>
