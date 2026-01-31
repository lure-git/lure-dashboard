<?php
require_once 'includes/auth.php';
require_admin();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SQL Query - LURE Dashboard</title>
    
    <!-- AdminLTE CSS -->
    <link rel="stylesheet" href="dist/css/adminlte.min.css">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="plugins/fontawesome-free/css/all.min.css">
    
    <style>
        #query-editor {
            font-family: 'Courier New', monospace;
            font-size: 14px;
            min-height: 200px;
            border: 1px solid #ced4da;
            border-radius: 4px;
            padding: 10px;
        }
        .results-table {
            overflow-x: auto;
            max-height: 600px;
        }
        .results-table table {
            font-size: 12px;
        }
        .query-example {
            background: #f8f9fa;
            padding: 10px;
            border-left: 3px solid #007bff;
            margin: 10px 0;
            cursor: pointer;
        }
        .query-example:hover {
            background: #e9ecef;
        }
        .query-example code {
            display: block;
            color: #495057;
        }
        .error-message {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
            padding: 12px;
            border-radius: 4px;
            margin: 10px 0;
        }
        .success-message {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
            padding: 12px;
            border-radius: 4px;
            margin: 10px 0;
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
                        <h1 class="m-0">SQL Query Tool</h1>
                    </div>
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-right">
                            <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
                            <li class="breadcrumb-item active">SQL Query</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main content -->
        <section class="content">
            <div class="container-fluid">
                
                <!-- Query Editor -->
                <div class="row">
                    <div class="col-md-12">
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title"><i class="fas fa-code"></i> Query Editor</h3>
                            </div>
                            <div class="card-body">
                                <textarea id="query-editor" class="form-control" placeholder="Enter your SQL query here...">SELECT * FROM lure_logs LIMIT 10;</textarea>
                                
                                <div class="mt-3">
                                    <button id="run-query" class="btn btn-primary">
                                        <i class="fas fa-play"></i> Run Query
                                    </button>
                                    <button id="clear-query" class="btn btn-secondary">
                                        <i class="fas fa-eraser"></i> Clear
                                    </button>
                                    <button id="export-csv" class="btn btn-success" style="display:none;">
                                        <i class="fas fa-download"></i> Export CSV
                                    </button>
                                </div>
                                
                                <div id="query-status" class="mt-3"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Query Results -->
                <div class="row">
                    <div class="col-md-12">
                        <div class="card" id="results-card" style="display:none;">
                            <div class="card-header">
                                <h3 class="card-title"><i class="fas fa-table"></i> Query Results</h3>
                                <div class="card-tools">
                                    <span id="row-count" class="badge badge-primary"></span>
                                </div>
                            </div>
                            <div class="card-body p-0 results-table">
                                <table class="table table-striped table-hover" id="results-table">
                                    <thead id="results-header"></thead>
                                    <tbody id="results-body"></tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Example Queries -->
                <div class="row">
                    <div class="col-md-12">
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title"><i class="fas fa-lightbulb"></i> Example Queries (Click to Load)</h3>
                            </div>
                            <div class="card-body">
                                <div class="query-example" data-query="SELECT * FROM lure_logs LIMIT 10;">
                                    <strong>Basic Select</strong>
                                    <code>SELECT * FROM lure_logs LIMIT 10;</code>
                                </div>
                                
                                <div class="query-example" data-query="SELECT src_ip, COUNT(*) as attempts, COUNT(DISTINCT dpt) as ports FROM lure_logs GROUP BY src_ip ORDER BY attempts DESC LIMIT 20;">
                                    <strong>Top Attackers</strong>
                                    <code>SELECT src_ip, COUNT(*) as attempts, COUNT(DISTINCT dpt) as ports FROM lure_logs GROUP BY src_ip ORDER BY attempts DESC LIMIT 20;</code>
                                </div>
                                
                                <div class="query-example" data-query="SELECT dpt, COUNT(*) as count FROM lure_logs GROUP BY dpt ORDER BY count DESC LIMIT 10;">
                                    <strong>Most Targeted Ports</strong>
                                    <code>SELECT dpt, COUNT(*) as count FROM lure_logs GROUP BY dpt ORDER BY count DESC LIMIT 10;</code>
                                </div>
                                
                                <div class="query-example" data-query="SELECT DATE(syslog_ts) as date, COUNT(*) as attacks FROM lure_logs WHERE datetime(syslog_ts) > datetime('now', '-7 days') GROUP BY DATE(syslog_ts) ORDER BY date;">
                                    <strong>Daily Snared Count (Last 7 Days)</strong>
                                    <code>SELECT DATE(syslog_ts) as date, COUNT(*) as attacks FROM lure_logs WHERE datetime(syslog_ts) > datetime('now', '-7 days') GROUP BY DATE(syslog_ts) ORDER BY date;</code>
                                </div>
                                
                                <div class="query-example" data-query="SELECT lure_host, COUNT(*) as attacks FROM lure_logs GROUP BY lure_host ORDER BY attacks DESC;">
                                    <strong>Attacks by Lure</strong>
                                    <code>SELECT lure_host, COUNT(*) as attacks FROM lure_logs GROUP BY lure_host ORDER BY attacks DESC;</code>
                                </div>
                                
                                <div class="query-example" data-query="SELECT proto, COUNT(*) as count FROM lure_logs GROUP BY proto ORDER BY count DESC;">
                                    <strong>Protocol Distribution</strong>
                                    <code>SELECT proto, COUNT(*) as count FROM lure_logs GROUP BY proto ORDER BY count DESC;</code>
                                </div>
                                
                                <div class="query-example" data-query="SELECT src_ip, COUNT(DISTINCT dpt) as unique_ports FROM lure_logs GROUP BY src_ip HAVING unique_ports > 10 ORDER BY unique_ports DESC;">
                                    <strong>Port Scanners (10+ ports)</strong>
                                    <code>SELECT src_ip, COUNT(DISTINCT dpt) as unique_ports FROM lure_logs GROUP BY src_ip HAVING unique_ports > 10 ORDER BY unique_ports DESC;</code>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </section>
    </div>

    <!-- Footer -->
    <footer class="main-footer">
        <strong>LURE Log Analytics &copy; 2026</strong>
        <div class="float-right d-none d-sm-inline-block">
            <b>Database:</b> /var/log/lures/lure_logs.db
        </div>
    </footer>
</div>

<!-- jQuery -->
<script src="plugins/jquery/jquery.min.js"></script>
<!-- Bootstrap -->
<script src="plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
<!-- AdminLTE -->
<script src="dist/js/adminlte.min.js"></script>

<script>
let currentResults = [];

// Run query
document.getElementById('run-query').addEventListener('click', function() {
    const query = document.getElementById('query-editor').value.trim();
    
    if (!query) {
        showStatus('Please enter a query', 'error');
        return;
    }
    
    // Show loading status
    showStatus('Executing query...', 'info');
    document.getElementById('results-card').style.display = 'none';
    
    // Execute query
    fetch('../api/sql-query.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ query: query })
    })
    .then(response => response.json())
    .then(data => {
        if (data.error) {
            showStatus('Error: ' + data.error, 'error');
            document.getElementById('results-card').style.display = 'none';
        } else {
            showStatus('Query executed successfully. Rows returned: ' + data.rows.length, 'success');
            displayResults(data);
            currentResults = data.rows;
        }
    })
    .catch(error => {
        showStatus('Error: ' + error.message, 'error');
        document.getElementById('results-card').style.display = 'none';
    });
});

