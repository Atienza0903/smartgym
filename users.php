<?php
require __DIR__ . '/includes/bootstrap.php';
$me = require_role('admin');
$page_title = 'User Accounts';

if (is_post()) {
    verify_csrf();
    $action = post('action');
    $id = (int)post('id');
    if ($action === 'save') {
        $name = post('full_name');
        $email = strtolower(post('email'));
        $role = post('role');
        $pass = (string)($_POST['password'] ?? '');
        $err = '';
        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) $err = 'Valid name and email are required.';
        elseif (!in_array($role, ['admin', 'staff', 'member'], true)) $err = 'Invalid role.';
        elseif (q('SELECT id FROM users WHERE email = ? AND id <> ?', [$email, $id])->fetch()) $err = 'Email already in use.';
        elseif (!$id && strlen($pass) < 6) $err = 'Password must be at least 6 characters.';
        elseif ($id && $pass !== '' && strlen($pass) < 6) $err = 'Password must be at least 6 characters.';
        elseif ($id === (int)$me['id'] && $role !== 'admin') $err = 'You cannot remove your own admin role.';
        if ($err) {
            flash('danger', $err);
        } elseif ($id) {
            q('UPDATE users SET full_name=?, email=?, phone=?, role=? WHERE id=?', [$name, $email, post('phone') ?: null, $role, $id]);
            if ($pass !== '') q('UPDATE users SET password_hash=? WHERE id=?', [password_hash($pass, PASSWORD_DEFAULT), $id]);
            flash('success', 'Account updated.');
        } else {
            q('INSERT INTO users (full_name, email, phone, password_hash, role) VALUES (?,?,?,?,?)', [$name, $email, post('phone') ?: null, password_hash($pass, PASSWORD_DEFAULT), $role]);
            flash('success', 'Account created.' . ($role === 'member' ? ' Link it to a member profile from Members > Edit.' : ''));
        }
    } elseif ($action === 'toggle' && $id !== (int)$me['id']) {
        q("UPDATE users SET status = IF(status='active','disabled','active') WHERE id = ?", [$id]);
        flash('success', 'Account status changed.');
    } elseif ($action === 'delete' && $id !== (int)$me['id']) {
        q('DELETE FROM users WHERE id = ?', [$id]);
        flash('success', 'Account deleted.');
    }
    redirect('users.php?' . http_build_query(['role' => get('role')]));
}

$role = get('role');
$users = q('SELECT u.*, m.member_code FROM users u LEFT JOIN members m ON m.user_id = u.id' . (in_array($role, ['admin', 'staff', 'member'], true) ? ' WHERE u.role = ?' : '') . ' ORDER BY FIELD(u.role,"admin","staff","member"), u.full_name',
    in_array($role, ['admin', 'staff', 'member'], true) ? [$role] : [])->fetchAll();
$edit = get('edit') ? q('SELECT * FROM users WHERE id = ?', [(int)get('edit')])->fetch() : null;
$f = $edit ?: ['id' => '', 'full_name' => '', 'email' => '', 'phone' => '', 'role' => 'staff'];
require __DIR__ . '/includes/header.php';
?>
<div class="row g-3">
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header"><i class="bi bi-person-<?= $edit ? 'gear' : 'plus' ?>"></i> <?= $edit ? 'Edit Account' : 'New Staff / Admin Account' ?></div>
            <div class="card-body">
                <form method="post" class="row g-2"><?= csrf_field() ?><input type="hidden" name="id" value="<?= e($f['id']) ?>">
                    <div class="col-12"><label class="form-label">Full Name</label><input name="full_name" class="form-control" value="<?= e($f['full_name']) ?>" required></div>
                    <div class="col-12"><label class="form-label">Email (login)</label><input type="email" name="email" class="form-control" value="<?= e($f['email']) ?>" required></div>
                    <div class="col-6"><label class="form-label">Phone</label><input name="phone" class="form-control" value="<?= e($f['phone']) ?>"></div>
                    <div class="col-6"><label class="form-label">Role</label><select name="role" class="form-select"><?php foreach (['admin', 'staff', 'member'] as $r): ?><option value="<?= $r ?>" <?= $f['role'] === $r ? 'selected' : '' ?>><?= ucfirst($r) ?></option><?php endforeach; ?></select></div>
                    <div class="col-12"><label class="form-label"><?= $edit ? 'New Password (optional)' : 'Password' ?></label><input type="password" name="password" class="form-control" autocomplete="new-password" <?= $edit ? '' : 'required' ?> minlength="6"></div>
                    <div class="col-12 d-grid gap-2"><button name="action" value="save" class="btn btn-primary"><i class="bi bi-save"></i> Save</button><?php if ($edit): ?><a href="users.php" class="btn btn-outline-light">Cancel</a><?php endif; ?></div>
                </form>
                <p class="small text-secondary mt-3 mb-0"><i class="bi bi-info-circle"></i> Member accounts are best created from the member registration form so they are linked to a member profile.</p>
            </div>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header"><i class="bi bi-person-gear"></i> Accounts
                <div class="ms-auto btn-group btn-group-sm">
                    <?php foreach (['' => 'All', 'admin' => 'Admins', 'staff' => 'Staff', 'member' => 'Members'] as $k => $l): ?><a href="?role=<?= $k ?>" class="btn <?= $role === $k ? 'btn-primary' : 'btn-outline-light' ?>"><?= $l ?></a><?php endforeach; ?>
                </div>
            </div>
            <div class="table-responsive"><table class="table table-hover">
                <thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Status</th><th>Last Login</th><th class="text-end">Actions</th></tr></thead>
                <tbody><?php foreach ($users as $u): $self = (int)$u['id'] === (int)$me['id']; ?>
                    <tr>
                        <td class="fw-semibold"><?= e($u['full_name']) ?> <?= $self ? '<span class="badge bg-dark">You</span>' : '' ?><?= $u['member_code'] ? '<br><small class="text-secondary">' . e($u['member_code']) . '</small>' : '' ?></td>
                        <td class="small"><?= e($u['email']) ?></td>
                        <td><?= badge(ucfirst($u['role']), ['admin' => 'danger', 'staff' => 'info', 'member' => 'secondary'][$u['role']]) ?></td>
                        <td><?= $u['status'] === 'active' ? badge('Active', 'success') : badge('Disabled', 'secondary') ?></td>
                        <td class="small text-nowrap"><?= fmt_datetime($u['last_login_at']) ?></td>
                        <td class="text-end text-nowrap">
                            <a href="?edit=<?= $u['id'] ?>" class="btn btn-sm btn-outline-light"><i class="bi bi-pencil"></i></a>
                            <?php if (!$self): ?>
                                <form method="post" class="d-inline"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $u['id'] ?>"><button name="action" value="toggle" class="btn btn-sm btn-outline-warning" title="<?= $u['status'] === 'active' ? 'Disable' : 'Enable' ?>"><i class="bi bi-<?= $u['status'] === 'active' ? 'pause' : 'play' ?>-circle"></i></button></form>
                                <form method="post" class="d-inline" data-confirm="Delete account <?= e($u['email']) ?>?"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $u['id'] ?>"><button name="action" value="delete" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button></form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?></tbody>
            </table></div>
        </div>
    </div>
</div>
<?php require __DIR__ . '/includes/footer.php';
