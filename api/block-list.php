<?php
/**
 * Block List API - Real-time with streaming output
 * 
 * Queries lure_logs directly for real-time data.
 * Streams JSON output to avoid memory exhaustion.
 * 
 * Parameters:
 *   format=json (default) | txt
 *   min_count=N (default 1)
 * 
 * Rewritten: Feb 16, 2026 - fixes 25% failure rate under load
 */

require_once 'config.php';

$format = $_GET['format'] ?? 'json';
$minCount = max(1, (int)($_GET['min_count'] ?? 1));

$db = getDB();

// Get permit list - separate exact IPs from CIDRs
$stmt = $db->query("SELECT entry FROM permit_list");
$permitList = $stmt->fetchAll(PDO::FETCH_COLUMN);

$permitIPs = [];
$permitCIDRs = [];
foreach ($permitList as $entry) {
    if (strpos($entry, '/') !== false) {
        $permitCIDRs[] = $entry;
    } else {
        $permitIPs[$entry] = true;
    }
}

// Build SQL exclusion for exact permit IPs (CIDRs checked in PHP)
$permitExclusion = '';
$bindParams = [];
if (!empty($permitIPs)) {
    $placeholders = implode(',', array_fill(0, count($permitIPs), '?'));
    $permitExclusion = "WHERE src_ip NOT IN ($placeholders)";
    $bindParams = array_keys($permitIPs);
}

// Query lure_logs directly - real-time data
// Uses covering index idx_lure_logs_src_ts_dpt
$sql = "
    SELECT 
        src_ip,
        MIN(syslog_ts) as first_seen,
        MAX(syslog_ts) as last_seen,
        COUNT(*) as attack_count,
        COUNT(DISTINCT dpt) as unique_ports,
        GROUP_CONCAT(DISTINCT dpt) as ports_targeted
    FROM lure_logs
    $permitExclusion
    GROUP BY src_ip
    HAVING attack_count >= ?
    ORDER BY attack_count DESC
";

$stmt = $db->prepare($sql);

// Bind permit IPs then min_count
$paramIndex = 1;
foreach ($bindParams as $ip) {
    $stmt->bindValue($paramIndex++, $ip);
}
$stmt->bindValue($paramIndex, $minCount, PDO::PARAM_INT);

$stmt->execute();

// Output based on format
if ($format === 'txt' || $format === 'text') {
    // Plain text - one IP per line (for firewall EDL)
    header('Content-Type: text/plain');
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        if (!ipInPermitCidr($row['src_ip'], $permitCIDRs)) {
            echo $row['src_ip'] . "\n";
        }
    }
} else {
    // JSON - stream array without loading all into memory
    header('Content-Type: application/json');
    
    echo '[';
    $first = true;
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        if (!ipInPermitCidr($row['src_ip'], $permitCIDRs)) {
            if (!$first) {
                echo ',';
            }
            $first = false;
            
            echo json_encode([
                'src_ip' => $row['src_ip'],
                'first_seen' => $row['first_seen'],
                'last_seen' => $row['last_seen'],
                'attack_count' => (int)$row['attack_count'],
                'unique_ports' => (int)$row['unique_ports'],
                'ports_targeted' => $row['ports_targeted']
            ]);
        }
    }
    
    echo ']';
}

/**
 * Check if IP is in any permit CIDR range
 */
function ipInPermitCidr($ip, $cidrs) {
    if (empty($cidrs)) {
        return false;
    }
    
    $ip_long = ip2long($ip);
    if ($ip_long === false) {
        return false;
    }
    
    foreach ($cidrs as $cidr) {
        list($subnet, $mask) = explode('/', $cidr);
        $subnet_long = ip2long($subnet);
        if ($subnet_long === false) {
            continue;
        }
        $mask_long = -1 << (32 - (int)$mask);
        if (($ip_long & $mask_long) === ($subnet_long & $mask_long)) {
            return true;
        }
    }
    
    return false;
}
?>
