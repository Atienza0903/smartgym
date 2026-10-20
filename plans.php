<?php
require __DIR__ . '/includes/bootstrap.php';
require_role('admin');
$page_title = 'Membership Plans';

if (is_post()) {
    verify_csrf();
    $action = post('action');
    $id = (int)post('id');
    if ($action === 'save') {
        $vals = [post('name'), max(1, (int)post('duration_days')), max(0, (float)post('price')), post('description') ?: null, post('is_active') === '1' ? 1 : 0];
        if ($vals[0] === '') {
            flash('danger', 'Plan name is required.');
        } elseif ($id) {
            q('UPDATE membership_plans SET name=?, duration_days=?, price=?, description=?, is_active=? WHERE id=?', array_merge($vals, [$id]));
            flash('success', 'Plan updated.');
        } else {
            q('INSERT INTO membership_plans (name, duration_days, price, description, is_active) VALUES (?,?,?,?,?)', $vals);
            flash('success', 'Plan created.');
        }
    } elseif ($action === 'delete') {
        $used = (int)q('SELECT COUNT(*) FROM members WHERE plan_id = ?', [$id])->fetchColumn();
        if ($used) {
            flash('warning', "Plan is used by $used member(s). Deactivate it instead.");
        } else {
            q('DELETE FROM membership_plans WHERE id = ?', [$id]);
            flash('success', 'Plan deleted.');
        }
    }
    redirect('plans.php');
}

$plans = q("SELECT p.*, (SELECT COUNT(*) FROM members m WHERE m.plan_id = p.id AND m.status='active' AND m.membership_end >= CURDATE()) active_members FROM membership_plans p ORDER BY p.is_active DESC, p.duration_days")->fetchAll();
$edit = get('edit') ? q('SELECT * FROM membership_plans WHERE id = ?', [(int)get('edit')])->fetch() : null;
$f = $edit ?: ['id' => '', 'name' => '', 'duration_days' => 30, 'price' => '', 'description' => '', 'is_active' => 1];
require __DIR__ . '/includes/header.php';
?>
<div class="row g-3">
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header"><i class="bi bi-<?= $edit ? 'pencil' : 'plus-circle' ?>"></i> <?= $edit ? 'Edit Plan' : 'New Plan' ?></div>
            <div class="card-body">
                <form method="post" class="row g-2"><?= csrf_field() ?><input type="hidden" name="id" value="<?= e($f['id']) ?>">
                    <div class="col-12"><label class="form-label">Name</label><input name="name" class="form-control" value="<?= e($f['name']) ?>" required></div>
                    <div class="col-6"><label class="form-label">Duration (days)</label><input type="number" min="1" name="duration_days" class="form-control" value="<?= e($f['duration_days']) ?>" required></div>
                    <div class="col-6"><label class="form-label">Price (₱)</label><input type="number" step="0.01" min="0" name="price" class="form-control" value="<?= e($f['price']) ?>" required></div>
                    <div class="col-12"><label class="form-label">Description</label><input name="description" class="form-control" value="<?= e($f['description']) ?>"></div>
                    <div class="col-12 form-check ms-1"><input class="form-check-input" type="checkbox" name="is_active" value="1" id="act" <?= $f['is_active'] ? 'checked' : '' ?>><label class="form-check-label" for="act">Available for new members</label></div>
                    <div class="col-12 d-grid gap-2"><button name="action" value="save" class="btn btn-primary"><i class="bi bi-save"></i> Save Plan</button><?php if ($edit): ?><a href="plans.php" class="btn btn-outline-light">Cancel</a><?php endif; ?></div>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header"><i class="bi bi-card-list"></i> Plans</div>
            <div class="table-responsive"><table class="table table-hover">
                <thead><tr><th>Name</th><th>Duration</th><th>Price</th><th>Active Members</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
                <tbody><?php foreach ($plans as $p): ?>
                    <tr>
                        <td><div class="fw-semibold"><?= e($p['name']) ?></div><small class="text-secondary"><?= e($p['description']) ?></small></td>
                        <td><?= $p['duration_days'] ?> day(s)</td><td><?= peso($p['price']) ?></td><td><?= $p['active_members'] ?></td>
                        <td><?= $p['is_active'] ? badge('Available', 'success') : badge('Inactive', 'secondary') ?></td>
                        <td class="text-end text-nowrap">
                            <a href="?edit=<?= $p['id'] ?>" class="btn btn-sm btn-outline-light"><i class="bi bi-pencil"></i></a>
                            <form method="post" class="d-inline" data-confirm="Delete this plan?"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $p['id'] ?>"><button name="action" value="delete" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button></form>
                        </td>
                    </tr>
                <?php endforeach; ?></tbody>
            </table></div>
        </div>
    </div>
</div>
<?php require __DIR__ . '/includes/footer.php';
