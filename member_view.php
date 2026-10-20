<?php
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/rfid.php';
$user = require_role('admin', 'staff');
$id = (int)get('id');
$m = q('SELECT m.*, p.name plan_name, u.email login_email FROM members m LEFT JOIN membership_plans p ON p.id = m.plan_id LEFT JOIN users u ON u.id = m.user_id WHERE m.id = ?', [$id])->fetch();
if (!$m) {
    flash('danger', 'Member not found.');
    redirect('members.php');
}

if (is_post()) {
    verify_csrf();
    $action = post('action');
    if ($action === 'renew') {
        $plan = q('SELECT * FROM membership_plans WHERE id = ?', [(int)post('plan_id')])->fetch();
        if ($plan) {
            $base = ($m['membership_end'] && $m['membership_end'] >= today()) ? date('Y-m-d', strtotime($m['membership_end'] . ' +1 day')) : today();
            $end = date('Y-m-d', strtotime($base . ' +' . ($plan['duration_days'] - 1) . ' days'));
            $start = ($m['membership_end'] && $m['membership_end'] >= today()) ? ($m['membership_start'] ?: today()) : today();
            q("UPDATE members SET plan_id = ?, membership_start = ?, membership_end = ?, status = 'active' WHERE id = ?", [$plan['id'], $start, $end, $id]);
            flash('success', "Membership renewed with {$plan['name']} until " . fmt_date($end) . '.');
        }
    } elseif ($action === 'checkin') {
        $r = manual_checkin($id);
        flash($r['ok'] ? 'success' : 'danger', $r['message']);
    } elseif ($action === 'checkout') {
        manual_checkout($id);
        flash('success', 'Member checked out.');
    } elseif ($action === 'unassign_card') {
        q('UPDATE members SET rfid_uid = NULL WHERE id = ?', [$id]);
        flash('success', 'RFID card unassigned.');
    }
    redirect('member_view.php?id=' . $id);
}

