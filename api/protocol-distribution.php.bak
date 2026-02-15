<?php
require_once 'config.php';

$db = getDB();

$stmt = $db->query("
    SELECT 
        UPPER(proto) as protocol,
        COUNT(*) as count
    FROM lure_logs
    GROUP BY proto
    ORDER BY count DESC
");

$protocols = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode($protocols);
?>
