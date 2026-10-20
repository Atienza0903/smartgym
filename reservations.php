<?php
require __DIR__ . '/includes/bootstrap.php';
$user = require_login();
$page_title = 'Workout Slot Reservations';
$isMember = $user['role'] === 'member';
$member = $isMember ? current_member() : null;

/** Validates and creates a booking. Returns an error string or null on success. */
function book_slot(int $slotId, array $m): ?string
{
    $pdo = db();
    $pdo->beginTransaction();
    $slot = q('SELECT * FROM workout_slots WHERE id = ? FOR UPDATE', [$slotId])->fetch();
    $fail = function ($msg) use ($pdo) { $pdo->rollBack(); return $msg; };
    if (!$slot) return $fail('Slot not found.');
    if ($slot['slot_date'] . ' ' . $slot['end_time'] < now()) return $fail('This slot has already ended.');
    if ($m['status'] !== 'active' || !$m['membership_end'] || $m['membership_end'] < $slot['slot_date']) return $fail('Membership is not active on the slot date. Please renew first.');
    $booked = (int)q("SELECT COUNT(*) FROM reservations WHERE slot_id = ? AND status IN ('booked','attended')", [$slotId])->fetchColumn();
    if ($booked >= $slot['capacity']) return $fail('Sorry, this slot is already full.');
    $overlap = q("SELECT s.title FROM reservations r JOIN workout_slots s ON s.id = r.slot_id
        WHERE r.member_id = ? AND r.status = 'booked' AND s.slot_date = ? AND s.start_time < ? AND s.end_time > ? AND s.id <> ?",
        [$m['id'], $slot['slot_date'], $slot['end_time'], $slot['start_time'], $slotId])->fetch();
    if ($overlap) return $fail('Overlaps with another booking: ' . $overlap['title'] . '.');
    $existing = q('SELECT * FROM reservations WHERE slot_id = ? AND member_id = ?', [$slotId, $m['id']])->fetch();
    if ($existing && in_array($existing['status'], ['booked', 'attended'], true)) return $fail('Already booked for this slot.');
    if ($existing) {
        q("UPDATE reservations SET status = 'booked', updated_at = ? WHERE id = ?", [now(), $existing['id']]);
    } else {
        q('INSERT INTO reservations (slot_id, member_id) VALUES (?, ?)', [$slotId, $m['id']]);
    }
    $pdo->commit();
    return null;
}

if (is_post()) {
    verify_csrf();
    $action = post('action');
    if ($isMember) {
        if (!$member) redirect('dashboard.php');
        if ($action === 'book') {
            $err = book_slot((int)post('slot_id'), $member);
            flash($err ? 'danger' : 'success', $err ?: 'Reservation confirmed! See you at the gym.');
        } elseif ($action === 'cancel') {
            $r = q('SELECT r.*, s.slot_date, s.start_time FROM reservations r JOIN workout_slots s ON s.id = r.slot_id WHERE r.id = ? AND r.member_id = ?', [(int)post('id'), $member['id']])->fetch();
            if ($r && $r['status'] === 'booked' && $r['slot_date'] . ' ' . $r['start_time'] > now()) {
                q("UPDATE reservations SET status = 'cancelled', updated_at = ? WHERE id = ?", [now(), $r['id']]);
                flash('success', 'Reservation cancelled.');
            } else {
                flash('danger', 'This reservation can no longer be cancelled.');
            }
        }
    } else {
        if ($action === 'set_status' && in_array(post('status'), ['booked', 'attended', 'cancelled', 'no_show'], true)) {
            q('UPDATE reservations SET status = ?, updated_at = ? WHERE id = ?', [post('status'), now(), (int)post('id')]);
            flash('success', 'Reservation updated.');
        } elseif ($action === 'add') {
            $m = q('SELECT * FROM members WHERE id = ?', [(int)post('member_id')])->fetch();
            $err = $m ? book_slot((int)post('slot_id'), $m) : 'Select a member.';
            flash($err ? 'danger' : 'success', $err ?: 'Reservation added for ' . $m['full_name'] . '.');
        }
    }
    redirect($_SERVER['REQUEST_URI']);
}

require __DIR__ . '/includes/header.php';

