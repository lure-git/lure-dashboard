<?php
require_once 'config.php';
$db = getDB();

$days = isset($_GET['days']) ? (int)$_GET['days'] : 7;
if (!in_array($days, [7, 14, 21, 30])) {
    $days = 7;
}

$stmt = $db->prepare("
    SELECT 
        src_ip,
        COUNT(*) as count
    FROM lure_logs
    WHERE syslog_ts > strftime('%Y-%m-%dT%H:%M:%S', 'now', '-' || :days || ' days')
    GROUP BY src_ip
    ORDER BY count DESC
    LIMIT 10
");
$stmt->bindValue(':days', $days, PDO::PARAM_INT);
$stmt->execute();
$attackers = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo json_encode($attackers);
?>
