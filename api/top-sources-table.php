<?php
require_once 'config.php';

$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;

$db = getDB();

$stmt = $db->prepare("
    SELECT 
        src_ip,
        COUNT(*) as attacks,
        COUNT(DISTINCT dpt) as ports_targeted
    FROM lure_logs
    GROUP BY src_ip
    ORDER BY attacks DESC
    LIMIT :limit
");
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->execute();

$sources = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode($sources);
?>