// Clear query
document.getElementById('clear-query').addEventListener('click', function() {
    document.getElementById('query-editor').value = '';
    document.getElementById('query-status').innerHTML = '';
    document.getElementById('results-card').style.display = 'none';
});

// Load example query
document.querySelectorAll('.query-example').forEach(function(example) {
    example.addEventListener('click', function() {
        const query = this.getAttribute('data-query');
        document.getElementById('query-editor').value = query;
        document.getElementById('query-editor').focus();
    });
});

// Display results
function displayResults(data) {
    const resultsCard = document.getElementById('results-card');
    const resultsHeader = document.getElementById('results-header');
    const resultsBody = document.getElementById('results-body');
    const rowCount = document.getElementById('row-count');
    const exportBtn = document.getElementById('export-csv');
    
    // Clear previous results
    resultsHeader.innerHTML = '';
    resultsBody.innerHTML = '';
    
    if (data.rows.length === 0) {
        resultsBody.innerHTML = '<tr><td class="text-center">No results found</td></tr>';
        resultsCard.style.display = 'block';
        exportBtn.style.display = 'none';
        return;
    }
    
    // Build header
    const headerRow = document.createElement('tr');
    data.columns.forEach(col => {
        const th = document.createElement('th');
        th.textContent = col;
        headerRow.appendChild(th);
    });
    resultsHeader.appendChild(headerRow);
    
    // Build body
    data.rows.forEach(row => {
        const tr = document.createElement('tr');
        data.columns.forEach(col => {
            const td = document.createElement('td');
            td.textContent = row[col] !== null ? row[col] : 'NULL';
            tr.appendChild(td);
        });
        resultsBody.appendChild(tr);
    });
    
    // Update row count
    rowCount.textContent = data.rows.length + ' rows';
    
    // Show results and export button
    resultsCard.style.display = 'block';
    exportBtn.style.display = 'inline-block';
}

// Export to CSV
document.getElementById('export-csv').addEventListener('click', function() {
    if (currentResults.length === 0) return;
    
    const query = document.getElementById('query-editor').value;
    
    fetch('../api/sql-query.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ query: query, format: 'csv' })
    })
    .then(response => response.blob())
    .then(blob => {
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = 'query_results_' + new Date().getTime() + '.csv';
        document.body.appendChild(a);
        a.click();
        window.URL.revokeObjectURL(url);
        document.body.removeChild(a);
    });
});

// Show status message
function showStatus(message, type) {
    const statusDiv = document.getElementById('query-status');
    const className = type === 'error' ? 'error-message' : type === 'success' ? 'success-message' : 'alert alert-info';
    statusDiv.innerHTML = `<div class="${className}">${message}</div>`;
}

// Keyboard shortcut: Ctrl+Enter to run query
document.getElementById('query-editor').addEventListener('keydown', function(e) {
    if (e.ctrlKey && e.key === 'Enter') {
        document.getElementById('run-query').click();
    }
});
</script>

</body>
</html>
