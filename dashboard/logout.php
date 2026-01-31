<?php
require_once 'includes/auth.php';

audit_log('logout', 'user', $_SESSION['user_id'] ?? null, 'User logged out');

logout();
header('Location: login.php');
exit;
