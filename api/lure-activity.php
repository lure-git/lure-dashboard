<?php
require_once 'config.php';
$db = getDB();

$days = isset($_GET['days']) ? (int)$_GET['days'] : 7;
if (!in_array($days, [7, 14, 21, 30])) {
    $days = 7;
}

$stmt = $db->prepare("
    SELECT 
        lure_host,
        COUNT(*) as snares
    FROM lure_logs
    WHERE syslog_ts > strftime('%Y-%m-%dT%H:%M:%S', 'now', '-' || :days || ' days')
    GROUP BY lure_host
    ORDER BY snares DESC
    LIMIT 10
");
$stmt->bindValue(':days', $days, PDO::PARAM_INT);
$stmt->execute();
$activity = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo json_encode($activity);
?>
