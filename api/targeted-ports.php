<?php
require_once 'config.php';
$db = getDB();

$days = isset($_GET['days']) ? (int)$_GET['days'] : 7;
if (!in_array($days, [7, 14, 21, 30])) {
    $days = 7;
}

// Service label lookup
$services = [
    // Web & Proxy
    21 => 'FTP',
    80 => 'HTTP',
    443 => 'HTTPS',
    8080 => 'HTTP-Proxy',
    8443 => 'HTTPS-Alt',
    3128 => 'Squid',
    // Remote Access
    22 => 'SSH',
    23 => 'Telnet',
    2222 => 'SSH-Alt',
    3389 => 'RDP',
    5900 => 'VNC',
    // Email
    25 => 'SMTP',
    110 => 'POP3',
    143 => 'IMAP',
    465 => 'SMTPS',
    587 => 'Submission',
    993 => 'IMAPS',
    995 => 'POP3S',
    // DNS/Network
    53 => 'DNS',
    123 => 'NTP',
    161 => 'SNMP',
    389 => 'LDAP',
    445 => 'SMB',
    135 => 'RPC',
    // Databases
    1433 => 'MSSQL',
    3306 => 'MySQL',
    5432 => 'PostgreSQL',
    1521 => 'Oracle',
    6379 => 'Redis',
    9200 => 'Elasticsearch',
    27017 => 'MongoDB',
    // VoIP/IoT
    5060 => 'SIP',
    1883 => 'MQTT',
    // Container/Cloud
    2375 => 'Docker',
    6443 => 'K8s-API',
    5000 => 'Docker-Reg',
    // VPN
    1080 => 'SOCKS',
    1194 => 'OpenVPN',
    1723 => 'PPTP',
    4500 => 'IPSec',
];
$stmt = $db->prepare("
    SELECT
        dpt as port,
        COUNT(*) as count
    FROM lure_logs
    WHERE syslog_ts > strftime('%Y-%m-%dT%H:%M:%S', 'now', '-' || :days || ' days')
    GROUP BY dpt
    ORDER BY count DESC
");
$stmt->bindValue(':days', $days, PDO::PARAM_INT);
$stmt->execute();
$all_ports = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Top 15 named ports, rest go to Others
$result = [];
$shown = 0;

foreach ($all_ports as $port) {
    $p = (int)$port['port'];
    $label = $services[$p] ?? null;

    if ($shown < 15 && $label !== null) {
        $port['service'] = $label;
        $result[] = $port;
        $shown++;
    }
}

echo json_encode($result);
?>