[$sl, $sc] = membership_state($m);
$logs = q('SELECT * FROM access_logs WHERE member_id = ? ORDER BY scanned_at DESC LIMIT 15', [$id])->fetchAll();
$reservations = q('SELECT r.*, s.title, s.slot_date, s.start_time, s.end_time FROM reservations r JOIN workout_slots s ON s.id = r.slot_id
    WHERE r.member_id = ? ORDER BY s.slot_date DESC, s.start_time DESC LIMIT 10', [$id])->fetchAll();
$visits30 = (int)q("SELECT COUNT(*) FROM access_logs WHERE member_id = ? AND direction = 'IN' AND scanned_at >= ?", [$id, date('Y-m-d', strtotime('-30 days'))])->fetchColumn();
$totalVisits = (int)q("SELECT COUNT(*) FROM access_logs WHERE member_id = ? AND direction = 'IN'", [$id])->fetchColumn();
$plans = q('SELECT * FROM membership_plans WHERE is_active = 1 ORDER BY duration_days')->fetchAll();
$page_title = $m['full_name'];
require __DIR__ . '/includes/header.php';
?>
<div class="row g-3">
    <div class="col-lg-4">
        <div class="card mb-3">
            <div class="card-body text-center">
                <div class="stat-icon bg-red-soft mx-auto mb-2" style="width:72px;height:72px;font-size:2rem"><i class="bi bi-person-fill"></i></div>
                <h5 class="fw-bold text-white mb-0"><?= e($m['full_name']) ?></h5>
                <div class="text-secondary mb-2"><?= e($m['member_code']) ?></div>
                <?= badge($sl, $sc) ?> <?= $m['is_inside'] ? badge('Inside Gym', 'success') : badge('Outside', 'secondary') ?>
                <div class="d-flex gap-2 justify-content-center mt-3">
                    <form method="post"><?= csrf_field() ?>
                        <?php if ($m['is_inside']): ?><button name="action" value="checkout" class="btn btn-sm btn-outline-secondary"><i class="bi bi-box-arrow-right"></i> Check Out</button>
                        <?php else: ?><button name="action" value="checkin" class="btn btn-sm btn-outline-success"><i class="bi bi-box-arrow-in-right"></i> Check In</button><?php endif; ?>
                    </form>
                    <a href="member_form.php?id=<?= $id ?>" class="btn btn-sm btn-primary"><i class="bi bi-pencil"></i> Edit</a>
                </div>
            </div>
            <ul class="list-group list-group-flush small">
                <li class="list-group-item d-flex justify-content-between"><span class="text-secondary">Email</span><span><?= e($m['email'] ?: '—') ?></span></li>
                <li class="list-group-item d-flex justify-content-between"><span class="text-secondary">Phone</span><span><?= e($m['phone'] ?: '—') ?></span></li>
                <li class="list-group-item d-flex justify-content-between"><span class="text-secondary">Gender</span><span><?= e($m['gender'] ?: '—') ?></span></li>
                <li class="list-group-item d-flex justify-content-between"><span class="text-secondary">Birthdate</span><span><?= fmt_date($m['birthdate']) ?></span></li>
                <li class="list-group-item d-flex justify-content-between"><span class="text-secondary">Emergency</span><span><?= e($m['emergency_contact'] ?: '—') ?></span></li>
                <li class="list-group-item d-flex justify-content-between"><span class="text-secondary">Address</span><span class="text-end"><?= e($m['address'] ?: '—') ?></span></li>
                <li class="list-group-item d-flex justify-content-between"><span class="text-secondary">Portal Login</span><span><?= e($m['login_email'] ?: 'None') ?></span></li>
                <li class="list-group-item d-flex justify-content-between"><span class="text-secondary">Registered</span><span><?= fmt_date($m['created_at']) ?></span></li>
            </ul>
        </div>
        <div class="card">
            <div class="card-header"><i class="bi bi-credit-card-2-front"></i> RFID Card</div>
            <div class="card-body">
                <?php if ($m['rfid_uid']): ?>
                    <div class="d-flex align-items-center justify-content-between">
                        <code class="fs-5"><?= e($m['rfid_uid']) ?></code>
                        <form method="post" data-confirm="Unassign this card (e.g. lost card)?"><?= csrf_field() ?><button name="action" value="unassign_card" class="btn btn-sm btn-outline-danger">Unassign</button></form>
                    </div>
                    <small class="text-secondary">Last scan: <?= fmt_datetime($m['last_scan_at']) ?></small>
                <?php else: ?>
                    <p class="text-secondary mb-2">No card assigned.</p><a href="member_form.php?id=<?= $id ?>" class="btn btn-sm btn-outline-primary">Assign Card</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="row g-3 mb-3">
            <div class="col-md-4"><div class="card stat-card"><div class="stat-icon bg-blue-soft"><i class="bi bi-card-list"></i></div><div><div class="stat-value fs-5"><?= e($m['plan_name'] ?? '—') ?></div><div class="stat-label">Plan</div></div></div></div>
            <div class="col-md-4"><div class="card stat-card"><div class="stat-icon bg-amber-soft"><i class="bi bi-calendar-event"></i></div><div><div class="stat-value fs-5"><?= fmt_date($m['membership_end']) ?></div><div class="stat-label">Valid Until</div></div></div></div>
            <div class="col-md-4"><div class="card stat-card"><div class="stat-icon bg-green-soft"><i class="bi bi-activity"></i></div><div><div class="stat-value"><?= $visits30 ?><small class="fs-6 text-secondary"> / <?= $totalVisits ?></small></div><div class="stat-label">Visits 30d / Total</div></div></div></div>
        </div>
        <div class="card mb-3">
            <div class="card-header"><i class="bi bi-arrow-repeat"></i> Renew / Change Membership</div>
            <div class="card-body">
                <form method="post" class="row g-2 align-items-end"><?= csrf_field() ?>
                    <div class="col-md-8"><label class="form-label">Plan</label>
                        <select name="plan_id" class="form-select"><?php foreach ($plans as $pl): ?><option value="<?= $pl['id'] ?>" <?= $pl['id'] == $m['plan_id'] ? 'selected' : '' ?>><?= e($pl['name']) ?> — <?= $pl['duration_days'] ?> day(s) — <?= peso($pl['price']) ?></option><?php endforeach; ?></select>
                    </div>
                    <div class="col-md-4 d-grid"><button name="action" value="renew" class="btn btn-primary"><i class="bi bi-arrow-repeat"></i> Renew</button></div>
                </form>
                <small class="text-secondary">If still active, the new period is added after the current end date.</small>
            </div>
        </div>
        <div class="card mb-3">
            <div class="card-header"><i class="bi bi-clock-history"></i> Attendance History <a href="access_logs.php?search=<?= urlencode($m['member_code']) ?>" class="btn btn-sm btn-outline-light ms-auto">All Logs</a></div>
            <?php if (!$logs): ?><div class="empty"><i class="bi bi-clock"></i>No attendance records.</div><?php else: ?>
            <div class="table-responsive"><table class="table table-hover"><thead><tr><th>Date & Time</th><th>Type</th><th>Source</th><th>Note</th></tr></thead><tbody>
                <?php foreach ($logs as $l): ?><tr><td><?= fmt_datetime($l['scanned_at']) ?></td><td><?= direction_badge($l['direction']) ?></td><td class="small text-secondary"><?= e(ucfirst($l['source'])) ?></td><td class="small"><?= e($l['reason']) ?></td></tr><?php endforeach; ?>
            </tbody></table></div><?php endif; ?>
        </div>
        <div class="card">
            <div class="card-header"><i class="bi bi-calendar-check"></i> Reservations</div>
            <?php if (!$reservations): ?><div class="empty"><i class="bi bi-calendar-x"></i>No reservations.</div><?php else: ?>
            <div class="table-responsive"><table class="table table-hover"><thead><tr><th>Slot</th><th>Date</th><th>Time</th><th>Status</th></tr></thead><tbody>
                <?php foreach ($reservations as $r): ?><tr><td><?= e($r['title']) ?></td><td><?= fmt_date($r['slot_date']) ?></td><td><?= fmt_time($r['start_time']) ?> – <?= fmt_time($r['end_time']) ?></td><td><?= reservation_badge($r['status']) ?></td></tr><?php endforeach; ?>
            </tbody></table></div><?php endif; ?>
        </div>
    </div>
</div>
<?php require __DIR__ . '/includes/footer.php';
