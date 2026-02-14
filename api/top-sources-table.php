<?php
require_once 'config.php';
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
$db = getDB();

// Fast query: get top IPs by count
$stmt = $db->prepare("
    SELECT src_ip, COUNT(*) as attacks
    FROM lure_logs
    WHERE syslog_ts > strftime('%Y-%m-%dT%H:%M:%S', 'now', '-30 days')
    AND src_ip NOT IN (SELECT entry FROM permit_list WHERE entry NOT LIKE '%/%')
    GROUP BY src_ip
    ORDER BY attacks DESC
    LIMIT :limit
");
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->execute();
$sources = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get port counts for just the top IPs
foreach ($sources as &$source) {
    $ps = $db->prepare("SELECT COUNT(DISTINCT dpt) as ports FROM lure_logs WHERE src_ip = :ip");
    $ps->bindValue(':ip', $source['src_ip']);
    $ps->execute();
    $source['ports_targeted'] = $ps->fetch(PDO::FETCH_ASSOC)['ports'];
}

echo json_encode($sources);
?>
