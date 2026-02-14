<?php
require_once 'config.php';
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
$db = getDB();
$stmt = $db->prepare("
    SELECT
        syslog_ts,
        src_ip,
        dpt as port,
        proto,
        lure_host
    FROM lure_logs
    WHERE src_ip NOT IN (SELECT entry FROM permit_list WHERE entry NOT LIKE '%/%')
    ORDER BY syslog_ts DESC
    LIMIT :limit
");
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->execute();
$activity = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo json_encode($activity);
?>
