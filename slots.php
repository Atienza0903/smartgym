<?php
require __DIR__ . '/includes/bootstrap.php';
$user = require_role('admin', 'staff');
$page_title = 'Workout Slot Schedule';
$editing = get('edit') ? q('SELECT * FROM workout_slots WHERE id = ?', [(int)get('edit')])->fetch() : null;

if (is_post()) {
    verify_csrf();
    $action = post('action');
    if ($action === 'delete') {
        q('DELETE FROM workout_slots WHERE id = ?', [(int)post('id')]);
        flash('success', 'Slot deleted (its reservations were removed).');
        redirect('slots.php?' . http_build_query(['date' => get('date')]));
    }
    $title = post('title');
    $start = post('start_time');
    $end = post('end_time');
    $capacity = max(1, (int)post('capacity'));
    $trainer = post('trainer') ?: null;
    $notes = post('notes') ?: null;
    $err = '';
    if ($title === '' || !$start || !$end) $err = 'Title, start time and end time are required.';
    elseif ($end <= $start) $err = 'End time must be after start time.';

    if (!$err && $action === 'update' && $editing) {
        $booked = (int)q("SELECT COUNT(*) FROM reservations WHERE slot_id = ? AND status IN ('booked','attended')", [$editing['id']])->fetchColumn();
        if ($capacity < $booked) {
            $err = "Capacity cannot be lower than current bookings ($booked).";
        } else {
            q('UPDATE workout_slots SET title=?, slot_date=?, start_time=?, end_time=?, capacity=?, trainer=?, notes=? WHERE id=?',
                [$title, post('slot_date'), $start, $end, $capacity, $trainer, $notes, $editing['id']]);
            flash('success', 'Slot updated.');
            redirect('slots.php?date=' . urlencode(post('slot_date')));
        }
    } elseif (!$err && $action === 'create') {
        $from = post('date_from');
        $to = post('date_to') ?: $from;
        $days = array_map('intval', (array)($_POST['days'] ?? []));
        if (!$from || $to < $from) {
            $err = 'Invalid date range.';
        } elseif ((strtotime($to) - strtotime($from)) / 86400 > 92) {
            $err = 'Date range is limited to 3 months at a time.';
        } else {
            $count = 0;
            for ($d = strtotime($from); $d <= strtotime($to); $d = strtotime('+1 day', $d)) {
                if ($days && !in_array((int)date('w', $d), $days, true)) continue;
                $date = date('Y-m-d', $d);
                $exists = q('SELECT id FROM workout_slots WHERE slot_date = ? AND start_time = ? AND title = ?', [$date, $start, $title])->fetch();
                if ($exists) continue;
                q('INSERT INTO workout_slots (title, slot_date, start_time, end_time, capacity, trainer, notes, created_by) VALUES (?,?,?,?,?,?,?,?)',
                    [$title, $date, $start, $end, $capacity, $trainer, $notes, $user['id']]);
                $count++;
            }
            flash('success', "$count slot(s) created.");
            redirect('slots.php?date=' . urlencode($from));
        }
    }
    if ($err) flash('danger', $err);
    redirect($_SERVER['REQUEST_URI']);
}

