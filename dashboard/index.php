<?php
require_once 'includes/auth.php';
require_login();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LURE Log Dashboard</title>
    
    <!-- AdminLTE CSS -->
    <link rel="stylesheet" href="dist/css/adminlte.min.css">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="plugins/fontawesome-free/css/all.min.css">
    
    <style>
        .chart-container {
            position: relative;
            height: 300px;
        }
        .ip-badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 3px;
            font-family: monospace;
            font-size: 0.9em;
            background-color: #e9ecef;
        }
    </style>
</head>
<body class="hold-transition sidebar-mini">
<div class="wrapper">
    
    <!-- Navbar -->
    <!-- Sidebar -->

	<?php include 'includes/navbar.php'; ?>

    <!-- Content Wrapper -->
    <div class="content-wrapper">
        <div class="content-header">
            <div class="container-fluid">
                <div class="row mb-2">
                    <div class="col-sm-6">
                        <h1 class="m-0">Dashboard Overview</h1>
                    </div>
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-right">
                            <li class="breadcrumb-item"><a href="../">Home</a></li>
                            <li class="breadcrumb-item active">Dashboard</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main content -->
        <section class="content">
            <div class="container-fluid">
                
                <!-- Stats Cards Row -->
                <div class="row">
                    <!-- Total Attacks -->
                    <div class="col-lg-3 col-6">
                        <div class="small-box bg-info">
                            <div class="inner">
                                <h3 id="total-attacks">0</h3>
                                <p>Total Snared</p>
                            </div>
                            <div class="icon">
                                <i class="fas fa-database"></i>
                            </div>
                            <a href="#" class="small-box-footer">
                                More info <i class="fas fa-arrow-circle-right"></i>
                            </a>
                        </div>
                    </div>

                    <!-- Unique IPs -->
                    <div class="col-lg-3 col-6">
                        <div class="small-box bg-success">
                            <div class="inner">
                                <h3 id="unique-ips">0</h3>
                                <p>Unique Source IPs</p>
                            </div>
                            <div class="icon">
                                <i class="fas fa-network-wired"></i>
                            </div>
                            <a href="#" class="small-box-footer">
                                View details <i class="fas fa-arrow-circle-right"></i>
                            </a>
                        </div>
                    </div>

                    <!-- Active Lures -->
                    <!-- Active Lures -->
                    <div class="col-lg-3 col-6">
                        <div class="small-box bg-warning">
                            <div class="inner">
                                <h3 id="active-lures">0</h3>
                                <p>Active Lures (24h)</p>
                            </div>
                            <div class="icon">
                                <i class="fas fa-network-wired"></i>
                            </div>
                            <a href="#" class="small-box-footer">
                                Lures reporting <i class="fas fa-arrow-circle-right"></i>
                            </a>
                        </div>
                    </div>

                    <!-- Last 24h -->
                    <div class="col-lg-3 col-6">
                        <div class="small-box bg-danger">
                            <div class="inner">
                                <h3 id="attacks-24h">0</h3>
                                <p>Snared (Last 24h)</p>
                            </div>
                            <div class="icon">
                                <i class="fas fa-chart-line"></i>
                            </div>
                            <a href="#" class="small-box-footer">
                                Trend analysis <i class="fas fa-arrow-circle-right"></i>
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Charts Row 1 -->
                <div class="row">
                    <!-- Attack Timeline -->
                    <div class="col-lg-6">
                        <div class="card">
                            <div class="card-header border-0">
                                <h3 class="card-title">Snared Timeline (Last 7 Days)</h3>
                            </div>
                            <div class="card-body">
                                <div class="chart-container">
                                    <canvas id="timelineChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Lure Activity -->
                    <div class="col-lg-6">
                        <div class="card">
                            <div class="card-header border-0">
                                <h3 class="card-title">Lure Activity (7 Days)</h3>
                            </div>
                            <div class="card-body">
                                <div class="chart-container">
                                    <canvas id="lureActivityChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Charts Row 2 -->
                <div class="row">
                    <!-- Top Attackers -->
                    <div class="col-lg-6">
                        <div class="card">
                            <div class="card-header border-0">
                                <h3 class="card-title">Top 10 Source IPs</h3>
                            </div>
                            <div class="card-body">
                                <div class="chart-container">
                                    <canvas id="topAttackersChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Most Targeted Ports -->
                    <div class="col-lg-6">
                        <div class="card">
                            <div class="card-header border-0">
                                <h3 class="card-title">Most Targeted Ports</h3>
                            </div>
                            <div class="card-body">
                                <div class="chart-container">
                                    <canvas id="portsChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tables Row -->
                <div class="row">
                    <!-- Top Source IPs Table -->
                    <div class="col-lg-6">
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">Top Source IPs</h3>
                            </div>
                            <div class="card-body p-0">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>IP Address</th>
                                            <th>Snared</th>
                                            <th>Ports</th>
                                        </tr>
                                    </thead>
                                    <tbody id="top-sources-table">
                                        <tr><td colspan="3" class="text-center">Loading...</td></tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Recent Activity -->
                    <div class="col-lg-6">
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">Recent Activity</h3>
                            </div>
                            <div class="card-body p-0">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Time</th>
                                            <th>Source IP</th>
                                            <th>Port</th>
                                            <th>Proto</th>
                                            <th>Lure</th>
                                        </tr>
                                    </thead>
                                    <tbody id="recent-activity-table">
                                        <tr><td colspan="5" class="text-center">Loading...</td></tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </section>
    </div>

    <!-- Footer -->
    <!-- Footer -->
    <footer class="main-footer">
        <strong>LURE Dashboard &copy; 2026</strong>
        <div class="float-right d-none d-sm-inline-block">
            Last updated: <span id="last-update">Never</span>
        </div>
    </footer>
