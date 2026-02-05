<?php
require_once 'includes/auth.php';
require_once 'includes/cast.php';
require_login();

$subnet_pairs = cast_get_subnet_pairs();
$instance_types = cast_get_instance_types();
$available_eips = cast_get_eips('bait-pool', true);
$next_hostname = cast_get_next_hostname();

// Check prerequisites
$errors = [];
$ami = cast_get_config('aws', 'lure_ami_id', '');
if (empty($ami)) $errors[] = 'Lure AMI ID not configured';
if (empty($subnet_pairs)) $errors[] = 'No subnet pairs configured';
if (empty($available_eips)) $errors[] = 'No available EIPs in pool';

$mgt_sg = db_fetch_one("SELECT sg_id FROM cast_security_groups WHERE sg_type='lure-mgt' AND is_active=1");
$bait_sg = db_fetch_one("SELECT sg_id FROM cast_security_groups WHERE sg_type='lure-bait' AND is_active=1");
if (!$mgt_sg) $errors[] = 'No lure-mgt security group configured';
if (!$bait_sg) $errors[] = 'No lure-bait security group configured';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LURE - Cast</title>
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
                        <h1 class="m-0"><i class="fas fa-rocket text-primary"></i> Cast - Deploy Lure</h1>
                    </div>
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-right">
                            <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
                            <li class="breadcrumb-item"><a href="health.php">Health</a></li>
                            <li class="breadcrumb-item active">Cast</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>

        <section class="content">
            <div class="container-fluid">
                <div class="row">
                    <div class="col-lg-8">
                        
                        <?php if (!empty($errors)): ?>
                        <div class="alert alert-warning">
                            <h5><i class="icon fas fa-exclamation-triangle"></i> Prerequisites Missing</h5>
                            <ul class="mb-0">
                                <?php foreach ($errors as $e): ?>
                                <li><?= htmlspecialchars($e) ?></li>
                                <?php endforeach; ?>
                            </ul>
                            <a href="configuration.php" class="btn btn-warning btn-sm mt-2">Go to Configuration</a>
                        </div>
                        <?php endif; ?>
                        
                        <div class="card card-primary">
                            <div class="card-header">
                                <h3 class="card-title"><i class="fas fa-plus-circle"></i> Deploy New Lure</h3>
                            </div>
                            <form id="form-deploy">
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label>Hostname</label>
                                                <input type="text" class="form-control" name="hostname" id="hostname"
                                                       value="<?= htmlspecialchars($next_hostname) ?>" required>
                                                <small class="text-muted">Auto-generated, can be modified</small>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label>Instance Type</label>
                                                <select class="form-control" name="instance_type" required>
                                                    <?php foreach ($instance_types as $t): ?>
                                                    <option value="<?= htmlspecialchars($t['instance_type']) ?>" <?= $t['is_default'] ? 'selected' : '' ?>>
                                                        <?= htmlspecialchars($t['instance_type']) ?>
                                                        <?php if ($t['description']): ?> - <?= htmlspecialchars($t['description']) ?><?php endif; ?>
                                                    </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label>Subnet Pair</label>
                                                <select class="form-control" name="subnet_pair_id" id="subnet_pair" required>
                                                    <option value="">Select subnet pair...</option>
                                                    <?php foreach ($subnet_pairs as $p): ?>
                                                    <option value="<?= $p['id'] ?>" 
                                                            data-mgt="<?= htmlspecialchars($p['mgt_subnet_id']) ?>"
                                                            data-bait="<?= htmlspecialchars($p['bait_subnet_id']) ?>">
                                                        <?= htmlspecialchars($p['name']) ?> (<?= $p['az'] ?>)
                                                    </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label>BAIT Elastic IP</label>
                                                <div class="input-group">
                                                    <select class="form-control" name="eip_allocation_id" id="eip_select" required>
                                                        <option value="">Select EIP...</option>
                                                        <?php foreach ($available_eips as $e): ?>
                                                        <option value="<?= htmlspecialchars($e['allocation_id']) ?>">
                                                            <?= htmlspecialchars($e['eip']) ?>
                                                        </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                    <div class="input-group-append">
                                                        <button type="button" class="btn btn-info" id="btn-sync-eips" title="Sync EIPs with AWS">
                                                            <i class="fas fa-sync-alt"></i>
                                                        </button>
                                                        <button type="button" class="btn btn-success" id="btn-allocate-eip" title="Allocate new EIP">
                                                            <i class="fas fa-plus"></i>
                                                        </button>
                                                    </div>
                                                </div>
                                                <small class="text-muted"><span id="eip-count"><?= count($available_eips) ?></span> available</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="card-footer">
                                    <button type="submit" class="btn btn-primary btn-lg" id="btn-deploy" <?= !empty($errors) ? 'disabled' : '' ?>>
                                        <i class="fas fa-rocket"></i> Deploy Lure
                                    </button>
                                </div>
                            </form>
                        </div>
                        
                        <div class="card card-dark" id="log-card" style="display: none;">
                            <div class="card-header">
                                <h3 class="card-title"><i class="fas fa-terminal"></i> Deployment Log</h3>
                            </div>
                            <div class="card-body p-0">
                                <pre id="deploy-log" style="max-height: 400px; overflow-y: auto; background: #1e1e1e; color: #d4d4d4; margin: 0; padding: 15px;"></pre>
                            </div>
                        </div>
                        
                    </div>
                    
                    <div class="col-lg-4">
                        <div class="card card-outline card-info">
                            <div class="card-header">
                                <h3 class="card-title"><i class="fas fa-info-circle"></i> Deployment Info</h3>
                            </div>
                            <div class="card-body">
                                <h6>What Happens</h6>
                                <ol class="small text-muted pl-3">
                                    <li>EC2 instance launched with 2 ENIs</li>
                                    <li>MGT interface → management SG</li>
                                    <li>BAIT interface → bait SG + EIP</li>
                                    <li>SSH configuration (hostname, rsyslog, nftables)</li>
                                    <li>Registered in health monitoring</li>
                                </ol>
                                <hr>
                                <h6>Current Config</h6>
                                <table class="table table-sm table-borderless small mb-0">
                                    <tr><td class="text-muted">AMI:</td><td><code><?= htmlspecialchars($ami ?: 'Not set') ?></code></td></tr>
                                    <tr><td class="text-muted">Region:</td><td><code><?= htmlspecialchars(cast_get_config('aws', 'region', 'us-east-2')) ?></code></td></tr>
                                    <tr><td class="text-muted">MGT SG:</td><td><code><?= htmlspecialchars($mgt_sg['sg_id'] ?? 'Not set') ?></code></td></tr>
                                    <tr><td class="text-muted">BAIT SG:</td><td><code><?= htmlspecialchars($bait_sg['sg_id'] ?? 'Not set') ?></code></td></tr>
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

