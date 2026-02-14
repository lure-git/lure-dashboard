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
        lure_host,
        CASE dpt
            WHEN 22 THEN 'SSH'
            WHEN 23 THEN 'Telnet'
            WHEN 80 THEN 'HTTP'
            WHEN 443 THEN 'HTTPS'
            WHEN 3389 THEN 'RDP'
            WHEN 8080 THEN 'HTTP-Alt'
            WHEN 445 THEN 'SMB'
            ELSE 'Other'
       END as service
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
