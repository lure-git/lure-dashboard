<?php
/**
 * Cast Status API - Get lure status
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../dashboard/includes/db.php';

$hostname = $_GET['hostname'] ?? '';

if (empty($hostname)) {
    die(json_encode(['success' => false, 'error' => 'Missing hostname']));
}

$lure = db_fetch_one(
    'SELECT status, ip_address, last_check FROM lure_health WHERE hostname = :hostname',
    [':hostname' => $hostname]
);

if ($lure) {
    echo json_encode([
        'success' => true,
        'hostname' => $hostname,
        'status' => $lure['status'],
        'ip_address' => $lure['ip_address'],
        'last_check' => $lure['last_check']
    ]);
} else {
    echo json_encode(['success' => false, 'error' => 'Lure not found']);
}
