<?php
require_once 'includes/auth.php';
require_login();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LURE Lists Manager</title>
    
    <!-- AdminLTE CSS -->
    <link rel="stylesheet" href="dist/css/adminlte.min.css">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="plugins/fontawesome-free/css/all.min.css">
    
    <style>
        .ip-search-result {
            margin-top: 20px;
            padding: 15px;
            border-radius: 5px;
        }
        .permit-badge {
            background-color: #28a745;
            color: white;
            padding: 5px 10px;
            border-radius: 3px;
        }
        .block-badge {
            background-color: #dc3545;
            color: white;
            padding: 5px 10px;
            border-radius: 3px;
        }
        .neutral-badge {
            background-color: #6c757d;
            color: white;
            padding: 5px 10px;
            border-radius: 3px;
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
                        <h1 class="m-0">Lists Manager</h1>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main content -->
        <section class="content">
            <div class="container-fluid">
                
                <!-- IP Search -->
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">Search IP Address</h3>
                            </div>
                            <div class="card-body">
                                <div class="input-group">
                                   <input type="text" id="search-ip" class="form-control" placeholder="Enter IP address (e.g., 1.2.3.4) or CIDR range (e.g., 10.0.0.0/16)"> 
                                    <div class="input-group-append">
                                        <button class="btn btn-primary" onclick="searchIP()">
                                            <i class="fas fa-search"></i> Search
                                        </button>
                                    </div>
                                </div>
                                <div id="search-result"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Permit List & Block List -->
                <div class="row">
                    <!-- Permit List -->
                    <div class="col-lg-6">
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">Permit List (Whitelist)</h3>
                                <div class="card-tools">
                                    <button class="btn btn-sm btn-success" onclick="showAddModal()">
                                        <i class="fas fa-plus"></i> Add Entry
                                    </button>
                                </div>
                            </div>
                            <div class="card-body p-0">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>IP/CIDR</th>
                                            <th>Description</th>
                                            <th>Added</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody id="permit-list-table">
                                        <tr><td colspan="4" class="text-center">Loading...</td></tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Block List -->
			<!-- Download Block List -->
                    <div class="col-lg-6">
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">Download Block List</h3>
                            </div>
                            <div class="card-body text-center">
                                <p class="text-muted">Export blocked IP addresses for use in firewalls, IDS/IPS, or other security tools.</p>
                                <p class="text-muted"><small>Automatically excludes all IPs and ranges from the Permit List</small></p>
                                <div class="mt-4">
                                    <button class="btn btn-primary btn-lg mr-3" onclick="downloadBlocklistTxt()">
                                        <i class="fas fa-download"></i> Download .TXT
                                    </button>
                                    <button class="btn btn-info btn-lg" onclick="downloadBlocklistCsv()">
                                        <i class="fas fa-file-csv"></i> Detailed .CSV
                                    </button>
                                </div>
                                <div class="mt-4">
                                    <p id="blocklist-count" class="text-muted"><small>Loading count...</small></p>
                                </div>
                            </div>
                        </div>
                    </div>
        </section>
    </div>

    <!-- Footer -->
    <footer class="main-footer">
        <strong>LURE Dashboard &copy; 2026</strong>
        <div class="float-right d-none d-sm-inline-block">
            Last updated: <span id="last-update">Never</span>
        </div>
    </footer>
</div>

<!-- Add Entry Modal -->
<div class="modal fade" id="addModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add Permit Entry</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label>IP Address or CIDR Range</label>
                    <input type="text" id="add-entry" class="form-control" placeholder="e.g., 1.2.3.4 or 10.0.0.0/8">
                    <small class="form-text text-muted">Enter a single IP or CIDR notation</small>
                </div>
                <div class="form-group">
                    <label>Description (Optional)</label>
                    <input type="text" id="add-description" class="form-control" placeholder="e.g., Internal network">
                </div>
                <div id="add-error" class="alert alert-danger" style="display:none;"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="addPermitEntry()">Add</button>
            </div>
        </div>
    </div>
</div>

<!-- jQuery -->
<script src="plugins/jquery/jquery.min.js"></script>
<!-- Bootstrap -->
<script src="plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
<!-- AdminLTE -->
<script src="dist/js/adminlte.min.js"></script>

<script>
// Load permit list
function loadPermitList() {
    fetch('../api/permit-list.php')
        .then(response => response.json())
        .then(data => {
            const tbody = document.getElementById('permit-list-table');
            tbody.innerHTML = '';
            
            if (data.length === 0) {
                tbody.innerHTML = '<tr><td colspan="4" class="text-center text-muted">No entries yet</td></tr>';
                return;
            }
            
            data.forEach(entry => {
                const row = tbody.insertRow();
                const date = new Date(entry.created_at);
                row.innerHTML = `
                    <td><code>${entry.entry}</code></td>
                    <td>${entry.description || '<em>No description</em>'}</td>
                    <td><small>${date.toLocaleString()}</small></td>
                    <td>
                        <button class="btn btn-sm btn-danger" onclick="deletePermitEntry(${entry.id})">
                            <i class="fas fa-trash"></i>
                        </button>
                    </td>
                `;
            });
        })
        .catch(error => console.error('Error loading permit list:', error));
}

// Load block list
function loadBlockList() {
    fetch('../api/block-list.php?limit=100')
        .then(response => response.json())
        .then(data => {
            const tbody = document.getElementById('block-list-table');
            tbody.innerHTML = '';
            
            if (data.length === 0) {
                tbody.innerHTML = '<tr><td colspan="3" class="text-center text-muted">No blocked IPs</td></tr>';
                return;
            }
            
            data.forEach(entry => {
                const row = tbody.insertRow();
                const date = new Date(entry.last_seen);
                row.innerHTML = `
                    <td><code>${entry.src_ip}</code></td>
                    <td><span class="badge badge-danger">${entry.attack_count}</span></td>
                    <td><small>${date.toLocaleString()}</small></td>
                `;
            });
        })
        .catch(error => console.error('Error loading block list:', error));
}

// Show add modal
function showAddModal() {
    document.getElementById('add-entry').value = '';
    document.getElementById('add-description').value = '';
    document.getElementById('add-error').style.display = 'none';
    $('#addModal').modal('show');
}

// Add permit entry
function addPermitEntry() {
    const entry = document.getElementById('add-entry').value.trim();
    const description = document.getElementById('add-description').value.trim();
    const errorDiv = document.getElementById('add-error');
    
    if (!entry) {
        errorDiv.textContent = 'IP or CIDR is required';
        errorDiv.style.display = 'block';
        return;
    }
    
    fetch('../api/permit-add.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ entry, description })
    })
    .then(response => response.json())
    .then(data => {
        if (data.error) {
            errorDiv.textContent = data.error;
            errorDiv.style.display = 'block';
        } else {
            $('#addModal').modal('hide');
            loadPermitList();
            loadBlockList(); // Refresh block list as it may change
        }
    })
    .catch(error => {
        errorDiv.textContent = 'Network error';
        errorDiv.style.display = 'block';
    });
}

