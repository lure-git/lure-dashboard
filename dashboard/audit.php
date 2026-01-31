<?php
require_once 'includes/auth.php';
require_admin();

$filter_user = $_GET['user'] ?? '';
$filter_action = $_GET['action'] ?? '';
$filter_days = (int)($_GET['days'] ?? 7);

$where = [];
$params = [];

if ($filter_user) {
    $where[] = 'username LIKE :user';
    $params[':user'] = "%$filter_user%";
}

if ($filter_action) {
    $where[] = 'action = :action';
    $params[':action'] = $filter_action;
}

if ($filter_days > 0) {
    $where[] = "timestamp >= datetime('now', '-$filter_days days')";
}

$where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
$logs = db_fetch_all("SELECT * FROM audit_log $where_sql ORDER BY id DESC LIMIT 500", $params);

$actions = db_fetch_all('SELECT DISTINCT action FROM audit_log ORDER BY action');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LURE | Audit Log</title>
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
                        <h1 class="m-0">Audit Log</h1>
                    </div>
                </div>
            </div>
        </div>

        <div class="content">
            <div class="container-fluid">

                <!-- Filters -->
                <div class="card card-outline card-info">
                    <div class="card-header">
                        <h3 class="card-title"><i class="fas fa-filter mr-2"></i>Filters</h3>
                        <div class="card-tools">
                            <button type="button" class="btn btn-tool" data-card-widget="collapse"><i class="fas fa-minus"></i></button>
                        </div>
                    </div>
                    <div class="card-body">
                        <form method="get" class="form-inline">
                            <div class="form-group mr-3">
                                <label class="mr-2">User:</label>
                                <input type="text" name="user" class="form-control" value="<?php echo htmlspecialchars($filter_user); ?>" placeholder="Username">
                            </div>
                            <div class="form-group mr-3">
                                <label class="mr-2">Action:</label>
                                <select name="action" class="form-control">
                                    <option value="">All Actions</option>
                                    <?php foreach ($actions as $a): ?>
                                    <option value="<?php echo htmlspecialchars($a['action']); ?>" <?php echo $filter_action === $a['action'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($a['action']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group mr-3">
                                <label class="mr-2">Days:</label>
                                <select name="days" class="form-control">
                                    <option value="1" <?php echo $filter_days === 1 ? 'selected' : ''; ?>>Last 24 hours</option>
                                    <option value="7" <?php echo $filter_days === 7 ? 'selected' : ''; ?>>Last 7 days</option>
                                    <option value="30" <?php echo $filter_days === 30 ? 'selected' : ''; ?>>Last 30 days</option>
                                    <option value="90" <?php echo $filter_days === 90 ? 'selected' : ''; ?>>Last 90 days</option>
                                    <option value="0" <?php echo $filter_days === 0 ? 'selected' : ''; ?>>All time</option>
                                </select>
                            </div>
                            <button type="submit" class="btn btn-info"><i class="fas fa-search mr-2"></i>Filter</button>
                            <a href="audit.php" class="btn btn-secondary ml-2"><i class="fas fa-times mr-2"></i>Clear</a>
                        </form>
                    </div>
                </div>

                <!-- Audit Log Table -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title"><i class="fas fa-history mr-2"></i>Audit Entries (<?php echo count($logs); ?>)</h3>
                    </div>
                    <div class="card-body">
                        <table id="auditTable" class="table table-bordered table-striped table-sm">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Timestamp</th>
                                    <th>User</th>
                                    <th>Action</th>
                                    <th>Target</th>
                                    <th>Details</th>
                                    <th>IP Address</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($logs as $log): ?>
                                <tr>
                                    <td><?php echo $log['id']; ?></td>
                                    <td><?php echo htmlspecialchars($log['timestamp']); ?></td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($log['username'] ?? 'anonymous'); ?></strong>
                                    </td>
                                    <td>
                                        <?php
                                        $action_colors = [
                                            'login' => 'success',
                                            'logout' => 'secondary',
                                            'user_create' => 'primary',
                                            'user_delete' => 'danger',
                                            'user_toggle' => 'warning',
                                            'password_reset' => 'info',
                                            'password_change' => 'info',
                                            'cert_issue' => 'success',
                                            'cert_revoke' => 'danger',
                                            'cert_download' => 'primary',
                                        ];
                                        $color = $action_colors[$log['action']] ?? 'secondary';
                                        ?>
                                        <span class="badge badge-<?php echo $color; ?>">
                                            <?php echo htmlspecialchars($log['action']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($log['target_type']): ?>
                                        <small class="text-muted"><?php echo htmlspecialchars($log['target_type']); ?>:</small>
                                        <?php echo htmlspecialchars($log['target_id']); ?>
                                        <?php endif; ?>
                                    </td>
                                    <td><small><?php echo htmlspecialchars($log['details'] ?? ''); ?></small></td>
                                    <td><code><?php echo htmlspecialchars($log['ip_address'] ?? ''); ?></code></td>
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
    $('#auditTable').DataTable({
        "paging": true,
        "pageLength": 25,
        "lengthChange": true,
        "searching": true,
        "ordering": true,
        "order": [[0, "desc"]],
        "info": true,
        "autoWidth": false,
        "responsive": true
    });
});
</script>
</body>
</html>
