<?php
require_once 'config.php';

header('Content-Type: application/json');

$db = getDB();

// Get daily stats
$stmt = $db->query("
    SELECT 
        DATE(syslog_ts) as date,
        src_ip,
        COUNT(*) as snared
    FROM lure_logs
    GROUP BY DATE(syslog_ts), src_ip
");

$rawData = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get permit list for filtering
$stmt = $db->query("SELECT entry FROM permit_list");
$permitList = $stmt->fetchAll(PDO::FETCH_COLUMN);

$permitIPs = [];
$permitCIDRs = [];

foreach ($permitList as $entry) {
    if (strpos($entry, '/') !== false) {
        $permitCIDRs[] = $entry;
    } else {
        $permitIPs[] = $entry;
    }
}

// Filter and aggregate by date
$dailyStats = [];

foreach ($rawData as $row) {
    $srcIP = $row['src_ip'];
    $date = $row['date'];
    
    // Skip if in exact permit list
    if (in_array($srcIP, $permitIPs)) {
        continue;
    }
    
    // Skip if in CIDR range
    $inPermitRange = false;
    foreach ($permitCIDRs as $cidr) {
        if (ipInCidr($srcIP, $cidr)) {
            $inPermitRange = true;
            break;
        }
    }
    
    if ($inPermitRange) {
        continue;
    }
    
    // Aggregate stats
    if (!isset($dailyStats[$date])) {
        $dailyStats[$date] = [
            'date' => $date,
            'unique_ips' => [],
            'total_snared' => 0
        ];
    }
    
    $dailyStats[$date]['unique_ips'][$srcIP] = true;
    $dailyStats[$date]['total_snared'] += $row['snared'];
}

// Format output
$result = [];
foreach ($dailyStats as $date => $stats) {
    $result[] = [
        'date' => $date,
        'unique_ips' => count($stats['unique_ips']),
        'total_snared' => $stats['total_snared']
    ];
}

// Sort by date descending
usort($result, function($a, $b) {
    return strcmp($b['date'], $a['date']);
});

echo json_encode($result);

// Helper function
function ipInCidr($ip, $cidr) {
    list($subnet, $mask) = explode('/', $cidr);
    $ip_long = ip2long($ip);
    $subnet_long = ip2long($subnet);
    $mask_long = -1 << (32 - (int)$mask);
    return ($ip_long & $mask_long) === ($subnet_long & $mask_long);
}
?>