// Delete permit entry
function deletePermitEntry(id) {
    if (!confirm('Remove this entry from the permit list?')) return;
    
    fetch('../api/permit-delete.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            loadPermitList();
            loadBlockList();
        } else {
            alert('Error: ' + (data.error || 'Unknown error'));
        }
    })
    .catch(error => alert('Network error'));
}

// Search IP or CIDR
function searchIP() {
    const input = document.getElementById('search-ip').value.trim();
    const resultDiv = document.getElementById('search-result');
    
    if (!input) {
        resultDiv.innerHTML = '';
        return;
    }
    
    fetch(`../api/ip-search.php?ip=${encodeURIComponent(input)}`)
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                resultDiv.innerHTML = `<div class="alert alert-danger mt-3">${data.error}</div>`;
                return;
            }
            
            let html = '<div class="ip-search-result">';
            
            if (data.search_type === 'ip') {
                // Single IP search result
                html += `<h5>Results for IP: <code>${data.ip}</code></h5>`;
                
                if (data.in_permit_list) {
                    html += '<p><span class="permit-badge"><i class="fas fa-check"></i> IN PERMIT LIST</span></p>';
                    if (data.permit_entry) {
                        if (data.permit_entry.entry) {
                            html += `<p><strong>Matched:</strong> ${data.permit_entry.entry}</p>`;
                            if (data.permit_entry.description) {
                                html += `<p><strong>Description:</strong> ${data.permit_entry.description}</p>`;
                            }
                        }
                    }
                } else if (data.in_block_list) {
                    html += '<p><span class="block-badge"><i class="fas fa-ban"></i> IN BLOCK LIST</span></p>';
                    html += `<p><strong>Snared Count:</strong> ${data.block_info.attack_count}</p>`;
                    html += `<p><strong>First Seen:</strong> ${new Date(data.block_info.first_seen).toLocaleString()}</p>`;
                    html += `<p><strong>Last Seen:</strong> ${new Date(data.block_info.last_seen).toLocaleString()}</p>`;
                } else {
                    html += '<p><span class="neutral-badge"><i class="fas fa-info-circle"></i> NOT IN ANY LIST</span></p>';
                }
                
            } else {
                // CIDR range search result
                html += `<h5>Results for CIDR: <code>${data.cidr}</code></h5>`;
                
                if (data.in_permit_list) {
                    html += '<p><span class="permit-badge"><i class="fas fa-check"></i> RANGE IN PERMIT LIST</span></p>';
                    if (data.permit_entry && data.permit_entry.description) {
                        html += `<p><strong>Description:</strong> ${data.permit_entry.description}</p>`;
                    }
                }
                
                if (data.overlapping_permit_ranges && data.overlapping_permit_ranges.length > 0) {
                    html += '<p><strong>Overlaps with permit ranges:</strong></p><ul>';
                    data.overlapping_permit_ranges.forEach(range => {
                        html += `<li><code>${range.entry}</code>`;
                        if (range.description) html += ` - ${range.description}`;
                        html += '</li>';
                    });
                    html += '</ul>';
                }
                
                if (data.blocked_ips_in_range.count > 0) {
                    html += `<p><span class="block-badge"><i class="fas fa-exclamation-triangle"></i> ${data.blocked_ips_in_range.count} BLOCKED IPs IN THIS RANGE</span></p>`;
                    html += '<div style="max-height: 300px; overflow-y: auto;"><table class="table table-sm table-striped"><thead><tr><th>IP</th><th>Attacks</th><th>Last Seen</th></tr></thead><tbody>';
                    data.blocked_ips_in_range.ips.forEach(ip => {
                        html += `<tr><td><code>${ip.src_ip}</code></td><td>${ip.attack_count}</td><td>${new Date(ip.last_seen).toLocaleString()}</td></tr>`;
                    });
                    html += '</tbody></table></div>';
                    if (data.blocked_ips_in_range.count > 100) {
                        html += `<p class="text-muted"><small>Showing first 100 of ${data.blocked_ips_in_range.count} IPs</small></p>`;
                    }
                } else {
                    html += '<p><span class="neutral-badge"><i class="fas fa-info-circle"></i> NO BLOCKED IPs IN THIS RANGE</span></p>';
                }
            }
            
            html += '</div>';
            resultDiv.innerHTML = html;
        })
        .catch(error => {
            resultDiv.innerHTML = '<div class="alert alert-danger mt-3">Search failed</div>';
        });
}

