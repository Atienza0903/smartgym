<?php
require __DIR__ . '/includes/bootstrap.php';
$user = require_role('admin', 'staff');
$page_title = 'Equipment Maintenance';
$equipStatuses = ['operational', 'needs_repair', 'under_maintenance', 'out_of_service'];
$ticketToEquip = ['reported' => 'needs_repair', 'in_progress' => 'under_maintenance', 'resolved' => 'operational'];

if (is_post()) {
    verify_csrf();
    $action = post('action');
    $nul = fn($v) => $v === '' ? null : $v;
    if ($action === 'save_equipment') {
        $id = (int)post('id');
        $vals = [post('name'), $nul(post('category')), $nul(post('serial_no')), $nul(post('location')), in_array(post('status'), $equipStatuses, true) ? post('status') : 'operational', $nul(post('purchase_date')), $nul(post('notes'))];
        if ($vals[0] === '') {
            flash('danger', 'Equipment name is required.');
        } elseif ($id) {
            q('UPDATE equipment SET name=?, category=?, serial_no=?, location=?, status=?, purchase_date=?, notes=? WHERE id=?', array_merge($vals, [$id]));
            flash('success', 'Equipment updated.');
        } else {
            q('INSERT INTO equipment (name, category, serial_no, location, status, purchase_date, notes) VALUES (?,?,?,?,?,?,?)', $vals);
            flash('success', 'Equipment added.');
        }
    } elseif ($action === 'delete_equipment' && $user['role'] === 'admin') {
        q('DELETE FROM equipment WHERE id = ?', [(int)post('id')]);
        flash('success', 'Equipment deleted.');
    } elseif ($action === 'report') {
        $eid = (int)post('equipment_id');
        $priority = in_array(post('priority'), ['low', 'medium', 'high', 'critical'], true) ? post('priority') : 'medium';
        if (post('issue') === '' || !q('SELECT id FROM equipment WHERE id = ?', [$eid])->fetch()) {
            flash('danger', 'Select equipment and describe the issue.');
        } else {
            q('INSERT INTO maintenance_logs (equipment_id, reported_by, issue, priority, reported_at) VALUES (?,?,?,?,?)', [$eid, $user['id'], post('issue'), $priority, now()]);
            q("UPDATE equipment SET status = ? WHERE id = ? AND status = 'operational'", [$priority === 'critical' ? 'out_of_service' : 'needs_repair', $eid]);
            flash('success', 'Issue reported. Maintenance ticket created.');
        }
    } elseif ($action === 'update_ticket') {
        $t = q('SELECT * FROM maintenance_logs WHERE id = ?', [(int)post('id')])->fetch();
        $st = post('status');
        if ($t && isset($ticketToEquip[$st])) {
            $resolvedAt = $st === 'resolved' ? ($t['resolved_at'] ?: now()) : null;
            q('UPDATE maintenance_logs SET status=?, action_taken=?, cost=?, resolved_at=? WHERE id=?', [$st, $nul(post('action_taken')), post('cost') === '' ? null : (float)post('cost'), $resolvedAt, $t['id']]);
            $openOthers = (int)q("SELECT COUNT(*) FROM maintenance_logs WHERE equipment_id = ? AND status <> 'resolved' AND id <> ?", [$t['equipment_id'], $t['id']])->fetchColumn();
            if ($st !== 'resolved' || !$openOthers) {
                q('UPDATE equipment SET status = ?' . ($st === 'resolved' ? ', last_maintenance = CURDATE()' : '') . ' WHERE id = ?', [$ticketToEquip[$st], $t['equipment_id']]);
            }
            flash('success', 'Maintenance ticket updated.');
        }
    }
    redirect('equipment.php' . (post('tab') ? '?tab=' . urlencode(post('tab')) : ''));
}

