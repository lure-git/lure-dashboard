<?php
require_once 'config.php';

$db = getDB();

$stmt = $db->query("
    SELECT 
        src_ip,
        COUNT(*) as count
    FROM lure_logs
    GROUP BY src_ip
    ORDER BY count DESC
    LIMIT 10
");

$attackers = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode($attackers);
?>
