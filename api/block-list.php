<?php
require_once 'config.php';

$db = getDB();

// Get all potential blocked IPs (without permit filter in SQL)
$stmt = $db->query("
    SELECT 
        src_ip,
        MIN(datetime(syslog_ts)) as first_seen,
        MAX(datetime(syslog_ts)) as last_seen,
        COUNT(*) as attack_count,
        COUNT(DISTINCT dpt) as unique_ports,
        GROUP_CONCAT(DISTINCT dpt) as ports_targeted
    FROM lure_logs
    GROUP BY src_ip
    HAVING attack_count >= 1
    ORDER BY attack_count DESC
");

$allIPs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all permit list entries
$stmt = $db->query("SELECT entry FROM permit_list");
$permitList = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Separate exact IPs and CIDR ranges
$permitIPs = [];
$permitCIDRs = [];

foreach ($permitList as $entry) {
    if (strpos($entry, '/') !== false) {
        $permitCIDRs[] = $entry;
    } else {
        $permitIPs[] = $entry;
    }
}

// Filter out permitted IPs
$blocklist = [];
foreach ($allIPs as $ip) {
    $srcIP = $ip['src_ip'];
    
    // Check if IP is in exact permit list
    if (in_array($srcIP, $permitIPs)) {
        continue;
    }
    
    // Check if IP is in any CIDR range
    $inPermitRange = false;
    foreach ($permitCIDRs as $cidr) {
        if (ipInCidr($srcIP, $cidr)) {
            $inPermitRange = true;
            break;
        }
    }
    
    if (!$inPermitRange) {
        $blocklist[] = $ip;
    }
}

echo json_encode($blocklist);

// Helper function to check if IP is in CIDR range
function ipInCidr($ip, $cidr) {
    list($subnet, $mask) = explode('/', $cidr);
    $ip_long = ip2long($ip);
    $subnet_long = ip2long($subnet);
    $mask_long = -1 << (32 - (int)$mask);
    return ($ip_long & $mask_long) === ($subnet_long & $mask_long);
}
?>
