<?php
require_once 'config.php';

$db = getDB();

$stmt = $db->query("
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
    GROUP BY dpt
    ORDER BY count DESC
");

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
