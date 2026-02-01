<!-- Subnet Modal -->
<div class="modal fade" id="modal-subnet">
    <div class="modal-dialog">
        <div class="modal-content">
            <form class="resource-form" action="save" data-type="subnet">
                <input type="hidden" name="action" value="save_resource">
                <input type="hidden" name="type" value="subnet">
                <input type="hidden" name="id" value="">
                <div class="modal-header">
                    <h5 class="modal-title">Add Subnet</h5>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label>Subnet ID *</label>
                        <input type="text" class="form-control" name="subnet_id" required placeholder="subnet-xxxxx">
                    </div>
                    <div class="form-group">
                        <label>Name</label>
                        <input type="text" class="form-control" name="name">
                    </div>
                    <div class="form-group">
                        <label>Type *</label>
                        <select class="form-control" name="subnet_type" required>
                            <option value="">Select...</option>
                            <option value="mgt">MGT (Management)</option>
                            <option value="bait">BAIT (Honeypot)</option>
                            <option value="em">EM (Event Manager)</option>
                            <option value="public">Public</option>
                        </select>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Availability Zone</label>
                                <input type="text" class="form-control" name="az" placeholder="us-east-2b">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>CIDR</label>
                                <input type="text" class="form-control" name="cidr" placeholder="10.0.1.0/24">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Subnet Pair Modal -->
<div class="modal fade" id="modal-pair">
    <div class="modal-dialog">
        <div class="modal-content">
            <form class="resource-form">
                <input type="hidden" name="action" value="save_resource">
                <input type="hidden" name="type" value="pair">
                <input type="hidden" name="id" value="">
                <div class="modal-header">
                    <h5 class="modal-title">Add Subnet Pair</h5>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label>Pair Name *</label>
                        <input type="text" class="form-control" name="name" required placeholder="Primary-2b">
                    </div>
                    <div class="form-group">
                        <label>MGT Subnet *</label>
                        <select class="form-control" name="mgt_subnet_id" required>
                            <option value="">Select...</option>
                            <?php foreach (cast_get_subnets('mgt') as $s): ?>
                            <option value="<?= htmlspecialchars($s['subnet_id']) ?>"><?= htmlspecialchars($s['name']) ?> (<?= $s['cidr'] ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>BAIT Subnet *</label>
                        <select class="form-control" name="bait_subnet_id" required>
                            <option value="">Select...</option>
                            <?php foreach (cast_get_subnets('bait') as $s): ?>
                            <option value="<?= htmlspecialchars($s['subnet_id']) ?>"><?= htmlspecialchars($s['name']) ?> (<?= $s['cidr'] ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Availability Zone</label>
                        <input type="text" class="form-control" name="az" placeholder="us-east-2b">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Security Group Modal -->
<div class="modal fade" id="modal-sg">
    <div class="modal-dialog">
        <div class="modal-content">
            <form class="resource-form">
                <input type="hidden" name="action" value="save_resource">
                <input type="hidden" name="type" value="sg">
                <input type="hidden" name="id" value="">
                <div class="modal-header">
                    <h5 class="modal-title">Add Security Group</h5>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label>Security Group ID *</label>
                        <input type="text" class="form-control" name="sg_id" required placeholder="sg-xxxxx">
                    </div>
                    <div class="form-group">
                        <label>Name</label>
                        <input type="text" class="form-control" name="name">
                    </div>
                    <div class="form-group">
                        <label>Type *</label>
                        <select class="form-control" name="sg_type" required>
                            <option value="">Select...</option>
                            <option value="lure-mgt">Lure MGT</option>
                            <option value="lure-bait">Lure BAIT</option>
                            <option value="em">Event Manager</option>
                            <option value="bastion">Bastion</option>
                            <option value="proxy">Proxy</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Description</label>
                        <textarea class="form-control" name="description" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- EIP Modal -->
<div class="modal fade" id="modal-eip">
    <div class="modal-dialog">
        <div class="modal-content">
            <form class="resource-form">
                <input type="hidden" name="action" value="save_resource">
                <input type="hidden" name="type" value="eip">
                <input type="hidden" name="id" value="">
                <div class="modal-header">
                    <h5 class="modal-title">Add Elastic IP</h5>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label>Elastic IP *</label>
                        <input type="text" class="form-control" name="eip" required placeholder="x.x.x.x">
                    </div>
                    <div class="form-group">
                        <label>Allocation ID *</label>
                        <input type="text" class="form-control" name="allocation_id" required placeholder="eipalloc-xxxxx">
                    </div>
                    <div class="form-group">
                        <label>Type *</label>
                        <select class="form-control" name="eip_type" required>
                            <option value="bait-pool">BAIT Pool</option>
                            <option value="bastion">Bastion</option>
                            <option value="proxy">Proxy</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Instance Type Modal -->
<div class="modal fade" id="modal-instance-type">
    <div class="modal-dialog">
        <div class="modal-content">
            <form class="resource-form">
                <input type="hidden" name="action" value="save_resource">
                <input type="hidden" name="type" value="instance_type">
                <input type="hidden" name="id" value="">
                <div class="modal-header">
                    <h5 class="modal-title">Add Instance Type</h5>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label>Instance Type *</label>
                        <input type="text" class="form-control" name="instance_type" required placeholder="t3.micro">
                    </div>
                    <div class="form-group">
                        <label>Description</label>
                        <input type="text" class="form-control" name="description" placeholder="2 vCPU, 1 GB RAM">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>