$tab = get('tab', 'equipment');
$equipment = q("SELECT e.*, (SELECT COUNT(*) FROM maintenance_logs t WHERE t.equipment_id = e.id AND t.status <> 'resolved') open_tickets FROM equipment e ORDER BY e.category, e.name")->fetchAll();
$tickets = q('SELECT t.*, e.name equipment, u.full_name reporter FROM maintenance_logs t JOIN equipment e ON e.id = t.equipment_id LEFT JOIN users u ON u.id = t.reported_by
    ORDER BY (t.status = \'resolved\'), FIELD(t.priority,\'critical\',\'high\',\'medium\',\'low\'), t.reported_at DESC LIMIT 200')->fetchAll();
$counts = array_count_values(array_column($equipment, 'status'));
require __DIR__ . '/includes/header.php';
?>
<div class="row g-3 mb-3">
    <?php foreach ([['operational', 'Operational', 'check-circle', 'bg-green-soft'], ['needs_repair', 'Needs Repair', 'exclamation-triangle', 'bg-amber-soft'], ['under_maintenance', 'Under Maintenance', 'wrench', 'bg-blue-soft'], ['out_of_service', 'Out of Service', 'x-octagon', 'bg-red-soft']] as [$k, $l, $i, $c]): ?>
        <div class="col-6 col-lg-3"><div class="card stat-card"><div class="stat-icon <?= $c ?>"><i class="bi bi-<?= $i ?>"></i></div><div><div class="stat-value"><?= $counts[$k] ?? 0 ?></div><div class="stat-label"><?= $l ?></div></div></div></div>
    <?php endforeach; ?>
</div>
<ul class="nav nav-tabs mb-0">
    <li class="nav-item"><a class="nav-link <?= $tab === 'equipment' ? 'active' : '' ?>" href="?tab=equipment"><i class="bi bi-tools"></i> Equipment</a></li>
    <li class="nav-item"><a class="nav-link <?= $tab === 'tickets' ? 'active' : '' ?>" href="?tab=tickets"><i class="bi bi-wrench-adjustable"></i> Maintenance Tickets</a></li>
</ul>
<div class="card" style="border-top-left-radius:0">
    <div class="card-header">
        <?php if ($tab === 'equipment'): ?>
            <i class="bi bi-tools"></i> Equipment Inventory
            <button class="btn btn-sm btn-primary ms-auto" data-bs-toggle="modal" data-bs-target="#equipModal" onclick="editEquip({})"><i class="bi bi-plus"></i> Add Equipment</button>
        <?php else: ?>
            <i class="bi bi-wrench-adjustable"></i> Maintenance Tickets
        <?php endif; ?>
        <button class="btn btn-sm btn-outline-warning <?= $tab === 'equipment' ? '' : 'ms-auto' ?>" data-bs-toggle="modal" data-bs-target="#reportModal"><i class="bi bi-exclamation-triangle"></i> Report Issue</button>
    </div>
    <div class="table-responsive">
    <?php if ($tab === 'equipment'): ?>
        <table class="table table-hover">
            <thead><tr><th>Name</th><th>Category</th><th>Serial #</th><th>Location</th><th>Status</th><th>Last Maintenance</th><th>Open Tickets</th><th class="text-end">Actions</th></tr></thead>
            <tbody>
            <?php if (!$equipment): ?><tr><td colspan="8"><div class="empty"><i class="bi bi-tools"></i>No equipment yet.</div></td></tr><?php endif; ?>
            <?php foreach ($equipment as $eq): ?>
                <tr>
                    <td class="fw-semibold"><?= e($eq['name']) ?></td><td><?= e($eq['category']) ?></td><td class="small text-secondary"><?= e($eq['serial_no']) ?></td><td><?= e($eq['location']) ?></td>
                    <td><?= equipment_badge($eq['status']) ?></td><td><?= fmt_date($eq['last_maintenance']) ?></td>
                    <td><?= $eq['open_tickets'] ? badge($eq['open_tickets'], 'danger') : '<span class="text-secondary">0</span>' ?></td>
                    <td class="text-end text-nowrap">
                        <button class="btn btn-sm btn-outline-warning" title="Report issue" data-bs-toggle="modal" data-bs-target="#reportModal" onclick="document.getElementById('rep_eq').value='<?= $eq['id'] ?>'"><i class="bi bi-exclamation-triangle"></i></button>
                        <button class="btn btn-sm btn-outline-light" title="Edit" data-bs-toggle="modal" data-bs-target="#equipModal" onclick='editEquip(<?= e(json_encode($eq)) ?>)'><i class="bi bi-pencil"></i></button>
                        <?php if ($user['role'] === 'admin'): ?>
                            <form method="post" class="d-inline" data-confirm="Delete <?= e($eq['name']) ?> and its maintenance history?"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $eq['id'] ?>"><button name="action" value="delete_equipment" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button></form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <table class="table table-hover">
            <thead><tr><th>#</th><th>Equipment</th><th>Issue</th><th>Priority</th><th>Status</th><th>Reported</th><th>Action Taken</th><th>Cost</th><th></th></tr></thead>
            <tbody>
            <?php if (!$tickets): ?><tr><td colspan="9"><div class="empty"><i class="bi bi-check2-circle"></i>No maintenance tickets.</div></td></tr><?php endif; ?>
            <?php foreach ($tickets as $t): ?>
                <tr>
                    <td class="text-secondary">#<?= $t['id'] ?></td><td class="fw-semibold"><?= e($t['equipment']) ?></td><td class="small" style="max-width:260px"><?= e($t['issue']) ?></td>
                    <td><?= priority_badge($t['priority']) ?></td><td><?= ticket_badge($t['status']) ?></td>
                    <td class="small text-nowrap"><?= fmt_datetime($t['reported_at']) ?><br><span class="text-secondary">by <?= e($t['reporter'] ?? '—') ?></span></td>
                    <td class="small" style="max-width:220px"><?= e($t['action_taken'] ?: '—') ?><?= $t['resolved_at'] ? '<br><span class="text-success">Resolved ' . fmt_date($t['resolved_at']) . '</span>' : '' ?></td>
                    <td class="text-nowrap"><?= $t['cost'] !== null ? peso($t['cost']) : '—' ?></td>
                    <td><button class="btn btn-sm btn-outline-light" data-bs-toggle="modal" data-bs-target="#ticketModal" onclick='editTicket(<?= e(json_encode($t)) ?>)'><i class="bi bi-pencil-square"></i></button></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
    </div>
</div>

<div class="modal fade" id="equipModal" tabindex="-1"><div class="modal-dialog"><form method="post" class="modal-content"><?= csrf_field() ?>
    <input type="hidden" name="action" value="save_equipment"><input type="hidden" name="tab" value="equipment"><input type="hidden" name="id" id="eq_id">
    <div class="modal-header"><h5 class="modal-title" id="eq_title">Equipment</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body row g-2">
        <div class="col-12"><label class="form-label">Name *</label><input name="name" id="eq_name" class="form-control" required></div>
        <div class="col-6"><label class="form-label">Category</label><input name="category" id="eq_category" class="form-control" list="catList"><datalist id="catList"><option>Cardio</option><option>Strength</option><option>Free Weights</option><option>Accessories</option></datalist></div>
        <div class="col-6"><label class="form-label">Serial #</label><input name="serial_no" id="eq_serial_no" class="form-control"></div>
        <div class="col-6"><label class="form-label">Location</label><input name="location" id="eq_location" class="form-control"></div>
        <div class="col-6"><label class="form-label">Purchase Date</label><input type="date" name="purchase_date" id="eq_purchase_date" class="form-control"></div>
        <div class="col-12"><label class="form-label">Status</label><select name="status" id="eq_status" class="form-select"><?php foreach ($equipStatuses as $s): ?><option value="<?= $s ?>"><?= ucwords(str_replace('_', ' ', $s)) ?></option><?php endforeach; ?></select></div>
        <div class="col-12"><label class="form-label">Notes</label><input name="notes" id="eq_notes" class="form-control"></div>
    </div>
    <div class="modal-footer"><button type="button" class="btn btn-outline-light" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Save</button></div>
</form></div></div>

<div class="modal fade" id="reportModal" tabindex="-1"><div class="modal-dialog"><form method="post" class="modal-content"><?= csrf_field() ?>
    <input type="hidden" name="action" value="report"><input type="hidden" name="tab" value="tickets">
    <div class="modal-header"><h5 class="modal-title"><i class="bi bi-exclamation-triangle text-warning"></i> Report Damaged Equipment</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body row g-2">
        <div class="col-12"><label class="form-label">Equipment</label><select name="equipment_id" id="rep_eq" class="form-select" required><option value="">Select…</option><?php foreach ($equipment as $eq): ?><option value="<?= $eq['id'] ?>"><?= e($eq['name']) ?></option><?php endforeach; ?></select></div>
        <div class="col-12"><label class="form-label">Priority</label><select name="priority" class="form-select"><option value="low">Low</option><option value="medium" selected>Medium</option><option value="high">High</option><option value="critical">Critical (take out of service)</option></select></div>
        <div class="col-12"><label class="form-label">Issue Description</label><textarea name="issue" class="form-control" rows="3" required placeholder="e.g. Belt slipping at high speed, loud noise"></textarea></div>
    </div>
    <div class="modal-footer"><button type="button" class="btn btn-outline-light" data-bs-dismiss="modal">Cancel</button><button class="btn btn-warning">Submit Report</button></div>
</form></div></div>

<div class="modal fade" id="ticketModal" tabindex="-1"><div class="modal-dialog"><form method="post" class="modal-content"><?= csrf_field() ?>
    <input type="hidden" name="action" value="update_ticket"><input type="hidden" name="tab" value="tickets"><input type="hidden" name="id" id="tk_id">
    <div class="modal-header"><h5 class="modal-title">Update Ticket <span id="tk_title" class="text-secondary"></span></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body row g-2">
        <div class="col-12"><div class="small text-secondary" id="tk_issue"></div></div>
        <div class="col-6"><label class="form-label">Status</label><select name="status" id="tk_status" class="form-select"><option value="reported">Reported</option><option value="in_progress">In Progress</option><option value="resolved">Resolved</option></select></div>
        <div class="col-6"><label class="form-label">Cost (₱)</label><input type="number" step="0.01" min="0" name="cost" id="tk_cost" class="form-control"></div>
        <div class="col-12"><label class="form-label">Action Taken</label><textarea name="action_taken" id="tk_action" class="form-control" rows="3"></textarea></div>
    </div>
    <div class="modal-footer"><button type="button" class="btn btn-outline-light" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Update</button></div>
</form></div></div>
<?php
$page_scripts = <<<'JS'
<script>
function editEquip(eq) {
  document.getElementById('eq_title').textContent = eq.id ? 'Edit Equipment' : 'Add Equipment';
  ['id', 'name', 'category', 'serial_no', 'location', 'purchase_date', 'notes'].forEach(f => document.getElementById('eq_' + f).value = eq[f] || '');
  document.getElementById('eq_status').value = eq.status || 'operational';
}
function editTicket(t) {
  document.getElementById('tk_id').value = t.id;
  document.getElementById('tk_title').textContent = '#' + t.id + ' · ' + t.equipment;
  document.getElementById('tk_issue').textContent = t.issue;
  document.getElementById('tk_status').value = t.status;
  document.getElementById('tk_cost').value = t.cost || '';
  document.getElementById('tk_action').value = t.action_taken || '';
}
</script>
JS;
require __DIR__ . '/includes/footer.php';
