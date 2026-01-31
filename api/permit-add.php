<?php
require_once 'config-writable.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$entry = trim($data['entry'] ?? '');
$description = trim($data['description'] ?? '');

if (empty($entry)) {
    http_response_code(400);
    echo json_encode(['error' => 'Entry is required']);
    exit;
}

// Basic validation for IP or CIDR
if (!filter_var($entry, FILTER_VALIDATE_IP) && !preg_match('/^[\d\.]+\/\d{1,2}$/', $entry)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid IP or CIDR format']);
    exit;
}

try {
    $db = getDB();
    $stmt = $db->prepare("INSERT INTO permit_list (entry, description) VALUES (?, ?)");
    $stmt->execute([$entry, $description]);
    
    echo json_encode([
        'success' => true,
        'id' => $db->lastInsertId(),
        'entry' => $entry
    ]);
} catch (PDOException $e) {
    http_response_code(400);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>