$date = get('date', today());
$slots = q("SELECT s.*, (SELECT COUNT(*) FROM reservations r WHERE r.slot_id = s.id AND r.status IN ('booked','attended')) booked
    FROM workout_slots s WHERE s.slot_date = ? ORDER BY s.start_time", [$date])->fetchAll();
$week = [];
for ($i = -1; $i < 7; $i++) {
    $d = date('Y-m-d', strtotime("$i days"));
    $week[$d] = (int)q('SELECT COUNT(*) FROM workout_slots WHERE slot_date = ?', [$d])->fetchColumn();
}
$s = $editing ?: ['title' => 'Open Gym Session', 'slot_date' => $date, 'start_time' => '06:00', 'end_time' => '08:00', 'capacity' => 20, 'trainer' => '', 'notes' => ''];
$dayNames = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
require __DIR__ . '/includes/header.php';
?>
<div class="row g-3">
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header"><i class="bi bi-<?= $editing ? 'pencil' : 'plus-circle' ?>"></i> <?= $editing ? 'Edit Slot' : 'Create Workout Slots' ?></div>
            <div class="card-body">
                <form method="post" class="row g-2">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="<?= $editing ? 'update' : 'create' ?>">
                    <div class="col-12"><label class="form-label">Title</label><input name="title" class="form-control" value="<?= e($s['title']) ?>" required placeholder="e.g. Morning Session, HIIT Class"></div>
                    <?php if ($editing): ?>
                        <div class="col-12"><label class="form-label">Date</label><input type="date" name="slot_date" class="form-control" value="<?= e($s['slot_date']) ?>" required></div>
                    <?php else: ?>
                        <div class="col-6"><label class="form-label">From</label><input type="date" name="date_from" class="form-control" value="<?= e($date) ?>" required></div>
                        <div class="col-6"><label class="form-label">To</label><input type="date" name="date_to" class="form-control" value="<?= e($date) ?>"></div>
                        <div class="col-12"><label class="form-label">Repeat on (leave blank = every day)</label><div class="d-flex flex-wrap gap-2">
                            <?php foreach ($dayNames as $i => $dn): ?><div class="form-check form-check-inline m-0"><input class="form-check-input" type="checkbox" name="days[]" value="<?= $i ?>" id="d<?= $i ?>"><label class="form-check-label small" for="d<?= $i ?>"><?= $dn ?></label></div><?php endforeach; ?>
                        </div></div>
                    <?php endif; ?>
                    <div class="col-6"><label class="form-label">Start</label><input type="time" name="start_time" class="form-control" value="<?= e(substr($s['start_time'], 0, 5)) ?>" required></div>
                    <div class="col-6"><label class="form-label">End</label><input type="time" name="end_time" class="form-control" value="<?= e(substr($s['end_time'], 0, 5)) ?>" required></div>
                    <div class="col-6"><label class="form-label">Capacity</label><input type="number" min="1" name="capacity" class="form-control" value="<?= e($s['capacity']) ?>" required></div>
                    <div class="col-6"><label class="form-label">Trainer / Coach</label><input name="trainer" class="form-control" value="<?= e($s['trainer']) ?>"></div>
                    <div class="col-12"><label class="form-label">Notes</label><input name="notes" class="form-control" value="<?= e($s['notes']) ?>"></div>
                    <div class="col-12 d-grid gap-2 mt-2">
                        <button class="btn btn-primary"><i class="bi bi-save"></i> <?= $editing ? 'Save Slot' : 'Create Slot(s)' ?></button>
                        <?php if ($editing): ?><a href="slots.php?date=<?= e($s['slot_date']) ?>" class="btn btn-outline-light">Cancel</a><?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="d-flex flex-wrap gap-2 mb-3">
            <?php foreach ($week as $d => $cnt): ?>
                <a href="?date=<?= $d ?>" class="btn btn-sm <?= $d === $date ? 'btn-primary' : 'btn-outline-light' ?>">
                    <?= $d === today() ? 'Today' : date('D M d', strtotime($d)) ?> <span class="badge bg-dark"><?= $cnt ?></span></a>
            <?php endforeach; ?>
            <form class="ms-auto"><input type="date" name="date" value="<?= e($date) ?>" class="form-control form-control-sm" onchange="this.form.submit()"></form>
        </div>
        <div class="card">
            <div class="card-header"><i class="bi bi-calendar3-week"></i> Slots for <?= fmt_date($date, 'l, M d, Y') ?></div>
            <div class="table-responsive"><table class="table table-hover">
                <thead><tr><th>Time</th><th>Title</th><th>Trainer</th><th>Booked</th><th class="text-end">Actions</th></tr></thead>
                <tbody>
                <?php if (!$slots): ?><tr><td colspan="5"><div class="empty"><i class="bi bi-calendar-x"></i>No slots on this day. Create some using the form.</div></td></tr><?php endif; ?>
                <?php foreach ($slots as $sl): $pct = round($sl['booked'] / max(1, $sl['capacity']) * 100); ?>
                    <tr>
                        <td class="text-nowrap"><?= fmt_time($sl['start_time']) ?> – <?= fmt_time($sl['end_time']) ?></td>
                        <td><div class="fw-semibold"><?= e($sl['title']) ?></div><small class="text-secondary"><?= e($sl['notes']) ?></small></td>
                        <td><?= e($sl['trainer'] ?: '—') ?></td>
                        <td style="min-width:130px"><div class="d-flex align-items-center gap-2"><div class="progress flex-grow-1" style="height:6px"><div class="progress-bar <?= $pct >= 100 ? 'bg-secondary' : 'bg-danger' ?>" style="width:<?= $pct ?>%"></div></div><small><?= $sl['booked'] ?>/<?= $sl['capacity'] ?></small></div></td>
                        <td class="text-end text-nowrap">
                            <a href="reservations.php?slot=<?= $sl['id'] ?>" class="btn btn-sm btn-outline-light" title="View bookings"><i class="bi bi-list-check"></i></a>
                            <a href="?edit=<?= $sl['id'] ?>&date=<?= e($date) ?>" class="btn btn-sm btn-outline-light" title="Edit"><i class="bi bi-pencil"></i></a>
                            <form method="post" class="d-inline" data-confirm="Delete this slot and its <?= $sl['booked'] ?> booking(s)?"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $sl['id'] ?>"><button name="action" value="delete" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button></form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table></div>
        </div>
    </div>
</div>
<?php require __DIR__ . '/includes/footer.php';
