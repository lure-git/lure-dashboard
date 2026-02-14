<?php
require_once 'config.php';
$db = getDB();

$days = isset($_GET['days']) ? (int)$_GET['days'] : 7;
if (!in_array($days, [7, 14, 21, 30])) {
    $days = 7;
}

$stmt = $db->prepare("
    SELECT 
        dpt as port,
        COUNT(*) as count,
        CASE dpt
            WHEN 22 THEN 'SSH'
            WHEN 80 THEN 'HTTP'
            WHEN 443 THEN 'HTTPS'
            WHEN 3389 THEN 'RDP'
            WHEN 23 THEN 'Telnet'
            WHEN 8080 THEN 'HTTP-Alt'
            WHEN 445 THEN 'SMB'
            ELSE 'Other'
        END as service
    FROM lure_logs
    WHERE syslog_ts > strftime('%Y-%m-%dT%H:%M:%S', 'now', '-' || :days || ' days')
    GROUP BY dpt
    ORDER BY count DESC
");
$stmt->bindValue(':days', $days, PDO::PARAM_INT);
$stmt->execute();
$all_ports = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Group "Others" together
$result = [];
$others_count = 0;
foreach ($all_ports as $port) {
    if ($port['service'] === 'Other') {
        $others_count += $port['count'];
    } else {
        $result[] = $port;
    }
}

// Add "Others" as a single entry
if ($others_count > 0) {
    $result[] = [
        'port' => 'Others',
        'count' => $others_count,
        'service' => 'Others'
    ];
}

echo json_encode($result);
?>