</div>

<!-- jQuery -->
<script src="plugins/jquery/jquery.min.js"></script>
<!-- Bootstrap -->
<script src="plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
<!-- AdminLTE -->
<script src="dist/js/adminlte.min.js"></script>
<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

<script>
let timelineChart, protocolChart, attackersChart, portsChart;

// Load all dashboard data
function loadDashboard() {
    loadStats();
    loadTimeline();
    loadLureActivity();
    loadTopAttackers();
    loadTargetedPorts();
    loadTopSourcesTable();
    loadRecentActivity();
    
    document.getElementById('last-update').textContent = new Date().toLocaleString();
}

// Load stats cards
function loadStats() {
    fetch('../api/dashboard-stats.php')
        .then(response => response.json())
        .then(data => {
            document.getElementById('total-attacks').textContent = data.total_attacks.toLocaleString();
            document.getElementById('unique-ips').textContent = data.unique_ips.toLocaleString();
            document.getElementById('attacks-24h').textContent = '+' + data.attacks_24h.toLocaleString();
        })
        .catch(error => console.error('Error loading stats:', error));

    // Load active lures
    fetch('../api/active-lures.php')
        .then(response => response.json())
        .then(data => {
            document.getElementById('active-lures').textContent = data.active_lures;
        })
        .catch(error => console.error('Error loading active lures:', error));
}

// Load timeline chart
function loadTimeline() {
    fetch('../api/timeline-7days.php')
        .then(response => response.json())
        .then(data => {
            const ctx = document.getElementById('timelineChart').getContext('2d');
            
            if (timelineChart) {
                timelineChart.destroy();
            }
            
            timelineChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: data.dates,
                    datasets: [{
                        label: 'TCP',
                        data: data.tcp,
                        borderColor: 'rgb(75, 192, 192)',
                        backgroundColor: 'rgba(75, 192, 192, 0.1)',
                        tension: 0.4
                    }, {
                        label: 'UDP',
                        data: data.udp,
                        borderColor: 'rgb(255, 159, 64)',
                        backgroundColor: 'rgba(255, 159, 64, 0.1)',
                        tension: 0.4
                    }, {
                        label: 'ICMP',
                        data: data.icmp,
                        borderColor: 'rgb(153, 102, 255)',
                        backgroundColor: 'rgba(153, 102, 255, 0.1)',
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'top' }
                    },
                    scales: {
                        y: { beginAtZero: true }
                    }
                }
            });
        })
        .catch(error => console.error('Error loading timeline:', error));
}

