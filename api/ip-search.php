<?php
require_once 'config.php';

header('Content-Type: application/json');

$ip = trim($_GET['ip'] ?? '');
$cidr = trim($_GET['cidr'] ?? '');

// Determine if searching for IP or CIDR
$searchType = 'ip';
$searchValue = $ip;

if (!empty($cidr)) {
    $searchType = 'cidr';
    $searchValue = $cidr;
} elseif (strpos($ip, '/') !== false) {
    $searchType = 'cidr';
    $searchValue = $ip;
}

if (empty($searchValue)) {
    http_response_code(400);
    echo json_encode(['error' => 'IP or CIDR parameter required']);
    exit;
}

// Validate input
if ($searchType === 'ip') {
    if (!filter_var($searchValue, FILTER_VALIDATE_IP)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid IP format']);
        exit;
    }
} else {
    // Basic CIDR validation
    if (!preg_match('/^[\d\.]+\/\d{1,2}$/', $searchValue)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid CIDR format (use x.x.x.x/x)']);
        exit;
    }
}

$db = getDB();

if ($searchType === 'ip') {
    // SEARCH FOR SINGLE IP
    
    // Check permit list (exact match)
    $stmt = $db->prepare("SELECT entry, description FROM permit_list WHERE entry = ?");
    $stmt->execute([$searchValue]);
    $permitExact = $stmt->fetch(PDO::FETCH_ASSOC);

    // Check permit list (CIDR ranges)
    $stmt = $db->query("SELECT entry, description FROM permit_list WHERE entry LIKE '%/%'");
    $cidrs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $inPermitRange = false;
    foreach ($cidrs as $cidrEntry) {
        if (ipInCidr($searchValue, $cidrEntry['entry'])) {
            $inPermitRange = $cidrEntry;
            break;
        }
    }

    // Check block list (with permit filtering)
    $stmt = $db->prepare("
        SELECT 
            src_ip,
            MIN(datetime(syslog_ts)) as first_seen,
            MAX(datetime(syslog_ts)) as last_seen,
            COUNT(*) as attack_count
        FROM lure_logs
        WHERE src_ip = ?
        GROUP BY src_ip
        HAVING attack_count >= 3
    ");
    $stmt->execute([$searchValue]);
    $inBlockList = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Only consider it blocked if NOT in permit list
    if ($inBlockList && ($permitExact || $inPermitRange)) {
        $inBlockList = false;
    }

    echo json_encode([
        'search_type' => 'ip',
        'ip' => $searchValue,
        'in_permit_list' => $permitExact !== false || $inPermitRange !== false,
        'permit_entry' => $permitExact ?: ($inPermitRange ?: null),
        'in_block_list' => $inBlockList !== false,
        'block_info' => $inBlockList ?: null
    ]);

} else {
    // SEARCH FOR CIDR RANGE
    
    // Check if range itself is in permit list
    $stmt = $db->prepare("SELECT entry, description FROM permit_list WHERE entry = ?");
    $stmt->execute([$searchValue]);
    $permitExact = $stmt->fetch(PDO::FETCH_ASSOC);

    // Check if this range overlaps with any permit list ranges
    $stmt = $db->query("SELECT entry, description FROM permit_list WHERE entry LIKE '%/%'");
    $permitRanges = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $overlappingPermit = [];
    foreach ($permitRanges as $range) {
        if ($range['entry'] === $searchValue || cidrOverlaps($searchValue, $range['entry'])) {
            $overlappingPermit[] = $range;
        }
    }

    // Get all IPs from logs that fall within this CIDR and filter by permit list
    $stmt = $db->prepare("
        SELECT 
            src_ip,
            MIN(datetime(syslog_ts)) as first_seen,
            MAX(datetime(syslog_ts)) as last_seen,
            COUNT(*) as attack_count
        FROM lure_logs
        GROUP BY src_ip
        HAVING attack_count >= 3
    ");
    $stmt->execute();
    $allBlocked = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get all permit list entries for filtering
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
    
    $blockedInRange = [];
    
    foreach ($allBlocked as $blocked) {
        $srcIP = $blocked['src_ip'];
        
        // Only include if IP is in search range
        if (!ipInCidr($srcIP, $searchValue)) {
            continue;
        }
        
        // Skip if IP is in exact permit list
        if (in_array($srcIP, $permitIPs)) {
            continue;
        }
        
        // Skip if IP is in any CIDR range in permit list
        $inPermitRange = false;
        foreach ($permitCIDRs as $cidr) {
            if (ipInCidr($srcIP, $cidr)) {
                $inPermitRange = true;
                break;
            }
        }
        
        if (!$inPermitRange) {
            $blockedInRange[] = $blocked;
        }
    }

    echo json_encode([
        'search_type' => 'cidr',
        'cidr' => $searchValue,
        'in_permit_list' => $permitExact !== false,
        'permit_entry' => $permitExact ?: null,
        'overlapping_permit_ranges' => $overlappingPermit,
        'blocked_ips_in_range' => [
            'count' => count($blockedInRange),
            'ips' => array_slice($blockedInRange, 0, 100) // Limit to first 100 for display
        ]
    ]);
}

// Helper function to check if IP is in CIDR range
function ipInCidr($ip, $cidr) {
    list($subnet, $mask) = explode('/', $cidr);
    $ip_long = ip2long($ip);
    $subnet_long = ip2long($subnet);
    $mask_long = -1 << (32 - (int)$mask);
    return ($ip_long & $mask_long) === ($subnet_long & $mask_long);
}

// Helper function to check if two CIDR ranges overlap
function cidrOverlaps($cidr1, $cidr2) {
    list($ip1, $mask1) = explode('/', $cidr1);
    list($ip2, $mask2) = explode('/', $cidr2);
    
    $ip1_long = ip2long($ip1);
    $ip2_long = ip2long($ip2);
    $mask1_long = -1 << (32 - (int)$mask1);
    $mask2_long = -1 << (32 - (int)$mask2);
    
    $network1 = $ip1_long & $mask1_long;
    $network2 = $ip2_long & $mask2_long;
    $broadcast1 = $network1 | ~$mask1_long;
    $broadcast2 = $network2 | ~$mask2_long;
    
    return !($broadcast1 < $network2 || $broadcast2 < $network1);
}
?>
