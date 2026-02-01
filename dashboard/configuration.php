<?php
require_once 'includes/auth.php';
require_once 'includes/cast.php';
require_login();

// Load all config data
$general = cast_get_config_category('general');
$infra = cast_get_config_category('infra');
$aws = cast_get_config_category('aws');
$subnets = cast_get_subnets();
$subnet_pairs = cast_get_subnet_pairs();
$security_groups = cast_get_security_groups();
$route_tables = cast_get_route_tables();
$vpc_endpoints = cast_get_vpc_endpoints();
$eips = cast_get_eips();
$instance_types = cast_get_instance_types();

$aws_regions = [
    'us-east-1' => 'US East (N. Virginia)',
    'us-east-2' => 'US East (Ohio)',
    'us-west-1' => 'US West (N. California)',
    'us-west-2' => 'US West (Oregon)',
    'eu-west-1' => 'EU (Ireland)',
    'eu-west-2' => 'EU (London)',
    'eu-central-1' => 'EU (Frankfurt)',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LURE - Configuration</title>
    <link rel="stylesheet" href="dist/css/adminlte.min.css">
    <link rel="stylesheet" href="plugins/fontawesome-free/css/all.min.css">
    <link rel="stylesheet" href="plugins/toastr/toastr.min.css">
    <link rel="stylesheet" href="plugins/sweetalert2-theme-bootstrap-4/bootstrap-4.min.css">
</head>
<body class="hold-transition sidebar-mini">
<div class="wrapper">
    
    <?php include 'includes/navbar.php'; ?>

    <div class="content-wrapper">
        <div class="content-header">
            <div class="container-fluid">
                <div class="row mb-2">
                    <div class="col-sm-6">
                        <h1 class="m-0"><i class="fas fa-cogs text-secondary"></i> Configuration</h1>
                    </div>
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-right">
                            <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
                            <li class="breadcrumb-item active">Configuration</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>

        <section class="content">
            <div class="container-fluid">
                
                <div class="card card-primary card-outline card-tabs">
                    <div class="card-header p-0 pt-1 border-bottom-0">
                        <ul class="nav nav-tabs" id="config-tabs" role="tablist">
                            <li class="nav-item">
                                <a class="nav-link active" data-toggle="pill" href="#tab-general">
                                    <i class="fas fa-sliders-h"></i> General
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" data-toggle="pill" href="#tab-infra">
                                    <i class="fas fa-server"></i> Infrastructure
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" data-toggle="pill" href="#tab-aws">
                                    <i class="fab fa-aws"></i> AWS
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" data-toggle="pill" href="#tab-subnets">
                                    <i class="fas fa-network-wired"></i> Subnets
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" data-toggle="pill" href="#tab-security">
                                    <i class="fas fa-shield-alt"></i> Security Groups
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" data-toggle="pill" href="#tab-eips">
                                    <i class="fas fa-globe"></i> Elastic IPs
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" data-toggle="pill" href="#tab-instances">
                                    <i class="fas fa-microchip"></i> Instance Types
                                </a>
                            </li>
                        </ul>
                    </div>
                    
                    <div class="card-body">
                        <div class="tab-content">
                            
                            <!-- General Tab -->
                            <div class="tab-pane fade show active" id="tab-general">
                                <form id="form-general" class="config-form" data-category="general">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label>Deployment Mode</label>
                                                <div class="custom-control custom-radio">
                                                    <input type="radio" id="mode-full" name="deployment_mode" value="full" 
                                                           class="custom-control-input" <?= ($general['deployment_mode'] ?? '') === 'full' ? 'checked' : '' ?>>
                                                    <label class="custom-control-label" for="mode-full">
                                                        <strong>Full</strong> - LURE manages all infrastructure
                                                    </label>
                                                </div>
                                                <div class="custom-control custom-radio">
                                                    <input type="radio" id="mode-byo" name="deployment_mode" value="byo_access" 
                                                           class="custom-control-input" <?= ($general['deployment_mode'] ?? 'byo_access') === 'byo_access' ? 'checked' : '' ?>>
                                                    <label class="custom-control-label" for="mode-byo">
                                                        <strong>BYO Access</strong> - Customer provides Bastion/Proxy
                                                    </label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label>Hostname Prefix</label>
                                                <input type="text" class="form-control" name="name_prefix" 
                                                       value="<?= htmlspecialchars($general['name_prefix'] ?? 'lure-') ?>">
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label>Starting Number</label>
                                                <input type="number" class="form-control" name="name_start" 
                                                       value="<?= htmlspecialchars($general['name_start'] ?? '100') ?>" min="1">
                                            </div>
                                        </div>
                                    </div>
                                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save</button>
                                </form>
                            </div>
                            
                            <!-- Infrastructure Tab -->
                            <div class="tab-pane fade" id="tab-infra">
                                <form id="form-infra" class="config-form" data-category="infra">
                                    <h5>EM-Lure Settings</h5>
                                    <div class="row">
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label>EM Private IP</label>
                                                <input type="text" class="form-control" name="em_ip" 
                                                       value="<?= htmlspecialchars($infra['em_ip'] ?? '') ?>" placeholder="10.0.4.x">
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label>Rsyslog Port</label>
                                                <input type="number" class="form-control" name="rsyslog_port" 
                                                       value="<?= htmlspecialchars($infra['rsyslog_port'] ?? '1514') ?>">
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label>EM Instance ID</label>
                                                <input type="text" class="form-control" name="em_instance_id" 
                                                       value="<?= htmlspecialchars($infra['em_instance_id'] ?? '') ?>" placeholder="i-xxxxx">
                                            </div>
                                        </div>
                                    </div>
                                    <h5>SSH Settings</h5>
                                    <div class="row">
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label>SSH User</label>
                                                <input type="text" class="form-control" name="ssh_user" 
                                                       value="<?= htmlspecialchars($infra['ssh_user'] ?? 'lure') ?>">
                                            </div>
                                        </div>
                                        <div class="col-md-8">
                                            <div class="form-group">
                                                <label>SSH Key Path</label>
                                                <input type="text" class="form-control" name="ssh_key_path" 
                                                       value="<?= htmlspecialchars($infra['ssh_key_path'] ?? '') ?>" placeholder="/home/lure/.ssh/key.pem">
                                            </div>
                                        </div>
                                    </div>
                                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save</button>
                                </form>
                            </div>
                            
                            <!-- AWS Tab -->
                            <div class="tab-pane fade" id="tab-aws">
                                <form id="form-aws" class="config-form" data-category="aws">
                                    <div class="row">
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label>AWS Region</label>
                                                <select class="form-control" name="region">
                                                    <?php foreach ($aws_regions as $code => $name): ?>
                                                    <option value="<?= $code ?>" <?= ($aws['region'] ?? '') === $code ? 'selected' : '' ?>>
                                                        <?= htmlspecialchars($name) ?>
                                                    </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label>VPC ID</label>
                                                <input type="text" class="form-control" name="vpc_id" 
                                                       value="<?= htmlspecialchars($aws['vpc_id'] ?? '') ?>" placeholder="vpc-xxxxx">
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label>Lure AMI ID</label>
                                                <input type="text" class="form-control" name="lure_ami_id" 
                                                       value="<?= htmlspecialchars($aws['lure_ami_id'] ?? '') ?>" placeholder="ami-xxxxx">
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label>SSH Key Pair Name</label>
                                                <input type="text" class="form-control" name="key_pair_name" 
                                                       value="<?= htmlspecialchars($aws['key_pair_name'] ?? '') ?>">
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label>IAM Instance Role</label>
                                                <input type="text" class="form-control" name="iam_role" 
                                                       value="<?= htmlspecialchars($aws['iam_role'] ?? '') ?>">
                                            </div>
                                        </div>
                                    </div>
                                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save</button>
                                    <button type="button" class="btn btn-info" id="btn-test-aws">
                                        <i class="fas fa-plug"></i> Test Connection
                                    </button>
                                </form>
                            </div>
                            
                            <!-- Subnets Tab -->
                            <div class="tab-pane fade" id="tab-subnets">
                                <div class="mb-3">
                                    <button class="btn btn-success btn-sm" data-toggle="modal" data-target="#modal-subnet">
                                        <i class="fas fa-plus"></i> Add Subnet
                                    </button>
                                    <button class="btn btn-info btn-sm" id="btn-import-subnets">
                                        <i class="fab fa-aws"></i> Import from AWS
                                    </button>
                                </div>
                                <table class="table table-sm table-striped">
                                    <thead>
                                        <tr><th>Subnet ID</th><th>Name</th><th>Type</th><th>AZ</th><th>CIDR</th><th>Actions</th></tr>
                                    </thead>
                                    <tbody id="subnets-table">
                                        <?php foreach ($subnets as $s): ?>
                                        <tr data-id="<?= $s['id'] ?>">
                                            <td><code><?= htmlspecialchars($s['subnet_id']) ?></code></td>
                                            <td><?= htmlspecialchars($s['name']) ?></td>
                                            <td><span class="badge badge-<?= cast_subnet_type_badge($s['subnet_type']) ?>"><?= $s['subnet_type'] ?></span></td>
                                            <td><?= htmlspecialchars($s['az']) ?></td>
                                            <td><code><?= htmlspecialchars($s['cidr']) ?></code></td>
                                            <td>
                                                <button class="btn btn-xs btn-warning btn-edit" data-type="subnet" data-id="<?= $s['id'] ?>"><i class="fas fa-edit"></i></button>
                                                <button class="btn btn-xs btn-danger btn-delete" data-type="subnet" data-id="<?= $s['id'] ?>"><i class="fas fa-trash"></i></button>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                                
                                <hr>
                                <h5>Subnet Pairs</h5>
                                <div class="mb-3">
                                    <button class="btn btn-success btn-sm" data-toggle="modal" data-target="#modal-pair">
                                        <i class="fas fa-plus"></i> Add Pair
                                    </button>
                                </div>
                                <table class="table table-sm table-striped">
                                    <thead>
                                        <tr><th>Name</th><th>MGT Subnet</th><th>BAIT Subnet</th><th>AZ</th><th>Actions</th></tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($subnet_pairs as $p): ?>
                                        <tr data-id="<?= $p['id'] ?>">
                                            <td><?= htmlspecialchars($p['name']) ?></td>
                                            <td><?= htmlspecialchars($p['mgt_name']) ?> <small class="text-muted">(<?= $p['mgt_cidr'] ?>)</small></td>
                                            <td><?= htmlspecialchars($p['bait_name']) ?> <small class="text-muted">(<?= $p['bait_cidr'] ?>)</small></td>
                                            <td><?= htmlspecialchars($p['az']) ?></td>
                                            <td>
                                                <button class="btn btn-xs btn-danger btn-delete" data-type="pair" data-id="<?= $p['id'] ?>"><i class="fas fa-trash"></i></button>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <!-- Security Groups Tab -->
                            <div class="tab-pane fade" id="tab-security">
                                <div class="mb-3">
                                    <button class="btn btn-success btn-sm" data-toggle="modal" data-target="#modal-sg">
                                        <i class="fas fa-plus"></i> Add Security Group
                                    </button>
                                    <button class="btn btn-info btn-sm" id="btn-import-sgs">
                                        <i class="fab fa-aws"></i> Import from AWS
                                    </button>
                                </div>
                                <table class="table table-sm table-striped">
                                    <thead>
                                        <tr><th>SG ID</th><th>Name</th><th>Type</th><th>Description</th><th>Actions</th></tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($security_groups as $sg): ?>
                                        <tr data-id="<?= $sg['id'] ?>">
                                            <td><code><?= htmlspecialchars($sg['sg_id']) ?></code></td>
                                            <td><?= htmlspecialchars($sg['name']) ?></td>
                                            <td><span class="badge badge-<?= cast_sg_type_badge($sg['sg_type']) ?>"><?= $sg['sg_type'] ?></span></td>
                                            <td><?= htmlspecialchars($sg['description']) ?></td>
                                            <td>
                                                <button class="btn btn-xs btn-warning btn-edit" data-type="sg" data-id="<?= $sg['id'] ?>"><i class="fas fa-edit"></i></button>
                                                <button class="btn btn-xs btn-danger btn-delete" data-type="sg" data-id="<?= $sg['id'] ?>"><i class="fas fa-trash"></i></button>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <!-- Elastic IPs Tab -->
                            <div class="tab-pane fade" id="tab-eips">
                                <div class="mb-3">
                                    <button class="btn btn-success btn-sm" data-toggle="modal" data-target="#modal-eip">
                                        <i class="fas fa-plus"></i> Add EIP
                                    </button>
                                    <button class="btn btn-info btn-sm" id="btn-import-eips">
                                        <i class="fab fa-aws"></i> Import from AWS
                                    </button>
                                </div>
                                <table class="table table-sm table-striped">
                                    <thead>
                                        <tr><th>EIP</th><th>Allocation ID</th><th>Type</th><th>Status</th><th>Assigned To</th><th>Actions</th></tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($eips as $e): 
                                            $available = empty($e['assigned_to']);
                                        ?>
                                        <tr data-id="<?= $e['id'] ?>">
                                            <td><code><?= htmlspecialchars($e['eip']) ?></code></td>
                                            <td><code><?= htmlspecialchars($e['allocation_id']) ?></code></td>
                                            <td><span class="badge badge-<?= $e['eip_type'] === 'bait-pool' ? 'primary' : 'secondary' ?>"><?= $e['eip_type'] ?></span></td>
                                            <td><span class="badge badge-<?= $available ? 'success' : 'warning' ?>"><?= $available ? 'Available' : 'In Use' ?></span></td>
                                            <td><?= htmlspecialchars($e['assigned_to'] ?? '-') ?></td>
                                            <td>
                                                <button class="btn btn-xs btn-warning btn-edit" data-type="eip" data-id="<?= $e['id'] ?>"><i class="fas fa-edit"></i></button>
                                                <button class="btn btn-xs btn-danger btn-delete" data-type="eip" data-id="<?= $e['id'] ?>" <?= !$available ? 'disabled' : '' ?>><i class="fas fa-trash"></i></button>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <!-- Instance Types Tab -->
                            <div class="tab-pane fade" id="tab-instances">
                                <div class="mb-3">
                                    <button class="btn btn-success btn-sm" data-toggle="modal" data-target="#modal-instance-type">
                                        <i class="fas fa-plus"></i> Add Instance Type
                                    </button>
                                </div>
                                <table class="table table-sm table-striped">
                                    <thead>
                                        <tr><th>Type</th><th>Description</th><th>Default</th><th>Actions</th></tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($instance_types as $t): ?>
                                        <tr data-id="<?= $t['id'] ?>">
                                            <td><code><?= htmlspecialchars($t['instance_type']) ?></code></td>
                                            <td><?= htmlspecialchars($t['description']) ?></td>
                                            <td>
                                                <input type="radio" name="default_instance" value="<?= $t['id'] ?>" 
                                                       class="set-default-instance" <?= $t['is_default'] ? 'checked' : '' ?>>
                                            </td>
                                            <td>
                                                <button class="btn btn-xs btn-danger btn-delete" data-type="instance_type" data-id="<?= $t['id'] ?>"><i class="fas fa-trash"></i></button>
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
        </section>
    </div>

    <footer class="main-footer">
        <strong>LURE Dashboard &copy; 2026</strong>
    </footer>
</div>

<!-- Modals -->
<?php include 'includes/config-modals.php'; ?>

<script src="plugins/jquery/jquery.min.js"></script>
<script src="plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="plugins/toastr/toastr.min.js"></script>
<script src="plugins/sweetalert2/sweetalert2.min.js"></script>
<script src="dist/js/adminlte.min.js"></script>

<script>
$(function() {
    // Toast config
    toastr.options = { positionClass: 'toast-top-right', timeOut: 3000 };
    
    // Save config forms
    $('.config-form').on('submit', function(e) {
        e.preventDefault();
        const form = $(this);
        const category = form.data('category');
        
        $.post('../api/cast-config.php', form.serialize() + '&action=save&category=' + category)
            .done(function(r) {
                if (r.success) {
                    toastr.success('Settings saved');
                } else {
                    toastr.error(r.error || 'Save failed');
                }
            })
            .fail(function() { toastr.error('Request failed'); });
    });
    
    // Test AWS
    $('#btn-test-aws').click(function() {
        const btn = $(this).prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Testing...');
        $.post('../api/cast-config.php', { action: 'test_aws' })
            .done(function(r) {
                if (r.success) {
                    toastr.success('AWS connection OK - Account: ' + r.account);
                } else {
                    toastr.error(r.error || 'Connection failed');
                }
            })
            .always(function() {
                btn.prop('disabled', false).html('<i class="fas fa-plug"></i> Test Connection');
            });
    });
    
    // Import from AWS
    $('[id^="btn-import-"]').click(function() {
        const type = $(this).attr('id').replace('btn-import-', '');
        const btn = $(this).prop('disabled', true);
        
        $.post('../api/cast-config.php', { action: 'import', type: type })
            .done(function(r) {
                if (r.success) {
                    toastr.success('Imported ' + r.count + ' ' + type);
                    if (r.count > 0) location.reload();
                } else {
                    toastr.error(r.error || 'Import failed');
                }
            })
            .always(function() { btn.prop('disabled', false); });
    });
    
    // Delete resource
    $('.btn-delete').click(function() {
        const type = $(this).data('type');
        const id = $(this).data('id');
        const row = $(this).closest('tr');
        
        Swal.fire({
            title: 'Delete?',
            text: 'This will remove the resource from configuration.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33'
        }).then((result) => {
            if (result.isConfirmed) {
                $.post('../api/cast-config.php', { action: 'delete', type: type, id: id })
                    .done(function(r) {
                        if (r.success) {
                            row.fadeOut(function() { $(this).remove(); });
                            toastr.success('Deleted');
                        } else {
                            toastr.error(r.error || 'Delete failed');
                        }
                    });
            }
        });
    });
    
    // Edit resource
    $('.btn-edit').click(function() {
        const type = $(this).data('type');
        const id = $(this).data('id');
        
        $.get('../api/cast-config.php', { action: 'get', type: type, id: id })
            .done(function(r) {
                if (r.success) {
                    openEditModal(type, r.data);
                } else {
                    toastr.error(r.error || 'Failed to load');
                }
            });
    });
    
    function openEditModal(type, data) {
        const modalMap = { subnet: '#modal-subnet', sg: '#modal-sg', eip: '#modal-eip' };
        const modal = $(modalMap[type]);
        if (!modal.length) return;
        
        modal.find('[name="id"]').val(data.id);
        Object.keys(data).forEach(function(key) {
            modal.find('[name="' + key + '"]').val(data[key]);
        });
        modal.find('.modal-title').text('Edit ' + type);
        modal.modal('show');
    }
    
    // Add/Edit resource forms
    $('.resource-form').on('submit', function(e) {
        e.preventDefault();
        const form = $(this);
        const modal = form.closest('.modal');
        
        $.post('../api/cast-config.php', form.serialize())
            .done(function(r) {
                if (r.success) {
                    toastr.success('Saved');
                    location.reload();
                } else {
                    toastr.error(r.error || 'Save failed');
                }
            });
    });
    
    // Set default instance type
    $('.set-default-instance').change(function() {
        const id = $(this).val();
        $.post('../api/cast-config.php', { action: 'set_default_instance', id: id })
            .done(function(r) {
                if (r.success) toastr.success('Default updated');
            });
    });
    
    // Tab persistence
    if (location.hash) {
        $('a[href="' + location.hash + '"]').tab('show');
    }
    $('a[data-toggle="pill"]').on('shown.bs.tab', function(e) {
        history.replaceState(null, null, e.target.hash);
    });
});
</script>
</body>
</html>
