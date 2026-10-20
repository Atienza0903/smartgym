<?php
require __DIR__ . '/includes/bootstrap.php';
$user = require_login();
$page_title = 'My Profile';

if (is_post()) {
    verify_csrf();
    if (post('action') === 'profile') {
        $email = strtolower(post('email'));
        if (post('full_name') === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash('danger', 'Valid name and email are required.');
        } elseif (q('SELECT id FROM users WHERE email = ? AND id <> ?', [$email, $user['id']])->fetch()) {
            flash('danger', 'Email already in use.');
        } else {
            q('UPDATE users SET full_name = ?, email = ?, phone = ? WHERE id = ?', [post('full_name'), $email, post('phone') ?: null, $user['id']]);
            q('UPDATE members SET full_name = ?, email = ?, phone = ? WHERE user_id = ?', [post('full_name'), $email, post('phone') ?: null, $user['id']]);
            flash('success', 'Profile updated.');
        }
    } elseif (post('action') === 'password') {
        $new = (string)($_POST['new_password'] ?? '');
        if (!password_verify((string)($_POST['current_password'] ?? ''), $user['password_hash'])) {
            flash('danger', 'Current password is incorrect.');
        } elseif (strlen($new) < 6) {
            flash('danger', 'New password must be at least 6 characters.');
        } elseif ($new !== ($_POST['confirm_password'] ?? '')) {
            flash('danger', 'New passwords do not match.');
        } else {
            q('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($new, PASSWORD_DEFAULT), $user['id']]);
            flash('success', 'Password changed.');
        }
    }
    redirect('profile.php');
}
require __DIR__ . '/includes/header.php';
?>
<div class="row g-3">
    <div class="col-lg-6">
        <div class="card"><div class="card-header"><i class="bi bi-person-circle"></i> Account Details</div><div class="card-body">
            <form method="post" class="row g-3"><?= csrf_field() ?>
                <div class="col-12"><label class="form-label">Full Name</label><input name="full_name" class="form-control" value="<?= e($user['full_name']) ?>" required></div>
                <div class="col-md-7"><label class="form-label">Email</label><input type="email" name="email" class="form-control" value="<?= e($user['email']) ?>" required></div>
                <div class="col-md-5"><label class="form-label">Phone</label><input name="phone" class="form-control" value="<?= e($user['phone']) ?>"></div>
                <div class="col-12"><span class="text-secondary small">Role: <?= e(ucfirst($user['role'])) ?> · Member since <?= fmt_date($user['created_at']) ?></span></div>
                <div class="col-12"><button name="action" value="profile" class="btn btn-primary"><i class="bi bi-save"></i> Save</button></div>
            </form>
        </div></div>
    </div>
    <div class="col-lg-6">
        <div class="card"><div class="card-header"><i class="bi bi-shield-lock"></i> Change Password</div><div class="card-body">
            <form method="post" class="row g-3"><?= csrf_field() ?>
                <div class="col-12"><label class="form-label">Current Password</label><input type="password" name="current_password" class="form-control" required></div>
                <div class="col-md-6"><label class="form-label">New Password</label><input type="password" name="new_password" class="form-control" minlength="6" required autocomplete="new-password"></div>
                <div class="col-md-6"><label class="form-label">Confirm New Password</label><input type="password" name="confirm_password" class="form-control" minlength="6" required autocomplete="new-password"></div>
                <div class="col-12"><button name="action" value="password" class="btn btn-primary"><i class="bi bi-key"></i> Change Password</button></div>
            </form>
        </div></div>
    </div>
</div>
<?php require __DIR__ . '/includes/footer.php';
