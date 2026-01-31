<?php
// Database connection for LURE dashboard

define('DB_PATH', '/var/log/lures/lure_logs.db');

function get_db() {
    static $db = null;
    if ($db === null) {
        $db = new SQLite3(DB_PATH);
        $db->busyTimeout(5000);
    }
    return $db;
}

function db_query($sql, $params = []) {
    $db = get_db();
    $stmt = $db->prepare($sql);
    
    foreach ($params as $key => $value) {
        $type = is_int($value) ? SQLITE3_INTEGER : SQLITE3_TEXT;
        $stmt->bindValue($key, $value, $type);
    }
    
    return $stmt->execute();
}

function db_fetch_all($sql, $params = []) {
    $result = db_query($sql, $params);
    $rows = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $rows[] = $row;
    }
    return $rows;
}

function db_fetch_one($sql, $params = []) {
    $result = db_query($sql, $params);
    return $result->fetchArray(SQLITE3_ASSOC);
}
