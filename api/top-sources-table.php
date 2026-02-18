<?php
require_once 'config.php';
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
$db = getDB();

// Use daily_ip_stats for top IPs by count (much faster at scale)
$stmt = $db->prepare("
    SELECT src_ip, SUM(count) as attacks
    FROM daily_ip_stats
    WHERE date >= date('now', '-30 days')
    AND src_ip NOT IN (SELECT entry FROM permit_list WHERE entry NOT LIKE '%/%')
    GROUP BY src_ip
    ORDER BY attacks DESC
    LIMIT :limit
");
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->execute();
$sources = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get port counts in a single query using daily_port_stats
if (!empty($sources)) {
    $ips = array_column($sources, 'src_ip');
    $placeholders = implode(',', array_fill(0, count($ips), '?'));
    
    $ps = $db->prepare("
        SELECT src_ip, COUNT(DISTINCT dpt) as ports
        FROM daily_port_stats
        WHERE src_ip IN ($placeholders)
        GROUP BY src_ip
    ");
    $ps->execute($ips);
    $port_counts = [];
    foreach ($ps->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $port_counts[$row['src_ip']] = $row['ports'];
    }
    
    foreach ($sources as &$source) {
        $source['ports_targeted'] = $port_counts[$source['src_ip']] ?? 0;
    }
}

echo json_encode($sources);
?>
