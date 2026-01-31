<?php
require_once 'includes/auth.php';
require_admin();
$message = '';
$error = '';
// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add') {
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $role = $_POST['role'] ?? 'user';
        
        if (empty($username) || empty($password)) {
            $error = 'Username and password are required';
        } else {
            $existing = db_fetch_one('SELECT id FROM users WHERE username = :username', [':username' => $username]);
            if ($existing) {
                $error = 'Username already exists';
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                db_query(
                    'INSERT INTO users (username, email, password_hash, role) VALUES (:username, :email, :hash, :role)',
                    [':username' => $username, ':email' => $email, ':hash' => $hash, ':role' => $role]
                );
                audit_log('user_create', 'user', $username, "Role: $role, Email: $email");
                $message = "User '$username' created successfully";
            }
        }
    }
    
    if ($action === 'delete') {
        $user_id = (int)($_POST['user_id'] ?? 0);
        
        // Prevent self-deletion
        if ($user_id === $_SESSION['user_id']) {
            $error = 'You cannot delete your own account';
        } else {
            $user = db_fetch_one('SELECT username FROM users WHERE id = :id', [':id' => $user_id]);
            if ($user) {
                db_query('DELETE FROM users WHERE id = :id', [':id' => $user_id]);
                audit_log('user_delete', 'user', $user['username'], null);
                $message = "User '{$user['username']}' deleted";
            }
        }
    }
    
    if ($action === 'toggle') {
        $user_id = (int)($_POST['user_id'] ?? 0);
        
        if ($user_id === $_SESSION['user_id']) {
            $error = 'You cannot disable your own account';
        } else {
            $target_user = db_fetch_one('SELECT username, is_active FROM users WHERE id = :id', [':id' => $user_id]);
            db_query('UPDATE users SET is_active = NOT is_active WHERE id = :id', [':id' => $user_id]);
            $new_status = $target_user['is_active'] ? 'disabled' : 'enabled';
            audit_log('user_toggle', 'user', $target_user['username'], "User $new_status");
            $message = 'User status updated';
        }
    }
    
    if ($action === 'reset_password') {
        $user_id = (int)($_POST['user_id'] ?? 0);
        $new_password = $_POST['new_password'] ?? '';
        
        if (empty($new_password)) {
            $error = 'Password cannot be empty';
        } else {
            $target_user = db_fetch_one('SELECT username FROM users WHERE id = :id', [':id' => $user_id]);
            $hash = password_hash($new_password, PASSWORD_DEFAULT);
            db_query('UPDATE users SET password_hash = :hash WHERE id = :id', [':hash' => $hash, ':id' => $user_id]);
            audit_log('password_reset', 'user', $target_user['username'], 'Admin reset password');
            $message = 'Password updated';
        }
    }
}
$users = db_fetch_all('SELECT id, username, email, role, is_active, created_at, last_login FROM users ORDER BY id');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LURE | User Management</title>
    <link rel="stylesheet" href="dist/css/adminlte.min.css">
    <link rel="stylesheet" href="plugins/fontawesome-free/css/all.min.css">
    <link rel="stylesheet" href="plugins/datatables-bs4/css/dataTables.bootstrap4.min.css">
