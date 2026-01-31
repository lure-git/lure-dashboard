<?php
// Authentication functions for LURE dashboard

require_once __DIR__ . '/db.php';

session_start();

function login($username, $password) {
    $user = db_fetch_one(
        'SELECT id, username, password_hash, role, is_active FROM users WHERE username = :username',
        [':username' => $username]
    );
    
    if (!$user) {
        return ['success' => false, 'error' => 'Invalid username or password'];
    }
    
    if (!$user['is_active']) {
        return ['success' => false, 'error' => 'Account is disabled'];
    }
    
    if (!password_verify($password, $user['password_hash'])) {
        return ['success' => false, 'error' => 'Invalid username or password'];
    }
    
    // Update last login
    db_query('UPDATE users SET last_login = datetime("now") WHERE id = :id', [':id' => $user['id']]);
    
    // Set session
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['role'] = $user['role'];

    audit_log('login', 'user', $user['id'], 'Login successful');

    return ['success' => true];
}

function logout() {
    session_destroy();
    $_SESSION = [];
}

function is_logged_in() {
    return isset($_SESSION['user_id']);
}

function require_login() {
    if (!is_logged_in()) {
        header('Location: login.php');
        exit;
    }
}

function require_admin() {
    require_login();
    if ($_SESSION['role'] !== 'admin') {
        http_response_code(403);
        die('Access denied. Admin role required.');
    }
}

function current_user() {
    if (!is_logged_in()) {
        return null;
    }
    return [
        'id' => $_SESSION['user_id'],
        'username' => $_SESSION['username'],
        'role' => $_SESSION['role']
    ];
}

function audit_log($action, $target_type = null, $target_id = null, $details = null) {
    $user = current_user();
    $user_id = $user ? $user['id'] : null;
    $username = $user ? $user['username'] : 'anonymous';
    $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown';

    db_query(
        'INSERT INTO audit_log (user_id, username, action, target_type, target_id, details, ip_address) VALUES (:user_id, :username, :action, :target_type, :target_id, :details, :ip)',
        [
            ':user_id' => $user_id,
            ':username' => $username,
            ':action' => $action,
            ':target_type' => $target_type,
            ':target_id' => $target_id,
            ':details' => $details,
            ':ip' => $ip
        ]
    );
}