// Load lure activity chart
function loadLureActivity() {
    fetch('../api/lure-activity.php')
        .then(response => response.json())
        .then(data => {
            const labels = data.map(l => l.lure_host);
            const values = data.map(l => l.snares);
            
            // Generate colors for each lure
            const colors = [
                'rgba(54, 162, 235, 0.8)',
                'rgba(255, 99, 132, 0.8)',
                'rgba(75, 192, 192, 0.8)',
                'rgba(255, 159, 64, 0.8)',
                'rgba(153, 102, 255, 0.8)',
                'rgba(255, 205, 86, 0.8)',
                'rgba(201, 203, 207, 0.8)',
                'rgba(100, 149, 237, 0.8)',
                'rgba(255, 140, 0, 0.8)',
                'rgba(46, 204, 113, 0.8)'
            ];
            
            const ctx = document.getElementById('lureActivityChart').getContext('2d');
            
           // if (lureActivityChart) {
           //     lureActivityChart.destroy();
           // }
            
            lureActivityChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Snares',
                        data: values,
                        backgroundColor: colors.slice(0, data.length)
                    }]
                },
                options: {
                    indexAxis: 'y',
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false }
                    },
                    scales: {
                        x: { beginAtZero: true }
                    }
                }
            });
        })
        .catch(error => console.error('Error loading lure activity:', error));
}

// Load top attackers chart
function loadTopAttackers() {
    fetch('../api/top-attackers-chart.php')
        .then(response => response.json())
        .then(data => {
            const labels = data.map(a => a.src_ip);
            const values = data.map(a => a.count);
            
            const ctx = document.getElementById('topAttackersChart').getContext('2d');
            
            if (attackersChart) {
                attackersChart.destroy();
            }
            
            attackersChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Snared Count',
                        data: values,
                        backgroundColor: 'rgba(54, 162, 235, 0.8)'
                    }]
                },
                options: {
                    indexAxis: 'y',
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false }
                    },
                    scales: {
                        x: { beginAtZero: true }
                    }
                }
            });
        })
        .catch(error => console.error('Error loading top attackers:', error));
}

// Load targeted ports chart
function loadTargetedPorts() {
    fetch('../api/targeted-ports.php')
        .then(response => response.json())
        .then(data => {
            const labels = data.map(p => p.port + (p.service !== 'Other' && p.service !== 'Others' ? ' (' + p.service + ')' : ''));
            const values = data.map(p => p.count);
            const colors = [
                'rgb(255, 99, 132)',
                'rgb(54, 162, 235)',
                'rgb(255, 205, 86)',
                'rgb(75, 192, 192)',
                'rgb(153, 102, 255)',
                'rgb(255, 159, 64)',
                'rgb(201, 203, 207)',
                'rgb(100, 149, 237)'
            ];
            
            const ctx = document.getElementById('portsChart').getContext('2d');
            
            if (portsChart) {
                portsChart.destroy();
            }
            
            portsChart = new Chart(ctx, {
                type: 'pie',
                data: {
                    labels: labels,
                    datasets: [{
                        data: values,
                        backgroundColor: colors
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'right' }
                    }
                }
            });
        })
        .catch(error => console.error('Error loading ports:', error));
}

// Load top sources table
function loadTopSourcesTable() {
    fetch('../api/top-sources-table.php?limit=10')
        .then(response => response.json())
        .then(data => {
            const tbody = document.getElementById('top-sources-table');
            tbody.innerHTML = '';
            
            data.forEach(source => {
                const row = tbody.insertRow();
                row.innerHTML = `
                    <td><span class="ip-badge">${source.src_ip}</span></td>
                    <td><span class="badge badge-danger">${source.attacks.toLocaleString()}</span></td>
                    <td>${source.ports_targeted}</td>
                `;
            });
        })
        .catch(error => console.error('Error loading top sources:', error));
}

// Load recent activity
function loadRecentActivity() {
    fetch('../api/recent-activity.php?limit=12')
        .then(response => response.json())
        .then(data => {
            const tbody = document.getElementById('recent-activity-table');
            tbody.innerHTML = '';
            
            data.forEach(activity => {
                const row = tbody.insertRow();
                const time = new Date(activity.syslog_ts);
                const timeStr = time.toLocaleTimeString();
                
                row.innerHTML = `
                    <td><small>${timeStr}</small></td>
                    <td><code>${activity.src_ip}</code></td>
                    <td><span class="badge badge-info">${activity.port}</span></td>
                    <td><small>${activity.proto}</small></td>
                    <td><span class="badge badge-secondary">${activity.lure_host}</span></td>
                `;
            });
        })
        .catch(error => console.error('Error loading recent activity:', error));
}

// Initial load
loadDashboard();

// Auto-refresh every 60 seconds
setInterval(loadDashboard, 60000);
</script>

</body>
</html>