<script src="plugins/jquery/jquery.min.js"></script>
<script src="plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="plugins/toastr/toastr.min.js"></script>
<script src="plugins/sweetalert2/sweetalert2.min.js"></script>
<script src="dist/js/adminlte.min.js"></script>

<script>
$(function() {
    toastr.options = { positionClass: 'toast-top-right', timeOut: 5000 };
    
    const logCard = $('#log-card');
    const logPre = $('#deploy-log');
    
    function log(msg, type) {
        const ts = new Date().toLocaleTimeString();
        const color = type === 'error' ? '#f44' : type === 'success' ? '#4f4' : '#aaa';
        logPre.append('<span style="color:' + color + '">[' + ts + ']</span> ' + msg + '\n');
        logPre.scrollTop(logPre[0].scrollHeight);
    }
    
    // Sync EIPs with AWS
    $('#btn-sync-eips').click(function() {
        const btn = $(this);
        btn.prop('disabled', true).html('<i class="fas fa-sync-alt fa-spin"></i>');
        
        $.post('../api/cast-sync-eips.php')
            .done(function(r) {
                if (r.success) {
                    // Reload the EIP dropdown with fresh data from DB
                    $.get('../api/cast-config.php', { action: 'get_available_eips' })
                        .done(function(eipData) {
                            if (eipData.success) {
                                const select = $('#eip_select');
                                select.find('option:not(:first)').remove();
                                eipData.eips.forEach(function(e) {
                                    select.append($('<option>').val(e.allocation_id).text(e.eip));
                                });
                                $('#eip-count').text(eipData.eips.length);
                                
                                // Update prerequisite warning
                                if (eipData.eips.length > 0) {
                                    $('.alert-warning li:contains("No available EIPs")').remove();
                                    if ($('.alert-warning li').length === 0) {
                                        $('.alert-warning').remove();
                                        $('#btn-deploy').prop('disabled', false);
                                    }
                                }
                            }
                        });
                    
                    let msg = 'EIP sync complete: ';
                    let parts = [];
                    if (r.added > 0) parts.push(r.added + ' added');
                    if (r.updated > 0) parts.push(r.updated + ' updated');
                    if (r.removed > 0) parts.push(r.removed + ' removed');
                    msg += parts.length > 0 ? parts.join(', ') : 'already in sync';
                    msg += ' (' + r.available_count + ' available)';
                    
                    toastr.success(msg);
                } else {
                    toastr.error(r.error || 'Sync failed');
                }
            })
            .fail(function() {
                toastr.error('Sync request failed');
            })
            .always(function() {
                btn.prop('disabled', false).html('<i class="fas fa-sync-alt"></i>');
            });
    });
    
    // Allocate new EIP
    $('#btn-allocate-eip').click(function() {
        const btn = $(this);
        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');
        
        $.post('../api/cast-allocate-eip.php')
            .done(function(r) {
                if (r.success) {
                    const option = $('<option>')
                        .val(r.allocation_id)
                        .text(r.eip)
                        .prop('selected', true);
                    $('#eip_select').append(option);
                    
                    const count = parseInt($('#eip-count').text()) + 1;
                    $('#eip-count').text(count);
                    
                    $('.alert-warning li:contains("No available EIPs")').remove();
                    if ($('.alert-warning li').length === 0) {
                        $('.alert-warning').remove();
                        $('#btn-deploy').prop('disabled', false);
                    }
                    
                    toastr.success('Allocated ' + r.eip);
                } else {
                    toastr.error(r.error || 'Failed to allocate EIP');
                }
            })
            .fail(function() {
                toastr.error('Request failed');
            })
            .always(function() {
                btn.prop('disabled', false).html('<i class="fas fa-plus"></i>');
            });
    });
    
    $('#form-deploy').on('submit', function(e) {
        e.preventDefault();
        
        const form = $(this);
        const btn = $('#btn-deploy');
        const pair = $('#subnet_pair option:selected');
        
        if (!pair.val()) {
            toastr.error('Please select a subnet pair');
            return;
        }
        
        // IMPORTANT: Serialize BEFORE disabling fields
        const data = form.serialize() + 
            '&mgt_subnet=' + pair.data('mgt') + 
            '&bait_subnet=' + pair.data('bait');
        
        // Now disable form
        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Deploying...');
        form.find('input, select').prop('disabled', true);
        
        logCard.show();
        logPre.empty();
        log('Starting deployment...', 'info');
        
        $.post('../api/cast-deploy.php', data)
            .done(function(r) {
                if (r.success) {
                    log('Instance launched: ' + r.instance_id, 'success');
                    log('MGT IP: ' + r.mgt_ip, 'success');
                    log('BAIT EIP: ' + r.bait_eip, 'success');
                    
                    if (r.configuring) {
                        log('Post-deploy configuration started...', 'info');
                        pollStatus(r.hostname);
                    } else {
                        log('Complete! (Manual config may be needed)', 'success');
                    }
                    
                    toastr.success(r.hostname + ' deployed — MGT: ' + r.mgt_ip + ', BAIT: ' + r.bait_eip, 'Lure Deployed');
                    
                    // Re-enable deploy button for another deployment
                    btn.prop('disabled', false).html('<i class="fas fa-rocket"></i> Deploy Lure');
                    form.find('input, select').prop('disabled', false);
                    
                    // Remove the used EIP from dropdown and update count
                    $('#eip_select option:selected').remove();
                    $('#eip_select').val('');
                    var newCount = Math.max(0, parseInt($('#eip-count').text()) - 1);
                    $('#eip-count').text(newCount);
                    
                    // Increment hostname
                    var currentName = $('#hostname').val();
                    var match = currentName.match(/^(.*?)(\d+)$/);
                    if (match) {
                        $('#hostname').val(match[1] + (parseInt(match[2]) + 1));
                    }
                } else {
                    log('Failed: ' + r.error, 'error');
                    toastr.error(r.error || 'Deployment failed');
                    btn.prop('disabled', false).html('<i class="fas fa-rocket"></i> Deploy Lure');
                    form.find('input, select').prop('disabled', false);
                }
            })
            .fail(function() {
                log('Request failed', 'error');
                toastr.error('Request failed');
                btn.prop('disabled', false).html('<i class="fas fa-rocket"></i> Deploy Lure');
                form.find('input, select').prop('disabled', false);
            });
    });
    
    function pollStatus(hostname) {
        let attempts = 0;
        let lastLogLength = 0;
        
        function checkLog() {
            $.get('../api/cast-post-deploy-log.php', { hostname: hostname })
                .done(function(r) {
                    if (r.success && r.log && r.log.length > lastLogLength) {
                        var newContent = r.log.substring(lastLogLength);
                        lastLogLength = r.log.length;
                        newContent.split('\n').forEach(function(line) {
                            if (!line.trim()) return;
                            var color = '#aaa';
                            if (line.match(/\b(error|fail|fatal)\b/i)) color = '#f44';
                            else if (line.match(/\b(ok|success|done|complete|✓)\b/i)) color = '#4f4';
                            else if (line.match(/\b(skip|warn|waiting)\b/i)) color = '#fa0';
                            logPre.append('<span style="color:' + color + '">' + $('<span>').text(line).html() + '</span>\n');
                        });
                        logPre.scrollTop(logPre[0].scrollHeight);
                    }
                });
        }
        
        // Check immediately, then every 5 seconds
        checkLog();
        
        const poll = setInterval(function() {
            attempts++;
            checkLog();
            
            $.get('../api/cast-status.php', { hostname: hostname })
                .done(function(r) {
                    if (r.status === 'active') {
                        log('Configuration complete!', 'success');
                        clearInterval(poll);
                    } else if (attempts > 60) {
                        log('Config taking longer than expected, check health page', 'info');
                        clearInterval(poll);
                    }
                });
        }, 5000);
    }
});
</script>
</body>
</html>
