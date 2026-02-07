<?php
require_once 'includes/auth.php';
require_admin();

$message = '';
$error = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'issue') {
        $name = trim($_POST['name'] ?? '');
        $name = preg_replace('/[^a-zA-Z0-9_-]/', '', $name); // Sanitize
        
        if (empty($name)) {
            $error = 'Certificate name is required';
        } elseif (strlen($name) < 3) {
            $error = 'Certificate name must be at least 3 characters';
        } else {
            $output = [];
            $return_code = 0;
            exec('sudo /usr/local/share/lure/cert-mgt/lure-cert issue ' . escapeshellarg($name) . ' 2>&1', $output, $return_code);
            
            if ($return_code === 0) {
                // Update cert_type to 'api' for manually issued certs
                db_query("UPDATE certificates SET cert_type = 'api' WHERE common_name = :name AND cert_type IS NULL", [':name' => $name]);
                audit_log('cert_issue', 'certificate', $name, null);
                $message = "Certificate '$name' issued successfully";
            } else {
                $error = "Failed to issue certificate: " . implode("\n", $output);
            }
        }
    }
    
    if ($action === 'revoke') {
        $name = trim($_POST['name'] ?? '');
        
        if (empty($name)) {
            $error = 'Certificate name is required';
        } else {
            $output = [];
            $return_code = 0;
            exec('sudo /usr/local/share/lure/cert-mgt/lure-cert revoke ' . escapeshellarg($name) . ' 2>&1', $output, $return_code);
            
            if ($return_code === 0) {
                audit_log('cert_revoke', 'certificate', $name, null);
                $message = "Certificate '$name' revoked";
            } else {
                $error = "Failed to revoke certificate: " . implode("\n", $output);
            }
        }
    }
    
    if ($action === 'regenerate_lure') {
        $name = trim($_POST['name'] ?? '');
        $name = preg_replace('/[^a-zA-Z0-9_-]/', '', $name);
        
        if (empty($name)) {
            $error = 'Lure name is required';
        } else {
            // First revoke the old cert
            exec('sudo /usr/local/share/lure/cert-mgt/lure-cert revoke ' . escapeshellarg($name) . ' 2>&1');
            
            // Delete the old cert files
            exec('sudo rm -f /etc/ssl/lure/clients/' . escapeshellarg($name) . '.crt 2>&1');
            exec('sudo rm -f /etc/ssl/lure/clients/' . escapeshellarg($name) . '.key 2>&1');
            
            // Generate new cert
            $output = [];
            $return_code = 0;
            exec('sudo /usr/local/share/lure/cert-mgt/lure-cert-generate.sh ' . escapeshellarg($name) . ' 2>&1', $output, $return_code);
            
            if ($return_code === 0) {
                audit_log('cert_regenerate', 'lure_certificate', $name, null);
                $message = "Certificate for lure '$name' regenerated. You must redeploy or manually update the lure's certificates.";
            } else {
                $error = "Failed to regenerate certificate: " . implode("\n", $output);
            }
        }
    }
}

