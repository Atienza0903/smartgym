<?php
require __DIR__ . '/includes/bootstrap.php';
$user = require_role('admin', 'staff');
$id = (int)get('id');
$member = $id ? q('SELECT * FROM members WHERE id = ?', [$id])->fetch() : null;
if ($id && !$member) {
    flash('danger', 'Member not found.');
    redirect('members.php');
}
$linkedUser = ($member && $member['user_id']) ? q('SELECT * FROM users WHERE id = ?', [$member['user_id']])->fetch() : null;
$page_title = $member ? 'Edit Member' : 'Add Member';
$plans = q('SELECT * FROM membership_plans WHERE is_active = 1 OR id = ? ORDER BY duration_days', [$member['plan_id'] ?? 0])->fetchAll();
$planById = array_column($plans, null, 'id');

$data = $member ?: [
    'member_code' => next_member_code(), 'full_name' => '', 'email' => '', 'phone' => '', 'gender' => '', 'birthdate' => '',
    'address' => '', 'emergency_contact' => '', 'rfid_uid' => get('uid'), 'plan_id' => '', 'membership_start' => today(),
    'membership_end' => '', 'status' => 'active', 'notes' => '',
];
$errors = [];

if (is_post()) {
    verify_csrf();
    foreach (['member_code', 'full_name', 'email', 'phone', 'gender', 'birthdate', 'address', 'emergency_contact', 'rfid_uid', 'plan_id', 'membership_start', 'membership_end', 'status', 'notes'] as $f) {
        $data[$f] = post($f);
    }
    $data['email'] = strtolower($data['email']);
    $data['rfid_uid'] = normalize_uid($data['rfid_uid']);
    $createAccount = post('create_account') === '1';
    $password = (string)($_POST['password'] ?? '');

    if ($data['full_name'] === '') $errors[] = 'Full name is required.';
    if ($data['member_code'] === '') $errors[] = 'Member code is required.';
    if ($data['email'] !== '' && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Invalid email address.';
    if (!in_array($data['status'], ['active', 'inactive', 'suspended'], true)) $errors[] = 'Invalid status.';
    if (!in_array($data['gender'], ['', 'Male', 'Female', 'Other'], true)) $errors[] = 'Invalid gender.';
    if ($data['plan_id'] !== '' && !isset($planById[$data['plan_id']])) $errors[] = 'Invalid plan.';
    if (q('SELECT id FROM members WHERE member_code = ? AND id <> ?', [$data['member_code'], $id])->fetch()) $errors[] = 'Member code already exists.';
    if ($data['rfid_uid'] !== '' && ($dup = q('SELECT full_name FROM members WHERE rfid_uid = ? AND id <> ?', [$data['rfid_uid'], $id])->fetch())) {
        $errors[] = 'RFID card is already assigned to ' . $dup['full_name'] . '.';
    }
    if ($data['plan_id'] !== '' && $data['membership_start'] !== '' && $data['membership_end'] === '') {
        $data['membership_end'] = date('Y-m-d', strtotime($data['membership_start'] . ' +' . ((int)$planById[$data['plan_id']]['duration_days'] - 1) . ' days'));
    }
    if ($data['membership_start'] && $data['membership_end'] && $data['membership_end'] < $data['membership_start']) $errors[] = 'Membership end date must be after start date.';

    $wantsAccount = !$linkedUser && $createAccount;
    if ($wantsAccount) {
        if ($data['email'] === '') $errors[] = 'Email is required to create a member login.';
        if (strlen($password) < 6) $errors[] = 'Login password must be at least 6 characters.';
        if ($data['email'] && q('SELECT id FROM users WHERE email = ?', [$data['email']])->fetch()) $errors[] = 'A user account with this email already exists.';
    }
    if ($linkedUser && $data['email'] !== '' && q('SELECT id FROM users WHERE email = ? AND id <> ?', [$data['email'], $linkedUser['id']])->fetch()) {
        $errors[] = 'Another user account already uses this email.';
    }
    if ($linkedUser && $password !== '' && strlen($password) < 6) $errors[] = 'New password must be at least 6 characters.';

    if (!$errors) {
        $pdo = db();
        $pdo->beginTransaction();
        $nullIfEmpty = fn($v) => $v === '' ? null : $v;
        $userId = $linkedUser['id'] ?? null;
        if ($wantsAccount) {
            q("INSERT INTO users (full_name, email, phone, password_hash, role) VALUES (?, ?, ?, ?, 'member')",
                [$data['full_name'], $data['email'], $nullIfEmpty($data['phone']), password_hash($password, PASSWORD_DEFAULT)]);
            $userId = (int)$pdo->lastInsertId();
        } elseif ($linkedUser) {
            q('UPDATE users SET full_name = ?, email = COALESCE(?, email), phone = ? WHERE id = ?', [$data['full_name'], $nullIfEmpty($data['email']), $nullIfEmpty($data['phone']), $userId]);
            if ($password !== '') {
                q('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($password, PASSWORD_DEFAULT), $userId]);
            }
        }
        $vals = [
            $userId, $data['member_code'], $data['full_name'], $nullIfEmpty($data['email']), $nullIfEmpty($data['phone']), $nullIfEmpty($data['gender']),
            $nullIfEmpty($data['birthdate']), $nullIfEmpty($data['address']), $nullIfEmpty($data['emergency_contact']), $nullIfEmpty($data['rfid_uid']),
            $nullIfEmpty($data['plan_id']), $nullIfEmpty($data['membership_start']), $nullIfEmpty($data['membership_end']), $data['status'], $nullIfEmpty($data['notes']),
        ];
        if ($member) {
            q('UPDATE members SET user_id=?, member_code=?, full_name=?, email=?, phone=?, gender=?, birthdate=?, address=?, emergency_contact=?, rfid_uid=?,
               plan_id=?, membership_start=?, membership_end=?, status=?, notes=? WHERE id=?', array_merge($vals, [$id]));
        } else {
            q('INSERT INTO members (user_id, member_code, full_name, email, phone, gender, birthdate, address, emergency_contact, rfid_uid,
               plan_id, membership_start, membership_end, status, notes) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)', $vals);
            $id = (int)$pdo->lastInsertId();
        }
        $pdo->commit();
        flash('success', $member ? 'Member updated.' : 'Member registered successfully.');
        redirect('member_view.php?id=' . $id);
    }
}

// Recently scanned cards that are not assigned to anyone — handy for registering a new card.
$unknownCards = q("SELECT l.rfid_uid, MAX(l.scanned_at) last_seen FROM access_logs l
    LEFT JOIN members m ON m.rfid_uid = l.rfid_uid
    WHERE l.member_id IS NULL AND l.reason = 'Unregistered card' AND m.id IS NULL AND l.scanned_at >= ?
    GROUP BY l.rfid_uid ORDER BY last_seen DESC LIMIT 5", [date('Y-m-d H:i:s', strtotime('-1 day'))])->fetchAll();

require __DIR__ . '/includes/header.php';
?>
<form method="post" class="row g-3">
    <?= csrf_field() ?>
    <?php if ($errors): ?><div class="col-12"><div class="alert alert-danger mb-0"><ul class="mb-0"><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div></div><?php endif; ?>
    <div class="col-lg-8">
        <div class="card mb-3">
            <div class="card-header"><i class="bi bi-person-vcard"></i> Personal Information</div>
            <div class="card-body row g-3">
                <div class="col-md-4"><label class="form-label">Member Code *</label><input name="member_code" class="form-control" value="<?= e($data['member_code']) ?>" required></div>
                <div class="col-md-8"><label class="form-label">Full Name *</label><input name="full_name" class="form-control" value="<?= e($data['full_name']) ?>" required></div>
                <div class="col-md-6"><label class="form-label">Email</label><input type="email" name="email" class="form-control" value="<?= e($data['email']) ?>"></div>
                <div class="col-md-6"><label class="form-label">Phone</label><input name="phone" class="form-control" value="<?= e($data['phone']) ?>" placeholder="09XXXXXXXXX"></div>
                <div class="col-md-4"><label class="form-label">Gender</label>
                    <select name="gender" class="form-select"><option value="">—</option>
                        <?php foreach (['Male', 'Female', 'Other'] as $g): ?><option <?= $data['gender'] === $g ? 'selected' : '' ?>><?= $g ?></option><?php endforeach; ?>
                    </select></div>
                <div class="col-md-4"><label class="form-label">Birthdate</label><input type="date" name="birthdate" class="form-control" value="<?= e($data['birthdate']) ?>"></div>
                <div class="col-md-4"><label class="form-label">Emergency Contact</label><input name="emergency_contact" class="form-control" value="<?= e($data['emergency_contact']) ?>" placeholder="Name - Phone"></div>
                <div class="col-12"><label class="form-label">Address</label><input name="address" class="form-control" value="<?= e($data['address']) ?>"></div>
                <div class="col-12"><label class="form-label">Notes</label><textarea name="notes" rows="2" class="form-control"><?= e($data['notes']) ?></textarea></div>
            </div>
        </div>
        <div class="card">
            <div class="card-header"><i class="bi bi-card-checklist"></i> Membership</div>
            <div class="card-body row g-3">
                <div class="col-md-4"><label class="form-label">Plan</label>
                    <select name="plan_id" id="plan_id" class="form-select"><option value="">— No plan —</option>
                        <?php foreach ($plans as $pl): ?><option value="<?= $pl['id'] ?>" data-days="<?= $pl['duration_days'] ?>" <?= (string)$data['plan_id'] === (string)$pl['id'] ? 'selected' : '' ?>><?= e($pl['name']) ?> (<?= peso($pl['price']) ?>)</option><?php endforeach; ?>
                    </select></div>
                <div class="col-md-3"><label class="form-label">Start Date</label><input type="date" name="membership_start" id="m_start" class="form-control" value="<?= e($data['membership_start']) ?>"></div>
                <div class="col-md-3"><label class="form-label">End Date</label><input type="date" name="membership_end" id="m_end" class="form-control" value="<?= e($data['membership_end']) ?>"><small class="text-secondary">Auto-filled from plan</small></div>
                <div class="col-md-2"><label class="form-label">Status</label>
                    <select name="status" class="form-select"><?php foreach (['active', 'inactive', 'suspended'] as $s): ?><option value="<?= $s ?>" <?= $data['status'] === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option><?php endforeach; ?></select></div>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card mb-3">
            <div class="card-header"><i class="bi bi-credit-card-2-front"></i> RFID Card / Keyfob</div>
            <div class="card-body">
                <label class="form-label">Card UID</label>
                <input name="rfid_uid" id="rfid_uid" class="form-control font-monospace" value="<?= e($data['rfid_uid']) ?>" placeholder="e.g. A1B2C3D4">
                <small class="text-secondary d-block mt-1">Tap the new card on the reader, then pick it below — or type the UID manually.</small>
                <?php if ($unknownCards): ?>
                    <div class="mt-3"><div class="form-label">Recently tapped unregistered cards</div>
                        <?php foreach ($unknownCards as $c): ?>
                            <button type="button" class="btn btn-sm btn-outline-primary me-1 mb-1 font-monospace" onclick="document.getElementById('rfid_uid').value='<?= e($c['rfid_uid']) ?>'"><?= e($c['rfid_uid']) ?> <small class="text-secondary">· <?= date('h:i A', strtotime($c['last_seen'])) ?></small></button>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <div class="card mb-3">
            <div class="card-header"><i class="bi bi-key"></i> Member Portal Login</div>
            <div class="card-body">
                <?php if ($linkedUser): ?>
                    <p class="small mb-2">Linked account: <b><?= e($linkedUser['email']) ?></b></p>
                    <label class="form-label">Reset Password</label>
                    <input type="password" name="password" class="form-control" placeholder="Leave blank to keep current" autocomplete="new-password">
                <?php else: ?>
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" name="create_account" value="1" id="create_account" <?= post('create_account') === '1' ? 'checked' : '' ?>>
                        <label class="form-check-label" for="create_account">Create login so member can view status & reserve slots</label>
                    </div>
                    <input type="password" name="password" class="form-control" placeholder="Initial password (min 6 chars)" autocomplete="new-password">
                    <small class="text-secondary">Member logs in with the email above.</small>
                <?php endif; ?>
            </div>
        </div>
        <div class="d-grid gap-2">
            <button class="btn btn-primary"><i class="bi bi-save"></i> <?= $member ? 'Save Changes' : 'Register Member' ?></button>
            <a href="<?= $member ? 'member_view.php?id=' . $member['id'] : 'members.php' ?>" class="btn btn-outline-light">Cancel</a>
        </div>
    </div>
</form>
<?php
$page_scripts = <<<'JS'
<script>
(function () {
  const plan = document.getElementById('plan_id'), start = document.getElementById('m_start'), end = document.getElementById('m_end');
  function calc() {
    const opt = plan.selectedOptions[0];
    if (!opt || !opt.dataset.days || !start.value) return;
    const d = new Date(start.value + 'T00:00:00');
    d.setDate(d.getDate() + parseInt(opt.dataset.days, 10) - 1);
    end.value = d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
  }
  plan.addEventListener('change', calc); start.addEventListener('change', calc);
})();
</script>
JS;
require __DIR__ . '/includes/footer.php';
