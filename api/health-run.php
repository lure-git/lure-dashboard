<?php
require_once 'config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Run the health check script (no sudo needed - www-data is in adm group)
$script = '/usr/local/share/lure/health/lure-health-check.sh';

// Check if script exists and is executable
if (!file_exists($script) || !is_executable($script)) {
    echo json_encode(['error' => 'Health check script not found or not executable']);
    exit;
}

$output = [];
$return_var = 0;
exec($script . ' 2>&1', $output, $return_var);

if ($return_var === 0) {
    echo json_encode(['success' => true, 'output' => implode("\n", $output)]);
} else {
    echo json_encode(['error' => 'Health check failed', 'output' => implode("\n", $output), 'code' => $return_var]);
}
?>
