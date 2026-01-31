<?php
require_once 'includes/auth.php';
require_login();

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    $user = db_fetch_one('SELECT password_hash FROM users WHERE id = :id', [':id' => $_SESSION['user_id']]);
    
    if (!password_verify($current_password, $user['password_hash'])) {
        $error = 'Current password is incorrect';
    } elseif (strlen($new_password) < 8) {
        $error = 'New password must be at least 8 characters';
    } elseif ($new_password !== $confirm_password) {
        $error = 'New passwords do not match';
    } else {
        $hash = password_hash($new_password, PASSWORD_DEFAULT);
	db_query('UPDATE users SET password_hash = :hash WHERE id = :id', [
            ':hash' => $hash,
            ':id' => $_SESSION['user_id']
        ]);
        audit_log('password_change', 'user', $_SESSION['user_id'], 'User changed own password');
        $message = 'Password changed successfully';
    }
}

$user_info = db_fetch_one('SELECT username, email, role, created_at, last_login FROM users WHERE id = :id', [':id' => $_SESSION['user_id']]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LURE | My Profile</title>
    <link rel="stylesheet" href="dist/css/adminlte.min.css">
    <link rel="stylesheet" href="plugins/fontawesome-free/css/all.min.css">
</head>
<body class="hold-transition sidebar-mini">
<div class="wrapper">
    <?php include 'includes/navbar.php'; ?>

    <div class="content-wrapper">
        <div class="content-header">
            <div class="container-fluid">
                <div class="row mb-2">
                    <div class="col-sm-6">
                        <h1 class="m-0">My Profile</h1>
                    </div>
                </div>
            </div>
        </div>

        <div class="content">
            <div class="container-fluid">
                
                <?php if ($message): ?>
                <div class="alert alert-success alert-dismissible">
                    <button type="button" class="close" data-dismiss="alert">&times;</button>
                    <i class="fas fa-check-circle mr-2"></i><?php echo htmlspecialchars($message); ?>
                </div>
                <?php endif; ?>
                
                <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible">
                    <button type="button" class="close" data-dismiss="alert">&times;</button>
                    <i class="fas fa-exclamation-circle mr-2"></i><?php echo htmlspecialchars($error); ?>
                </div>
                <?php endif; ?>

                <div class="row">
                    <div class="col-md-6">
                        <div class="card card-primary">
                            <div class="card-header">
                                <h3 class="card-title"><i class="fas fa-user mr-2"></i>Account Information</h3>
                            </div>
                            <div class="card-body">
                                <table class="table table-borderless">
                                    <tr>
                                        <th style="width: 150px;">Username:</th>
                                        <td><?php echo htmlspecialchars($user_info['username']); ?></td>
                                    </tr>
                                    <tr>
                                        <th>Email:</th>
                                        <td><?php echo htmlspecialchars($user_info['email'] ?? 'Not set'); ?></td>
                                    </tr>
                                    <tr>
                                        <th>Role:</th>
                                        <td>
                                            <span class="badge badge-<?php echo $user_info['role'] === 'admin' ? 'danger' : 'info'; ?>">
                                                <?php echo htmlspecialchars($user_info['role']); ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th>Created:</th>
                                        <td><?php echo htmlspecialchars($user_info['created_at']); ?></td>
                                    </tr>
                                    <tr>
                                        <th>Last Login:</th>
                                        <td><?php echo htmlspecialchars($user_info['last_login'] ?? 'Never'); ?></td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="card card-warning">
                            <div class="card-header">
                                <h3 class="card-title"><i class="fas fa-key mr-2"></i>Change Password</h3>
                            </div>
                            <form method="post">
                                <div class="card-body">
                                    <div class="form-group">
                                        <label>Current Password</label>
                                        <input type="password" name="current_password" class="form-control" required>
                                    </div>
                                    <div class="form-group">
                                        <label>New Password</label>
                                        <input type="password" name="new_password" class="form-control" minlength="8" required>
                                        <small class="form-text text-muted">Minimum 8 characters</small>
                                    </div>
                                    <div class="form-group">
                                        <label>Confirm New Password</label>
                                        <input type="password" name="confirm_password" class="form-control" minlength="8" required>
                                    </div>
                                </div>
                                <div class="card-footer">
                                    <button type="submit" class="btn btn-warning">
                                        <i class="fas fa-save mr-2"></i>Change Password
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <footer class="main-footer">
        <strong>LURE Dashboard</strong>
    </footer>
</div>

<script src="plugins/jquery/jquery.min.js"></script>
<script src="plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="dist/js/adminlte.min.js"></script>
</body>
</html>
