<?php
// Determine which page is active
$current_page = basename($_SERVER['PHP_SELF']);
$user = current_user();
$is_admin = $user && $user['role'] === 'admin';
?>
<!-- Navbar -->
<nav class="main-header navbar navbar-expand navbar-white navbar-light">
    <ul class="navbar-nav">
        <li class="nav-item">
            <a class="nav-link" data-widget="pushmenu" href="#" role="button"><i class="fas fa-bars"></i></a>
        </li>
        <li class="nav-item d-none d-sm-inline-block">
            <a href="index.php" class="nav-link">LURE Dashboard</a>
        </li>
    </ul>
    <ul class="navbar-nav ml-auto">
        <?php if ($user): ?>
        <li class="nav-item">
            <a href="profile.php" class="nav-link">
                <i class="fas fa-user mr-1"></i><?php echo htmlspecialchars($user['username']); ?>
                <span class="badge badge-<?php echo $is_admin ? 'danger' : 'info'; ?> ml-1"><?php echo htmlspecialchars($user['role']); ?></span>
            </a>
        </li>
        <li class="nav-item">
            <a href="logout.php" class="nav-link text-danger">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </li>
        <?php endif; ?>
    </ul>
</nav>

<!-- Sidebar -->
<aside class="main-sidebar sidebar-dark-primary elevation-4">
    <a href="index.php" class="brand-link pl-3">
        <span class="brand-text font-weight-light ml-2">LURE</span>
    </a>
    <div class="sidebar">
        <nav class="mt-2">
            <ul class="nav nav-pills nav-sidebar flex-column">
                <li class="nav-item">
                    <a href="index.php" class="nav-link <?php echo ($current_page == 'index.php') ? 'active' : ''; ?>">
                        <i class="nav-icon fas fa-tachometer-alt"></i>
                        <p>Dashboard</p>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="enrichment.php" class="nav-link <?php echo ($current_page == 'enrichment.php') ? 'active' : ''; ?>">
                        <i class="nav-icon fas fa-search-plus"></i>
                        <p>Enrichment</p>
                    </a>
                </li>

                <?php if ($is_admin): ?>
                <li class="nav-header">ADMINISTRATION</li>
                <li class="nav-item">
                    <a href="cast.php" class="nav-link <?php echo ($current_page == 'cast.php') ? 'active' : ''; ?>">
                        <i class="nav-icon fas fa-project-diagram"></i>
                        <p>Cast</p>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="health.php" class="nav-link <?php echo ($current_page == 'health.php') ? 'active' : ''; ?>">
                        <i class="nav-icon fas fa-heartbeat"></i>
                        <p>Health</p>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="lists.php" class="nav-link <?php echo ($current_page == 'lists.php') ? 'active' : ''; ?>">
                        <i class="nav-icon fas fa-list"></i>
                        <p>Lists</p>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="configuration.php" class="nav-link <?php echo ($current_page == 'configuration.php') ? 'active' : ''; ?>">
                        <i class="nav-icon fas fa-cogs"></i>
                        <p>Configuration</p>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="certificates.php" class="nav-link <?php echo ($current_page == 'certificates.php') ? 'active' : ''; ?>">
                        <i class="nav-icon fas fa-shield-alt"></i>
                        <p>API &amp; Certificates</p>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="users.php" class="nav-link <?php echo ($current_page == 'users.php') ? 'active' : ''; ?>">
                        <i class="nav-icon fas fa-users"></i>
                        <p>Users</p>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="sql-query.php" class="nav-link <?php echo ($current_page == 'sql-query.php') ? 'active' : ''; ?>">
                        <i class="nav-icon fas fa-terminal"></i>
                        <p>SQL Query</p>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="audit.php" class="nav-link <?php echo ($current_page == 'audit.php') ? 'active' : ''; ?>">
                        <i class="nav-icon fas fa-history"></i>
                        <p>Audit Log</p>
                    </a>
                </li>
                <?php endif; ?>
            </ul>
        </nav>
    </div>
</aside>