// Download block list as CSV
// Download blocklist as plain text (just IPs)
function downloadBlocklistTxt() {
    fetch('../api/block-list.php')
        .then(response => response.json())
        .then(data => {
            let txt = data.map(entry => entry.src_ip).join('\n');
            
            const blob = new Blob([txt], { type: 'text/plain' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `blocklist-${new Date().toISOString().split('T')[0]}.txt`;
            a.click();
            window.URL.revokeObjectURL(url);
        })
        .catch(error => alert('Download failed'));
}

// Download detailed blocklist as CSV
function downloadBlocklistCsv() {
    fetch('../api/block-list.php')
        .then(response => response.json())
        .then(data => {
            let csv = 'IP Address,Snared Count,First Seen,Last Seen,Unique Port Count\n';
            data.forEach(entry => {
                csv += `${entry.src_ip},${entry.attack_count},${entry.first_seen},${entry.last_seen},${entry.unique_ports}\n`;
            });
            
            const blob = new Blob([csv], { type: 'text/csv' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `blocklist-detailed-${new Date().toISOString().split('T')[0]}.csv`;
            a.click();
            window.URL.revokeObjectURL(url);
        })
        .catch(error => alert('Download failed'));
}

// Load blocklist count
function loadBlocklistCount() {
    fetch('../api/block-list.php')
        .then(response => response.json())
        .then(data => {
            document.getElementById('blocklist-count').innerHTML = 
                `<small><i class="fas fa-ban text-danger"></i> <strong>${data.length}</strong> block listed IP addresses</small>`;
        })
        .catch(error => {
            document.getElementById('blocklist-count').innerHTML = 
                '<small class="text-danger">Error loading count</small>';
        });
}
// Initial load
loadPermitList();
loadBlocklistCount();

// Auto-refresh every 60 seconds
setInterval(() => {
    loadPermitList();
    loadBlocklistCount();
    document.getElementById('last-update').textContent = new Date().toLocaleString();
}, 60000);
</script>

</body>
</html>
