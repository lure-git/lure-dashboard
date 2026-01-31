<?php
require_once 'config.php';

$db = getDB();

$stmt = $db->query("
    SELECT id, entry, description, created_at 
    FROM permit_list 
    ORDER BY created_at DESC
");

$entries = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode($entries);
?>
