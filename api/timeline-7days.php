<?php
require_once 'config.php';
$db = getDB();

$days = isset($_GET['days']) ? (int)$_GET['days'] : 7;
if (!in_array($days, [7, 14, 21, 30])) {
    $days = 7;
}

// Use pre-aggregated daily_totals instead of scanning raw lure_logs
$stmt = $db->prepare("
    SELECT date, proto, SUM(count) as count
    FROM daily_totals
    WHERE date >= date('now', '-' || :days || ' days')
    GROUP BY date, proto
    ORDER BY date ASC, proto
");
$stmt->bindValue(':days', $days, PDO::PARAM_INT);
$stmt->execute();
$data = $stmt->fetchAll(PDO::FETCH_ASSOC);

$result = ['dates' => [], 'tcp' => [], 'udp' => [], 'icmp' => []];
$by_date = [];

foreach ($data as $row) {
    $d = $row['date'];
    if (!isset($by_date[$d])) {
        $by_date[$d] = ['tcp' => 0, 'udp' => 0, 'icmp' => 0];
    }
    $proto = strtolower($row['proto']);
    if (isset($by_date[$d][$proto])) {
        $by_date[$d][$proto] = (int)$row['count'];
    }
}

foreach ($by_date as $date => $protos) {
    $result['dates'][] = $date;
    $result['tcp'][] = $protos['tcp'];
    $result['udp'][] = $protos['udp'];
    $result['icmp'][] = $protos['icmp'];
}

echo json_encode($result);
?>
