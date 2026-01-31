<?php
require_once 'config.php';

$db = getDB();

// Get last 7 days of data grouped by date and protocol
$stmt = $db->query("
    SELECT 
        DATE(syslog_ts) as date,
        proto,
        COUNT(*) as count
    FROM lure_logs
    WHERE datetime(syslog_ts) > datetime('now', '-7 days')
    GROUP BY DATE(syslog_ts), proto
    ORDER BY date ASC, proto
");

$data = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Organize data by protocol
$result = [
    'dates' => [],
    'tcp' => [],
    'udp' => [],
    'icmp' => []
];

// Get all unique dates
$dates_query = $db->query("
    SELECT DISTINCT DATE(syslog_ts) as date
    FROM lure_logs
    WHERE datetime(syslog_ts) > datetime('now', '-7 days')
    ORDER BY date ASC
");
$dates = $dates_query->fetchAll(PDO::FETCH_COLUMN);

// Initialize arrays
foreach ($dates as $date) {
    $result['dates'][] = $date;
    $result['tcp'][] = 0;
    $result['udp'][] = 0;
    $result['icmp'][] = 0;
}

// Fill in the data
foreach ($data as $row) {
    $date_index = array_search($row['date'], $result['dates']);
    if ($date_index !== false) {
        $proto = strtolower($row['proto']);
        if (isset($result[$proto])) {
            $result[$proto][$date_index] = (int)$row['count'];
        }
    }
}

echo json_encode($result);
?>
