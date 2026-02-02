<?php
require_once 'includes/auth.php';
require_login();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LURE Health Status</title>
    
    <!-- AdminLTE CSS -->
    <link rel="stylesheet" href="dist/css/adminlte.min.css">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="plugins/fontawesome-free/css/all.min.css">
    <!-- Toastr -->
    <link rel="stylesheet" href="plugins/toastr/toastr.min.css">
    <!-- SweetAlert2 -->
    <link rel="stylesheet" href="plugins/sweetalert2-theme-bootstrap-4/bootstrap-4.min.css">
    
    <style>
        .health-ok { color: #28a745; }
        .health-warn { color: #ffc107; }
        .health-critical { color: #dc3545; }
        .status-card {
            text-align: center;
            padding: 20px;
        }
        .status-card h2 {
            font-size: 3rem;
            margin-bottom: 0;
        }
        .lure-card {
            margin-bottom: 20px;
        }
        .service-status {
            font-size: 1.2rem;
            margin: 5px 0;
        }
        .lure-actions {
            margin-top: 10px;
            padding-top: 10px;
            border-top: 1px solid #eee;
        }
    </style>
</head>
<body class="hold-transition sidebar-mini">
<div class="wrapper">
    
    <?php include 'includes/navbar.php'; ?>

    <!-- Content Wrapper -->
    <div class="content-wrapper">
        <div class="content-header">
            <div class="container-fluid">
                <div class="row mb-2">
                    <div class="col-sm-6">
                        <h1 class="m-0"><i class="fas fa-heartbeat text-danger"></i> Lure Health Status</h1>
                    </div>
                    <div class="col-sm-6">
			<div class="float-right mt-2">
    				<button class="btn btn-warning btn-sm mr-2" id="btn-clear-terminated" title="Remove terminated lures from list">
        			<i class="fas fa-broom"></i> Clear Terminated
    				</button>
    				<span class="text-muted"><i class="fas fa-clock"></i> Health checks run every 10 minutes</span>
			</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main content -->
        <section class="content">
            <div class="container-fluid">
                
                <!-- Summary Cards -->
                <div class="row">
                    <div class="col-lg-3 col-6">
                        <div class="small-box bg-success">
                            <div class="inner">
                                <h3 id="count-online">-</h3>
                                <p>Online</p>
                            </div>
                            <div class="icon">
                                <i class="fas fa-check-circle"></i>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-6">
                        <div class="small-box bg-warning">
                            <div class="inner">
                                <h3 id="count-degraded">-</h3>
                                <p>Degraded</p>
                            </div>
                            <div class="icon">
                                <i class="fas fa-exclamation-triangle"></i>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-6">
                        <div class="small-box bg-danger">
                            <div class="inner">
                                <h3 id="count-offline">-</h3>
                                <p>Offline</p>
                            </div>
                            <div class="icon">
                                <i class="fas fa-times-circle"></i>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-6">
                        <div class="small-box bg-info">
                            <div class="inner">
                                <h3 id="count-total">-</h3>
                                <p>Total Lures</p>
                            </div>
                            <div class="icon">
                                <i class="fas fa-server"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- EM-Lure Card -->
                <div class="row">
                    <div class="col-12">
                        <div class="card card-primary card-outline" id="em-card">
                            <div class="card-header">
                                <h3 class="card-title">
                                    <i class="fas fa-server text-primary"></i>
                                    <strong>EM-Lure (Enterprise Manager)</strong>
                                </h3>
                                <div class="card-tools">
                                    <span id="em-status-badge" class="badge badge-secondary">Loading...</span>
                                </div>
                            </div>
                            <div class="card-body" id="em-card-body">
                                <div class="text-center">
                                    <i class="fas fa-spinner fa-spin"></i> Loading...
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Lure Cards -->
                <div class="row" id="lure-cards">
                    <div class="col-12 text-center">
                        <i class="fas fa-spinner fa-spin fa-3x"></i>
                        <p>Loading health status...</p>
                    </div>
                </div>

            </div>
        </section>
    </div>

    <!-- Footer -->
    <footer class="main-footer">
        <strong>LURE Dashboard &copy; 2026</strong>
        <div class="float-right d-none d-sm-inline-block">
            Last checked: <span id="last-check">Never</span>
        </div>
    </footer>
</div>

<!-- jQuery -->
<script src="plugins/jquery/jquery.min.js"></script>
<!-- Bootstrap -->
<script src="plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
<!-- AdminLTE -->
<script src="dist/js/adminlte.min.js"></script>
<!-- Toastr -->
<script src="plugins/toastr/toastr.min.js"></script>
<!-- SweetAlert2 -->
<script src="plugins/sweetalert2/sweetalert2.min.js"></script>

<script>
// Toastr config
toastr.options = { positionClass: 'toast-top-right', timeOut: 3000 };

function rebootLure(hostname) {
    Swal.fire({
        title: 'Reboot ' + hostname + '?',
        text: 'The lure will be unavailable for 1-2 minutes.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ffc107',
        confirmButtonText: 'Yes, reboot it'
    }).then((result) => {
        if (result.isConfirmed) {
            $.post('../api/cast-reboot.php', { hostname: hostname })
                .done(function(r) {
                    if (r.success) {
                        toastr.success(hostname + ' is rebooting');
                        setTimeout(loadHealthStatus, 2000);
                    } else {
                        toastr.error(r.error || 'Reboot failed');
                    }
                })
                .fail(function() {
                    toastr.error('Request failed');
                });
        }
    });
}

function terminateLure(hostname) {
    Swal.fire({
        title: 'Terminate ' + hostname + '?',
        html: '<strong class="text-danger">This will permanently destroy the instance!</strong><br>The EIP will be released back to the pool.',
        icon: 'error',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        confirmButtonText: 'Yes, terminate it',
        input: 'text',
        inputPlaceholder: 'Type hostname to confirm',
        inputValidator: (value) => {
            if (value !== hostname) {
                return 'Hostname does not match';
            }
        }
    }).then((result) => {
        if (result.isConfirmed) {
            $.post('../api/cast-terminate.php', { hostname: hostname })
                .done(function(r) {
                    if (r.success) {
                        toastr.success(hostname + ' terminated');
                        setTimeout(loadHealthStatus, 2000);
                    } else {
                        toastr.error(r.error || 'Terminate failed');
                    }
                })
                .fail(function() {
                    toastr.error('Request failed');
                });
        }
    });
}

function loadHealthStatus() {
    fetch('../api/health.php')
        .then(response => response.json())
        .then(data => {
            // Update summary counts
            document.getElementById('count-online').textContent = data.summary.online;
            document.getElementById('count-degraded').textContent = data.summary.degraded;
            document.getElementById('count-offline').textContent = data.summary.offline;
            document.getElementById('count-total').textContent = data.summary.total;
            document.getElementById('last-check').textContent = data.checked_at;
            
            // Update EM card
            if (data.em) {
                const em = data.em;
                const statusClass = em.status === 'online' ? 'success' 
                    : em.status === 'degraded' ? 'warning' : 'danger';
                
                document.getElementById('em-card').className = `card card-${statusClass} card-outline`;
                document.getElementById('em-status-badge').className = `badge badge-${statusClass}`;
                document.getElementById('em-status-badge').textContent = em.status.toUpperCase();
                
                const checkIcon = (val) => val == 1 
                    ? '<i class="fas fa-check-circle health-ok"></i>' 
                    : '<i class="fas fa-times-circle health-critical"></i>';
                
                const diskClass = em.disk_percent > 90 ? 'health-critical' 
                    : em.disk_percent > 70 ? 'health-warn' : 'health-ok';
                const memClass = em.memory_percent > 90 ? 'health-critical' 
                    : em.memory_percent > 70 ? 'health-warn' : 'health-ok';
                const loadClass = parseFloat(em.load) > 2.0 ? 'health-critical' 
                    : parseFloat(em.load) > 1.5 ? 'health-warn' : 'health-ok';
                
                document.getElementById('em-card-body').innerHTML = `
                    <div class="row">
                        <div class="col-md-3">
                            <p class="service-status">${checkIcon(em.ssh_ok)} SSH</p>
                            <p class="service-status">${checkIcon(em.rsyslog_ok)} Rsyslog</p>
                            <p class="service-status">${checkIcon(em.nginx_ok)} Nginx</p>
                        </div>
                        <div class="col-md-3">
                            <p class="service-status">
                                <i class="fas fa-hdd ${diskClass}"></i> 
                                Disk: <strong class="${diskClass}">${em.disk_percent}%</strong>
                            </p>
                            <p class="service-status">
                                <i class="fas fa-memory ${memClass}"></i> 
                                Memory: <strong class="${memClass}">${em.memory_percent}%</strong>
                            </p>
                            <p class="service-status">
                                <i class="fas fa-tachometer-alt ${loadClass}"></i> 
                                Load: <strong class="${loadClass}">${em.load || 'N/A'}</strong>
                            </p>
                        </div>
                        <div class="col-md-3">
                            <p class="service-status">
                                <i class="fas fa-clock"></i> 
                                ${em.uptime || 'N/A'}
                            </p>
                        </div>
                        <div class="col-md-3">
                            <p class="text-muted"><small><i class="fas fa-shield-alt"></i> ${em.ssl_expiry}</small></p>
                            ${em.error_message ? `<p class="text-danger"><small><i class="fas fa-exclamation-circle"></i> ${em.error_message}</small></p>` : ''}
                        </div>
                    </div>
                `;
            } else {
                document.getElementById('em-card-body').innerHTML = '<div class="alert alert-warning">No EM health data available</div>';
            }
            
            // Build lure cards
            const container = document.getElementById('lure-cards');
            container.innerHTML = '';
            
            data.lures.forEach(lure => {
                const statusClass = lure.status === 'online' ? 'success' 
                    : lure.status === 'degraded' ? 'warning' : 'danger';
                const statusIcon = lure.status === 'online' ? 'check-circle' 
                    : lure.status === 'degraded' ? 'exclamation-triangle' : 'times-circle';
                
                const checkIcon = (val) => val == 1 
                    ? '<i class="fas fa-check-circle health-ok"></i>' 
                    : '<i class="fas fa-times-circle health-critical"></i>';
                
                const diskClass = lure.disk_percent > 90 ? 'health-critical' 
                    : lure.disk_percent > 70 ? 'health-warn' : 'health-ok';
                const memClass = lure.memory_percent > 90 ? 'health-critical' 
                    : lure.memory_percent > 70 ? 'health-warn' : 'health-ok';
                const loadClass = parseFloat(lure.load) > 2.0 ? 'health-critical' 
                    : parseFloat(lure.load) > 1.5 ? 'health-warn' : 'health-ok';
                
                const lastLog = lure.last_log_received 
                    ? new Date(lure.last_log_received).toLocaleString() 
                    : 'Never';
                
                const isTerminated = lure.status === 'terminated';
                
                const card = document.createElement('div');
                card.className = 'col-lg-4 col-md-6';
                card.innerHTML = `
                    <div class="card lure-card card-${statusClass} card-outline">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="fas fa-${statusIcon} text-${statusClass}"></i>
                                <strong>${lure.lure_id}</strong>
                            </h3>
                            <div class="card-tools">
                                <span class="badge badge-${statusClass}">${lure.status.toUpperCase()}</span>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-6">
                                    <p class="service-status">${checkIcon(lure.ssh_ok)} SSH</p>
                                    <p class="service-status">${checkIcon(lure.rsyslog_ok)} Rsyslog</p>
                                    <p class="service-status">${checkIcon(lure.nftables_ok)} NFTables</p>
                                </div>
                                <div class="col-6">
                                    <p class="service-status">
                                        <i class="fas fa-hdd ${diskClass}"></i> 
                                        Disk: <strong class="${diskClass}">${lure.disk_percent || '-'}%</strong>
                                    </p>
                                    <p class="service-status">
                                        <i class="fas fa-memory ${memClass}"></i> 
                                        Memory: <strong class="${memClass}">${lure.memory_percent || '-'}%</strong>
                                    </p>
                                    <p class="service-status">
                                        <i class="fas fa-tachometer-alt ${loadClass}"></i> 
                                        Load: <strong class="${loadClass}">${lure.load || 'N/A'}</strong>
                                    </p>
                                </div>
                            </div>
                            <hr>
                            <small class="text-muted">
                                <i class="fas fa-clock"></i> ${lure.uptime || 'N/A'}<br>
                                <i class="fas fa-network-wired"></i> ${lure.ip_address}<br>
                                <i class="fas fa-crosshairs"></i> Last Snare: ${lastLog}
                            </small>
                            ${lure.error_message ? `<br><small class="text-danger"><i class="fas fa-exclamation-circle"></i> ${lure.error_message}</small>` : ''}
                            
                            ${!isTerminated ? `
                            <div class="lure-actions text-right">
                                <button class="btn btn-warning btn-sm" onclick="rebootLure('${lure.hostname}')" title="Reboot">
                                    <i class="fas fa-sync"></i> Reboot
                                </button>
                                <button class="btn btn-danger btn-sm" onclick="terminateLure('${lure.hostname}')" title="Terminate">
                                    <i class="fas fa-trash"></i> Terminate
                                </button>
                            </div>
                            ` : ''}
                        </div>
                    </div>
                `;
                container.appendChild(card);
            });
        })
        .catch(error => {
            console.error('Error loading health:', error);
            document.getElementById('lure-cards').innerHTML = 
                '<div class="col-12"><div class="alert alert-danger">Error loading health status</div></div>';
        });
}

// Initial load
loadHealthStatus();

// Auto-refresh every 60 seconds
// Clear terminated lures
$('#btn-clear-terminated').click(function() {
    Swal.fire({
        title: 'Clear terminated lures?',
        text: 'This will remove all terminated lures from the list.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Yes, clear them'
    }).then((result) => {
        if (result.isConfirmed) {
            $.post('../api/cast-clear-terminated.php')
                .done(function(r) {
                    if (r.success) {
                        toastr.success('Removed ' + r.count + ' terminated lure(s)');
                        loadHealthStatus();
                    } else {
                        toastr.error(r.error || 'Clear failed');
                    }
                })
                .fail(function() {
                    toastr.error('Request failed');
                });
        }
    });
});
setInterval(loadHealthStatus, 60000);
</script>

</body>
</html>