// Handle download
if (isset($_GET['download'])) {
    $name = trim($_GET['download']);
    $name = preg_replace('/[^a-zA-Z0-9_-]/', '', $name);
    
    $cert_path = "/etc/ssl/lure/clients/{$name}.crt";
    $key_path = "/etc/ssl/lure/clients/{$name}.key";
    $ca_path = "/etc/ssl/lure/ca.crt";
    
    // Check cert exists and is active
    $cert = db_fetch_one('SELECT is_active, cert_type FROM certificates WHERE common_name = :name', [':name' => $name]);
    
    if (!$cert || !$cert['is_active']) {
        $error = 'Certificate not found or revoked';
    } else {
        // Create zip in temp directory
        $zip_file = "/tmp/lure-cert-{$name}.zip";
        $zip = new ZipArchive();
        
        if ($zip->open($zip_file, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {
            // Read files with sudo
            $cert_content = shell_exec('sudo cat ' . escapeshellarg($cert_path));
            $key_content = shell_exec('sudo cat ' . escapeshellarg($key_path));
            $ca_content = shell_exec('sudo cat ' . escapeshellarg($ca_path));
            
            $zip->addFromString("{$name}.crt", $cert_content);
            $zip->addFromString("{$name}.key", $key_content);
            $zip->addFromString("ca.crt", $ca_content);
            
            // Add readme based on cert type
            $cert_type = $cert['cert_type'] ?? 'api';
            if ($cert_type === 'lure') {
                $readme = "LURE Health Reporting Certificate Bundle\n";
                $readme .= "=========================================\n\n";
                $readme .= "Lure: {$name}\n\n";
                $readme .= "Files:\n";
                $readme .= "  client.crt - Client certificate (rename from {$name}.crt)\n";
                $readme .= "  client.key - Client private key (rename from {$name}.key)\n";
                $readme .= "  ca.crt     - CA certificate\n\n";
                $readme .= "Installation on lure:\n";
                $readme .= "  Place files in /usr/local/share/lure/certs/\n";
                $readme .= "  Rename {$name}.crt to client.crt\n";
                $readme .= "  Rename {$name}.key to client.key\n";
            } else {
                $readme = "LURE API Client Certificate Bundle\n";
                $readme .= "===================================\n\n";
                $readme .= "Client Name: {$name}\n\n";
                $readme .= "Files:\n";
                $readme .= "  {$name}.crt - Client certificate\n";
                $readme .= "  {$name}.key - Client private key (keep secure!)\n";
                $readme .= "  ca.crt      - CA certificate\n\n";
                $readme .= "Usage with curl:\n";
                $readme .= "  curl --cert {$name}.crt --key {$name}.key --cacert ca.crt https://dashboard.lure.network/api/endpoint\n\n";
                $readme .= "Usage with Python requests:\n";
                $readme .= "  requests.get(url, cert=('{$name}.crt', '{$name}.key'), verify='ca.crt')\n";
            }
            $zip->addFromString("README.txt", $readme);
            
            $zip->close();
            
            audit_log('cert_download', 'certificate', $name, null);
            
            // Send download
            header('Content-Type: application/zip');
            header('Content-Disposition: attachment; filename="lure-cert-' . $name . '.zip"');
            header('Content-Length: ' . filesize($zip_file));
            readfile($zip_file);
            unlink($zip_file);
            exit;
        } else {
            $error = 'Failed to create zip file';
        }
    }
}

// Fetch certificates by type
$api_certificates = db_fetch_all("SELECT * FROM certificates WHERE (cert_type = 'api' OR cert_type IS NULL) AND common_name NOT LIKE 'Lure-%' ORDER BY id DESC");
$lure_certificates = db_fetch_all("SELECT c.*, h.status as lure_status, h.last_check as last_health_report 
                                   FROM certificates c 
                                   LEFT JOIN lure_health h ON c.common_name = h.hostname 
                                   WHERE c.cert_type = 'lure' OR c.common_name LIKE 'Lure-%'
                                   ORDER BY c.id DESC");

// Count stats
$total_api = count($api_certificates);
$active_api = count(array_filter($api_certificates, fn($c) => $c['is_active']));
$total_lure = count($lure_certificates);
$active_lure = count(array_filter($lure_certificates, fn($c) => $c['is_active']));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LURE | Certificate Management</title>
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
                        <h1 class="m-0"><i class="fas fa-certificate text-warning"></i> Certificate Management</h1>
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

                <!-- Summary Cards -->
                <div class="row">
                    <div class="col-lg-3 col-6">
                        <div class="small-box bg-info">
                            <div class="inner">
                                <h3><?php echo $active_api; ?></h3>
                                <p>Active API Certs</p>
                            </div>
                            <div class="icon"><i class="fas fa-key"></i></div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-6">
                        <div class="small-box bg-success">
                            <div class="inner">
                                <h3><?php echo $active_lure; ?></h3>
                                <p>Active Lure Certs</p>
                            </div>
                            <div class="icon"><i class="fas fa-server"></i></div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-6">
                        <div class="small-box bg-warning">
                            <div class="inner">
                                <?php 
                                $expiring_soon = 0;
                                foreach (array_merge($api_certificates, $lure_certificates) as $c) {
                                    if ($c['is_active'] && strtotime($c['expires_at']) < strtotime('+30 days')) {
                                        $expiring_soon++;
                                    }
                                }
                                ?>
                                <h3><?php echo $expiring_soon; ?></h3>
                                <p>Expiring Soon (&lt;30d)</p>
                            </div>
                            <div class="icon"><i class="fas fa-clock"></i></div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-6">
                        <div class="small-box bg-secondary">
                            <div class="inner">
                                <h3><?php echo ($total_api - $active_api) + ($total_lure - $active_lure); ?></h3>
                                <p>Revoked</p>
                            </div>
                            <div class="icon"><i class="fas fa-ban"></i></div>
                        </div>
                    </div>
                </div>

                <!-- Issue API Certificate Card -->
                <div class="card card-success">
                    <div class="card-header">
                        <h3 class="card-title"><i class="fas fa-plus-circle mr-2"></i>Issue New API Certificate</h3>
                        <div class="card-tools">
                            <button type="button" class="btn btn-tool" data-card-widget="collapse"><i class="fas fa-minus"></i></button>
                        </div>
                    </div>
                    <form method="post">
                        <input type="hidden" name="action" value="issue">
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>Client Name</label>
                                        <input type="text" name="name" class="form-control" 
                                               pattern="[a-zA-Z0-9_-]+" 
                                               title="Only letters, numbers, underscores and hyphens"
                                               placeholder="e.g., api-client-1, sync-agent-prod" required>
                                        <small class="form-text text-muted">
                                            Use only letters, numbers, underscores and hyphens. This becomes the certificate's Common Name.
                                        </small>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="card-footer">
                            <button type="submit" class="btn btn-success">
                                <i class="fas fa-plus mr-2"></i>Issue Certificate
                            </button>
                        </div>
                    </form>
                </div>

                <!-- API Certificates List -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title"><i class="fas fa-key mr-2"></i>API Client Certificates</h3>
                        <div class="card-tools">
                            <span class="badge badge-info"><?php echo $total_api; ?> total</span>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php if (empty($api_certificates)): ?>
                        <p class="text-muted text-center">No API certificates issued yet.</p>
                        <?php else: ?>
                        <table id="apiCertsTable" class="table table-bordered table-striped table-sm">
                            <thead>
                                <tr>
                                    <th>Common Name</th>
                                    <th>Serial</th>
                                    <th>Issued</th>
                                    <th>Expires</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($api_certificates as $cert): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($cert['common_name']); ?></strong></td>
                                    <td><code style="font-size: 0.75em;"><?php echo htmlspecialchars(substr($cert['serial_number'] ?? '', 0, 16)); ?>...</code></td>
                                    <td><?php echo date('Y-m-d', strtotime($cert['issued_at'] ?? '')); ?></td>
                                    <td>
                                        <?php 
                                        $expires = $cert['expires_at'] ?? '';
                                        $expires_ts = strtotime($expires);
                                        $days_left = $expires_ts ? round(($expires_ts - time()) / 86400) : 0;
                                        $badge_class = 'success';
                                        if ($days_left < 30) $badge_class = 'warning';
                                        if ($days_left < 7) $badge_class = 'danger';
                                        if (!$cert['is_active']) $badge_class = 'secondary';
                                        ?>
                                        <?php echo date('Y-m-d', strtotime($expires)); ?>
                                        <?php if ($cert['is_active'] && $days_left > 0): ?>
                                        <span class="badge badge-<?php echo $badge_class; ?>"><?php echo $days_left; ?>d</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($cert['is_active']): ?>
                                        <span class="badge badge-success">Active</span>
                                        <?php else: ?>
                                        <span class="badge badge-danger">Revoked</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($cert['is_active']): ?>
                                        <a href="?download=<?php echo urlencode($cert['common_name']); ?>" 
                                           class="btn btn-xs btn-primary" title="Download Bundle">
                                            <i class="fas fa-download"></i>
                                        </a>
                                        <form method="post" style="display:inline;" 
                                              onsubmit="return confirm('Revoke certificate <?php echo htmlspecialchars($cert['common_name']); ?>?');">
                                            <input type="hidden" name="action" value="revoke">
                                            <input type="hidden" name="name" value="<?php echo htmlspecialchars($cert['common_name']); ?>">
                                            <button type="submit" class="btn btn-xs btn-danger" title="Revoke">
                                                <i class="fas fa-ban"></i>
                                            </button>
                                        </form>
                                        <?php else: ?>
                                        <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Lure Certificates List -->
                <div class="card card-primary">
                    <div class="card-header">
                        <h3 class="card-title"><i class="fas fa-server mr-2"></i>Lure Health Certificates</h3>
                        <div class="card-tools">
                            <span class="badge badge-light"><?php echo $total_lure; ?> total</span>
                        </div>
                    </div>
                    <div class="card-body">
                        <p class="text-muted small mb-3">
                            <i class="fas fa-info-circle mr-1"></i>
                            Lure certificates are automatically generated during Cast deployment. They allow lures to report health status via mTLS.
                        </p>
                        <?php if (empty($lure_certificates)): ?>
                        <p class="text-muted text-center">No lure certificates yet. Deploy a lure via Cast to generate one.</p>
                        <?php else: ?>
                        <table id="lureCertsTable" class="table table-bordered table-striped table-sm">
                            <thead>
                                <tr>
                                    <th>Lure</th>
                                    <th>Lure Status</th>
                                    <th>Last Health Report</th>
                                    <th>Cert Expires</th>
                                    <th>Cert Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($lure_certificates as $cert): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($cert['common_name']); ?></strong>
                                    </td>
                                    <td>
                                        <?php 
                                        $lure_status = $cert['lure_status'] ?? 'unknown';
                                        $status_badge = 'secondary';
                                        if ($lure_status === 'active') $status_badge = 'success';
                                        if ($lure_status === 'degraded') $status_badge = 'warning';
                                        if ($lure_status === 'offline' || $lure_status === 'terminated') $status_badge = 'danger';
                                        ?>
                                        <span class="badge badge-<?php echo $status_badge; ?>"><?php echo ucfirst($lure_status); ?></span>
                                    </td>
                                    <td>
                                        <?php if ($cert['last_health_report']): ?>
                                        <?php 
                                        $last_report = strtotime($cert['last_health_report']);
                                        $mins_ago = round((time() - $last_report) / 60);
                                        ?>
                                        <?php if ($mins_ago < 10): ?>
                                        <span class="text-success"><?php echo $mins_ago; ?>m ago</span>
                                        <?php elseif ($mins_ago < 60): ?>
                                        <span class="text-warning"><?php echo $mins_ago; ?>m ago</span>
                                        <?php else: ?>
                                        <span class="text-danger"><?php echo round($mins_ago / 60); ?>h ago</span>
                                        <?php endif; ?>
                                        <?php else: ?>
                                        <span class="text-muted">Never</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php 
                                        $expires = $cert['expires_at'] ?? '';
                                        $expires_ts = strtotime($expires);
                                        $days_left = $expires_ts ? round(($expires_ts - time()) / 86400) : 0;
                                        $badge_class = 'success';
                                        if ($days_left < 30) $badge_class = 'warning';
                                        if ($days_left < 7) $badge_class = 'danger';
                                        if (!$cert['is_active']) $badge_class = 'secondary';
                                        ?>
                                        <?php echo date('Y-m-d', strtotime($expires)); ?>
                                        <span class="badge badge-<?php echo $badge_class; ?>"><?php echo $days_left; ?>d</span>
                                    </td>
                                    <td>
                                        <?php if ($cert['is_active']): ?>
                                        <span class="badge badge-success">Active</span>
                                        <?php else: ?>
                                        <span class="badge badge-danger">Revoked</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($cert['is_active']): ?>
                                        <a href="?download=<?php echo urlencode($cert['common_name']); ?>" 
                                           class="btn btn-xs btn-primary" title="Download Bundle">
                                            <i class="fas fa-download"></i>
                                        </a>
                                        <form method="post" style="display:inline;" 
                                              onsubmit="return confirm('Regenerate certificate for <?php echo htmlspecialchars($cert['common_name']); ?>? The lure will need to be updated with the new certificate.');">
                                            <input type="hidden" name="action" value="regenerate_lure">
                                            <input type="hidden" name="name" value="<?php echo htmlspecialchars($cert['common_name']); ?>">
                                            <button type="submit" class="btn btn-xs btn-warning" title="Regenerate">
                                                <i class="fas fa-sync"></i>
                                            </button>
                                        </form>
                                        <form method="post" style="display:inline;" 
                                              onsubmit="return confirm('Revoke certificate for <?php echo htmlspecialchars($cert['common_name']); ?>? The lure will no longer be able to report health.');">
                                            <input type="hidden" name="action" value="revoke">
                                            <input type="hidden" name="name" value="<?php echo htmlspecialchars($cert['common_name']); ?>">
                                            <button type="submit" class="btn btn-xs btn-danger" title="Revoke">
                                                <i class="fas fa-ban"></i>
                                            </button>
                                        </form>
                                        <?php else: ?>
                                        <form method="post" style="display:inline;">
                                            <input type="hidden" name="action" value="regenerate_lure">
                                            <input type="hidden" name="name" value="<?php echo htmlspecialchars($cert['common_name']); ?>">
                                            <button type="submit" class="btn btn-xs btn-success" title="Issue New">
                                                <i class="fas fa-plus"></i> New
                                            </button>
                                        </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Info Card -->
                <div class="card card-info collapsed-card">
                    <div class="card-header">
                        <h3 class="card-title"><i class="fas fa-info-circle mr-2"></i>Usage Instructions</h3>
                        <div class="card-tools">
                            <button type="button" class="btn btn-tool" data-card-widget="collapse"><i class="fas fa-plus"></i></button>
                        </div>
                    </div>
                    <div class="card-body">
                        <h5>API Client Certificates</h5>
                        <p>After downloading a certificate bundle, clients can authenticate to the API using mTLS:</p>
                        
                        <h6>curl</h6>
                        <pre><code>curl --cert client.crt --key client.key --cacert ca.crt https://dashboard.lure.network/api/endpoint</code></pre>
                        
                        <h6>Python</h6>
                        <pre><code>import requests
response = requests.get(url, cert=('client.crt', 'client.key'), verify='ca.crt')</code></pre>
                        
                        <hr>
                        
                        <h5>Lure Health Certificates</h5>
                        <p>Lure certificates are generated automatically during Cast deployment. The certificate files are placed in <code>/usr/local/share/lure/certs/</code> on each lure.</p>
                        <p>If you need to manually update a lure's certificate:</p>
                        <ol>
                            <li>Download the certificate bundle</li>
                            <li>Copy files to the lure's <code>/usr/local/share/lure/certs/</code> directory</li>
                            <li>Rename the .crt and .key files to <code>client.crt</code> and <code>client.key</code></li>
                        </ol>
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
    $('#apiCertsTable').DataTable({
        "paging": true,
        "lengthChange": false,
        "searching": true,
        "ordering": true,
        "order": [[2, "desc"]],
        "info": false,
        "autoWidth": false,
        "pageLength": 10
    });
    
    $('#lureCertsTable').DataTable({
        "paging": true,
        "lengthChange": false,
        "searching": true,
        "ordering": true,
        "order": [[0, "asc"]],
        "info": false,
        "autoWidth": false,
        "pageLength": 10
    });
});
</script>
</body>
</html>
