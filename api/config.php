<?php
define('DB_PATH', '/var/log/lures/lure_logs.db');

function getDB() {
    try {
        // Open database in read-only mode
        $db = new PDO('sqlite:' . DB_PATH, null, null, [
            PDO::SQLITE_ATTR_OPEN_FLAGS => PDO::SQLITE_OPEN_READONLY
        ]);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Additional security: disable extensions
        $db->exec('PRAGMA query_only = ON');
        
        return $db;
    } catch(PDOException $e) {
        header('HTTP/1.1 500 Internal Server Error');
        echo json_encode(['error' => 'Database connection failed: ' . $e->getMessage()]);
        exit;
    }
}

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
?>