if ($isMember):
    if (!$member) { echo '<div class="alert alert-warning">Your account is not linked to a member profile.</div>'; require __DIR__ . '/includes/footer.php'; exit; }
    $date = get('date', today());
    if ($date < today()) $date = today();
    $slots = q("SELECT s.*, (SELECT COUNT(*) FROM reservations r WHERE r.slot_id = s.id AND r.status IN ('booked','attended')) booked,
        (SELECT r.status FROM reservations r WHERE r.slot_id = s.id AND r.member_id = ?) my_status
        FROM workout_slots s WHERE s.slot_date = ? ORDER BY s.start_time", [$member['id'], $date])->fetchAll();
    $mine = q("SELECT r.*, s.title, s.slot_date, s.start_time, s.end_time, s.trainer FROM reservations r JOIN workout_slots s ON s.id = r.slot_id
        WHERE r.member_id = ? ORDER BY s.slot_date DESC, s.start_time DESC LIMIT 20", [$member['id']])->fetchAll();
    [$stLabel, $stColor] = membership_state($member);
    ?>
    <?php if ($stColor === 'danger' || $stColor === 'secondary'): ?>
        <div class="alert alert-warning"><i class="bi bi-exclamation-triangle me-1"></i>Your membership is <b><?= e($stLabel) ?></b>. Please renew at the front desk to make reservations.</div>
    <?php endif; ?>
    <div class="d-flex flex-wrap gap-2 mb-3">
        <?php for ($i = 0; $i < 7; $i++): $d = date('Y-m-d', strtotime("+$i days")); ?>
            <a href="?date=<?= $d ?>" class="btn btn-sm <?= $d === $date ? 'btn-primary' : 'btn-outline-light' ?>"><?= $i === 0 ? 'Today' : date('D M d', strtotime($d)) ?></a>
        <?php endfor; ?>
    </div>
    <div class="row g-3 mb-4">
        <?php if (!$slots): ?><div class="col-12"><div class="card"><div class="empty"><i class="bi bi-calendar-x"></i>No workout slots available on <?= fmt_date($date) ?>.</div></div></div><?php endif; ?>
        <?php foreach ($slots as $s):
            $left = $s['capacity'] - $s['booked'];
            $ended = $s['slot_date'] . ' ' . $s['end_time'] < now();
            $mineActive = in_array($s['my_status'], ['booked', 'attended'], true); ?>
            <div class="col-md-6 col-xl-4">
                <div class="card slot-card h-100 <?= $left <= 0 || $ended ? 'full' : '' ?>">
                    <div class="card-body">
                        <div class="d-flex justify-content-between"><h6 class="fw-bold text-white mb-1"><?= e($s['title']) ?></h6><?= $mineActive ? reservation_badge($s['my_status']) : '' ?></div>
                        <div class="fs-5"><?= fmt_time($s['start_time']) ?> – <?= fmt_time($s['end_time']) ?></div>
                        <div class="small text-secondary mb-2"><i class="bi bi-person"></i> <?= e($s['trainer'] ?: 'Open gym') ?> <?= $s['notes'] ? '· ' . e($s['notes']) : '' ?></div>
                        <div class="progress mb-1" style="height:6px"><div class="progress-bar bg-danger" style="width:<?= round($s['booked'] / max(1, $s['capacity']) * 100) ?>%"></div></div>
                        <div class="small mb-3"><?= max(0, $left) ?> of <?= $s['capacity'] ?> spots left</div>
                        <?php if ($mineActive): ?>
                            <button class="btn btn-sm btn-outline-success w-100" disabled><i class="bi bi-check2"></i> Reserved</button>
                        <?php elseif ($ended): ?>
                            <button class="btn btn-sm btn-outline-secondary w-100" disabled>Ended</button>
                        <?php elseif ($left <= 0): ?>
                            <button class="btn btn-sm btn-outline-secondary w-100" disabled>Full</button>
                        <?php else: ?>
                            <form method="post"><?= csrf_field() ?><input type="hidden" name="slot_id" value="<?= $s['id'] ?>"><button name="action" value="book" class="btn btn-sm btn-primary w-100"><i class="bi bi-calendar-plus"></i> Reserve Slot</button></form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <div class="card">
        <div class="card-header"><i class="bi bi-calendar-check"></i> My Reservations</div>
        <div class="table-responsive"><table class="table table-hover">
            <thead><tr><th>Slot</th><th>Date</th><th>Time</th><th>Trainer</th><th>Status</th><th></th></tr></thead>
            <tbody>
            <?php if (!$mine): ?><tr><td colspan="6"><div class="empty"><i class="bi bi-calendar-x"></i>You have no reservations yet.</div></td></tr><?php endif; ?>
            <?php foreach ($mine as $r): ?>
                <tr><td><?= e($r['title']) ?></td><td><?= fmt_date($r['slot_date']) ?></td><td><?= fmt_time($r['start_time']) ?> – <?= fmt_time($r['end_time']) ?></td><td><?= e($r['trainer'] ?: '—') ?></td><td><?= reservation_badge($r['status']) ?></td>
                    <td class="text-end"><?php if ($r['status'] === 'booked' && $r['slot_date'] . ' ' . $r['start_time'] > now()): ?>
                        <form method="post" data-confirm="Cancel this reservation?"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $r['id'] ?>"><button name="action" value="cancel" class="btn btn-sm btn-outline-danger">Cancel</button></form>
                    <?php endif; ?></td></tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
    </div>
<?php else:
    // ---- Admin / Staff view ----
    $slotId = (int)get('slot');
    $date = get('date', $slotId ? '' : today());
    $status = get('status');
    $where = ['1=1'];
    $params = [];
    if ($slotId) { $where[] = 's.id = ?'; $params[] = $slotId; }
    if ($date) { $where[] = 's.slot_date = ?'; $params[] = $date; }
    if (in_array($status, ['booked', 'attended', 'cancelled', 'no_show'], true)) { $where[] = 'r.status = ?'; $params[] = $status; }
    $rows = q('SELECT r.*, s.title, s.slot_date, s.start_time, s.end_time, m.full_name, m.member_code, m.id mid FROM reservations r
        JOIN workout_slots s ON s.id = r.slot_id JOIN members m ON m.id = r.member_id WHERE ' . implode(' AND ', $where) . '
        ORDER BY s.slot_date, s.start_time, m.full_name LIMIT 500', $params)->fetchAll();
    $upcomingSlots = q("SELECT s.*, (SELECT COUNT(*) FROM reservations r WHERE r.slot_id = s.id AND r.status IN ('booked','attended')) booked
        FROM workout_slots s WHERE CONCAT(s.slot_date, ' ', s.end_time) >= ? ORDER BY s.slot_date, s.start_time LIMIT 100", [now()])->fetchAll();
    $activeMembers = q("SELECT id, full_name, member_code FROM members WHERE status = 'active' ORDER BY full_name")->fetchAll();
    ?>
    <div class="row g-3">
        <div class="col-lg-9">
            <div class="card">
                <div class="card-header"><i class="bi bi-calendar-check"></i> Reservations <span class="badge bg-secondary"><?= count($rows) ?></span></div>
                <div class="card-body border-bottom" style="border-color:var(--sg-border)!important">
                    <form class="row g-2">
                        <div class="col-md-4"><input type="date" name="date" value="<?= e($date) ?>" class="form-control"></div>
                        <div class="col-md-4"><select name="status" class="form-select"><option value="">All status</option><?php foreach (['booked', 'attended', 'cancelled', 'no_show'] as $s): ?><option value="<?= $s ?>" <?= $status === $s ? 'selected' : '' ?>><?= ucwords(str_replace('_', ' ', $s)) ?></option><?php endforeach; ?></select></div>
                        <div class="col-md-4 d-grid"><button class="btn btn-outline-primary"><i class="bi bi-funnel"></i> Filter</button></div>
                    </form>
                </div>
                <div class="table-responsive"><table class="table table-hover">
                    <thead><tr><th>Member</th><th>Slot</th><th>Date</th><th>Time</th><th>Status</th><th class="text-end">Update</th></tr></thead>
                    <tbody>
                    <?php if (!$rows): ?><tr><td colspan="6"><div class="empty"><i class="bi bi-calendar-x"></i>No reservations found.</div></td></tr><?php endif; ?>
                    <?php foreach ($rows as $r): ?>
                        <tr>
                            <td><a href="member_view.php?id=<?= $r['mid'] ?>"><?= e($r['full_name']) ?></a><br><small class="text-secondary"><?= e($r['member_code']) ?></small></td>
                            <td><?= e($r['title']) ?></td><td class="text-nowrap"><?= fmt_date($r['slot_date']) ?></td>
                            <td class="text-nowrap"><?= fmt_time($r['start_time']) ?> – <?= fmt_time($r['end_time']) ?></td>
                            <td><?= reservation_badge($r['status']) ?></td>
                            <td class="text-end">
                                <form method="post" class="d-inline-flex gap-1"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $r['id'] ?>"><input type="hidden" name="action" value="set_status">
                                    <select name="status" class="form-select form-select-sm" style="width:auto"><?php foreach (['booked', 'attended', 'cancelled', 'no_show'] as $s): ?><option value="<?= $s ?>" <?= $r['status'] === $s ? 'selected' : '' ?>><?= ucwords(str_replace('_', ' ', $s)) ?></option><?php endforeach; ?></select>
                                    <button class="btn btn-sm btn-outline-light"><i class="bi bi-check2"></i></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table></div>
            </div>
        </div>
        <div class="col-lg-3">
            <div class="card">
                <div class="card-header"><i class="bi bi-plus-circle"></i> Book for a Member</div>
                <div class="card-body">
                    <form method="post" class="d-grid gap-2"><?= csrf_field() ?>
                        <label class="form-label mb-0">Member</label>
                        <select name="member_id" class="form-select" required><option value="">Select…</option><?php foreach ($activeMembers as $m): ?><option value="<?= $m['id'] ?>"><?= e($m['full_name']) ?> (<?= e($m['member_code']) ?>)</option><?php endforeach; ?></select>
                        <label class="form-label mb-0">Slot</label>
                        <select name="slot_id" class="form-select" required><option value="">Select…</option><?php foreach ($upcomingSlots as $s): ?><option value="<?= $s['id'] ?>" <?= $s['id'] == $slotId ? 'selected' : '' ?>><?= fmt_date($s['slot_date'], 'M d') ?> <?= fmt_time($s['start_time']) ?> · <?= e($s['title']) ?> (<?= $s['booked'] ?>/<?= $s['capacity'] ?>)</option><?php endforeach; ?></select>
                        <button name="action" value="add" class="btn btn-primary mt-1"><i class="bi bi-calendar-plus"></i> Book</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
<?php endif;
require __DIR__ . '/includes/footer.php';
