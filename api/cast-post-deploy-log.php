<?php
/**
 * Cast Post-Deploy Log API - Read post-deploy log output for a hostname
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../dashboard/includes/db.php';
require_once __DIR__ . '/../dashboard/includes/auth.php';

if (!is_logged_in()) {
    http_response_code(401);
    die(json_encode(['success' => false, 'error' => 'Unauthorized']));
}

$hostname = $_GET['hostname'] ?? '';

if (empty($hostname)) {
    die(json_encode(['success' => false, 'error' => 'Missing hostname']));
}

// Sanitize hostname to prevent path traversal
$hostname = basename($hostname);

$log_file = '/var/log/lures/post-deploy.log';

if (!file_exists($log_file)) {
    echo json_encode(['success' => true, 'log' => '']);
    exit;
}

$lines = file($log_file, FILE_IGNORE_NEW_LINES);

// Find the LAST block that starts with "Post-Deploy: <hostname>"
$block_start = -1;
for ($i = count($lines) - 1; $i >= 0; $i--) {
    if (strpos($lines[$i], "Post-Deploy: $hostname") !== false) {
        $block_start = $i;
        break;
    }
}

if ($block_start === -1) {
    echo json_encode(['success' => true, 'log' => '']);
    exit;
}

// Capture from block start until the next "Post-Deploy:" line or end of file
$relevant = [];
for ($i = $block_start; $i < count($lines); $i++) {
    // Stop if we hit the start of a different deployment
    if ($i > $block_start && strpos($lines[$i], 'Post-Deploy:') !== false) {
        break;
    }
    $relevant[] = $lines[$i];
}

echo json_encode([
    'success' => true,
    'log' => implode("\n", $relevant)
]);
