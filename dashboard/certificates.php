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
}

// Handle download
if (isset($_GET['download'])) {
    $name = trim($_GET['download']);
    $name = preg_replace('/[^a-zA-Z0-9_-]/', '', $name);
    
    $cert_path = "/etc/ssl/lure/clients/{$name}.crt";
    $key_path = "/etc/ssl/lure/clients/{$name}.key";
    $ca_path = "/etc/ssl/lure/ca.crt";
    
    // Check cert exists and is active
    $cert = db_fetch_one('SELECT is_active FROM certificates WHERE common_name = :name', [':name' => $name]);
    
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
            
            // Add readme
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

$certificates = db_fetch_all('SELECT * FROM certificates ORDER BY id DESC');
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
                        <h1 class="m-0">API Certificate Management</h1>
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

                <!-- Issue Certificate Card -->
                <div class="card card-success">
                    <div class="card-header">
                        <h3 class="card-title"><i class="fas fa-certificate mr-2"></i>Issue New Certificate</h3>
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

                <!-- Certificates List -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title"><i class="fas fa-list mr-2"></i>Issued Certificates</h3>
                    </div>
                    <div class="card-body">
                        <table id="certsTable" class="table table-bordered table-striped">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Common Name</th>
                                    <th>Serial Number</th>
                                    <th>Issued</th>
                                    <th>Expires</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($certificates as $cert): ?>
                                <tr>
                                    <td><?php echo $cert['id']; ?></td>
                                    <td><strong><?php echo htmlspecialchars($cert['common_name']); ?></strong></td>
                                    <td><code style="font-size: 0.8em;"><?php echo htmlspecialchars(substr($cert['serial_number'] ?? '', 0, 20)); ?>...</code></td>
                                    <td><?php echo htmlspecialchars($cert['issued_at'] ?? ''); ?></td>
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
                                        <?php echo htmlspecialchars($expires); ?>
                                        <?php if ($cert['is_active'] && $days_left > 0): ?>
                                        <br><span class="badge badge-<?php echo $badge_class; ?>"><?php echo $days_left; ?> days left</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($cert['is_active']): ?>
                                        <span class="badge badge-success"><i class="fas fa-check mr-1"></i>Active</span>
                                        <?php else: ?>
                                        <span class="badge badge-danger"><i class="fas fa-times mr-1"></i>Revoked</span>
                                        <br><small class="text-muted"><?php echo htmlspecialchars($cert['revoked_at'] ?? ''); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($cert['is_active']): ?>
                                        <!-- Download -->
                                        <a href="?download=<?php echo urlencode($cert['common_name']); ?>" 
                                           class="btn btn-sm btn-primary" title="Download Bundle">
                                            <i class="fas fa-download"></i>
                                        </a>
                                        
                                        <!-- Revoke -->
                                        <form method="post" style="display:inline;" 
                                              onsubmit="return confirm('Revoke certificate <?php echo htmlspecialchars($cert['common_name']); ?>? This cannot be undone.');">
                                            <input type="hidden" name="action" value="revoke">
                                            <input type="hidden" name="name" value="<?php echo htmlspecialchars($cert['common_name']); ?>">
                                            <button type="submit" class="btn btn-sm btn-danger" title="Revoke">
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
                        <p>After downloading a certificate bundle, clients can authenticate to the API using mTLS:</p>
                        
                        <h6>curl</h6>
                        <pre><code>curl --cert client.crt --key client.key --cacert ca.crt https://dashboard.lure.network/api/endpoint</code></pre>
                        
                        <h6>Python</h6>
                        <pre><code>import requests
response = requests.get(url, cert=('client.crt', 'client.key'), verify='ca.crt')</code></pre>
                        
                        <h6>wget</h6>
                        <pre><code>wget --certificate=client.crt --private-key=client.key --ca-certificate=ca.crt https://dashboard.lure.network/api/endpoint</code></pre>
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
    $('#certsTable').DataTable({
        "paging": true,
        "lengthChange": false,
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