</head>
<body class="hold-transition sidebar-mini">
<div class="wrapper">
    <?php include 'includes/navbar.php'; ?>
    <div class="content-wrapper">
        <div class="content-header">
            <div class="container-fluid">
                <div class="row mb-2">
                    <div class="col-sm-6">
                        <h1 class="m-0">User Management</h1>
                    </div>
                </div>
            </div>
        </div>
        <div class="content">
            <div class="container-fluid">
                
                <?php if ($message): ?>
                <div class="alert alert-success alert-dismissible">
                    <button type="button" class="close" data-dismiss="alert">&times;</button>
                    <?php echo htmlspecialchars($message); ?>
                </div>
                <?php endif; ?>
                
                <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible">
                    <button type="button" class="close" data-dismiss="alert">&times;</button>
                    <?php echo htmlspecialchars($error); ?>
                </div>
                <?php endif; ?>
                <!-- Add User Card -->
                <div class="card card-primary">
                    <div class="card-header">
                        <h3 class="card-title"><i class="fas fa-user-plus mr-2"></i>Add New User</h3>
                        <div class="card-tools">
                            <button type="button" class="btn btn-tool" data-card-widget="collapse"><i class="fas fa-minus"></i></button>
                        </div>
                    </div>
                    <form method="post">
                        <input type="hidden" name="action" value="add">
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label>Username</label>
                                        <input type="text" name="username" class="form-control" required>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label>Email</label>
                                        <input type="email" name="email" class="form-control">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label>Password</label>
                                        <input type="password" name="password" class="form-control" required>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label>Role</label>
                                        <select name="role" class="form-control">
                                            <option value="user">User</option>
                                            <option value="admin">Admin</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="card-footer">
                            <button type="submit" class="btn btn-primary"><i class="fas fa-plus mr-2"></i>Add User</button>
                        </div>
                    </form>
                </div>
                <!-- Users List -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title"><i class="fas fa-users mr-2"></i>Users</h3>
                    </div>
                    <div class="card-body">
                        <table id="usersTable" class="table table-bordered table-striped">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Username</th>
                                    <th>Email</th>
                                    <th>Role</th>
                                    <th>Status</th>
                                    <th>Created</th>
                                    <th>Last Login</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($users as $user): ?>
                                <tr>
                                    <td><?php echo $user['id']; ?></td>
                                    <td><?php echo htmlspecialchars($user['username']); ?></td>
                                    <td><?php echo htmlspecialchars($user['email'] ?? ''); ?></td>
                                    <td>
                                        <span class="badge badge-<?php echo $user['role'] === 'admin' ? 'danger' : 'info'; ?>">
                                            <?php echo htmlspecialchars($user['role']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge badge-<?php echo $user['is_active'] ? 'success' : 'secondary'; ?>">
                                            <?php echo $user['is_active'] ? 'Active' : 'Disabled'; ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($user['created_at'] ?? ''); ?></td>
                                    <td><?php echo htmlspecialchars($user['last_login'] ?? 'Never'); ?></td>
                                    <td>
                                        <!-- Toggle Status -->
                                        <form method="post" style="display:inline;">
                                            <input type="hidden" name="action" value="toggle">
                                            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-<?php echo $user['is_active'] ? 'warning' : 'success'; ?>" 
                                                    title="<?php echo $user['is_active'] ? 'Disable' : 'Enable'; ?>"
                                                    <?php echo $user['id'] === $_SESSION['user_id'] ? 'disabled' : ''; ?>>
                                                <i class="fas fa-<?php echo $user['is_active'] ? 'ban' : 'check'; ?>"></i>
                                            </button>
                                        </form>
                                        
                                        <!-- Reset Password -->
                                        <button type="button" class="btn btn-sm btn-info" data-toggle="modal" 
                                                data-target="#resetModal<?php echo $user['id']; ?>" title="Reset Password">
                                            <i class="fas fa-key"></i>
                                        </button>
                                        
                                        <!-- Delete -->
                                        <form method="post" style="display:inline;" 
                                              onsubmit="return confirm('Delete user <?php echo htmlspecialchars($user['username']); ?>?');">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-danger" title="Delete"
                                                    <?php echo $user['id'] === $_SESSION['user_id'] ? 'disabled' : ''; ?>>
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                        
                                        <!-- Reset Password Modal -->
                                        <div class="modal fade" id="resetModal<?php echo $user['id']; ?>">
                                            <div class="modal-dialog">
                                                <div class="modal-content">
                                                    <form method="post">
                                                        <input type="hidden" name="action" value="reset_password">
                                                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                        <div class="modal-header">
                                                            <h4 class="modal-title">Reset Password for <?php echo htmlspecialchars($user['username']); ?></h4>
                                                            <button type="button" class="close" data-dismiss="modal">&times;</button>
                                                        </div>
                                                        <div class="modal-body">
                                                            <div class="form-group">
                                                                <label>New Password</label>
                                                                <input type="password" name="new_password" class="form-control" required>
                                                            </div>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                                                            <button type="submit" class="btn btn-primary">Reset Password</button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
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
<script src="plugins/datatables/jquery.dataTables.min.js"></script>
<script src="plugins/datatables-bs4/js/dataTables.bootstrap4.min.js"></script>
<script src="dist/js/adminlte.min.js"></script>
<script>
$(function() {
    $('#usersTable').DataTable({
        "paging": true,
        "lengthChange": false,
        "searching": true,
        "ordering": true,
        "info": true,
        "autoWidth": false,
        "responsive": true
    });
});
</script>
</body>
</html>
