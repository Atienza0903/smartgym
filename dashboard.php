<?php
require __DIR__ . '/includes/bootstrap.php';
$user = require_login();
$page_title = 'Dashboard';
$today = today();

if ($user['role'] === 'member') {
    $member = current_member();
    $upcoming = $member ? q("SELECT r.*, s.title, s.slot_date, s.start_time, s.end_time, s.trainer FROM reservations r
        JOIN workout_slots s ON s.id = r.slot_id
        WHERE r.member_id = ? AND r.status = 'booked' AND CONCAT(s.slot_date, ' ', s.end_time) >= ?
        ORDER BY s.slot_date, s.start_time LIMIT 5", [$member['id'], now()])->fetchAll() : [];
    $recent = $member ? q('SELECT * FROM access_logs WHERE member_id = ? ORDER BY scanned_at DESC LIMIT 8', [$member['id']])->fetchAll() : [];
    $visitsMonth = $member ? (int)q("SELECT COUNT(*) FROM access_logs WHERE member_id = ? AND direction = 'IN' AND scanned_at >= ?", [$member['id'], date('Y-m-01')])->fetchColumn() : 0;
    require __DIR__ . '/includes/header.php';
    if (!$member): ?>
        <div class="alert alert-warning">Your account is not yet linked to a member profile. Please contact the front desk.</div>
    <?php else:
        [$stLabel, $stColor] = membership_state($member);
        $daysLeft = $member['membership_end'] ? (int)floor((strtotime($member['membership_end']) - strtotime($today)) / 86400) : null;
        ?>
        <div class="row g-3 mb-3">
            <div class="col-lg-8">
                <div class="card h-100">
                    <div class="card-header"><i class="bi bi-person-badge"></i> Membership Status</div>
                    <div class="card-body">
                        <div class="d-flex flex-wrap align-items-center gap-3 mb-3">
                            <div class="stat-icon bg-red-soft"><i class="bi bi-person-fill"></i></div>
                            <div>
                                <div class="fs-4 fw-bold text-white"><?= e($member['full_name']) ?></div>
                                <div class="text-secondary"><?= e($member['member_code']) ?> · <?= e($member['plan_name'] ?? 'No plan') ?></div>
                            </div>
                            <div class="ms-auto fs-5"><?= badge($stLabel, $stColor) ?></div>
                        </div>
                        <div class="row g-3 text-center">
                            <div class="col-6 col-md-3"><div class="stat-value"><?= $daysLeft === null ? '—' : max(0, $daysLeft) ?></div><div class="stat-label">Days Left</div></div>
                            <div class="col-6 col-md-3"><div class="stat-value fs-5 pt-2"><?= fmt_date($member['membership_end']) ?></div><div class="stat-label">Valid Until</div></div>
                            <div class="col-6 col-md-3"><div class="stat-value"><?= $visitsMonth ?></div><div class="stat-label">Visits This Month</div></div>
                            <div class="col-6 col-md-3"><div class="stat-value"><?= $member['is_inside'] ? '<span class="text-success">IN</span>' : '<span class="text-secondary">OUT</span>' ?></div><div class="stat-label">Current Status</div></div>
                        </div>
                        <?php if (!$member['rfid_uid']): ?>
                            <div class="alert alert-warning mt-3 mb-0 py-2"><i class="bi bi-credit-card-2-front me-1"></i>No RFID card registered yet. Ask the front desk to assign one.</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="card h-100">
                    <div class="card-header"><i class="bi bi-people-fill"></i> Gym Capacity (Live)</div>
                    <div class="card-body"><?php require __DIR__ . '/includes/gauge.php'; ?></div>
                </div>
            </div>
        </div>
        <div class="row g-3">
            <div class="col-lg-6">
                <div class="card h-100">
                    <div class="card-header"><i class="bi bi-calendar-check"></i> Upcoming Reservations <a href="reservations.php" class="btn btn-sm btn-primary ms-auto">Book a Slot</a></div>
                    <?php if (!$upcoming): ?><div class="empty"><i class="bi bi-calendar-x"></i>No upcoming reservations.</div>
                    <?php else: ?>
                        <ul class="list-group list-group-flush">
                            <?php foreach ($upcoming as $r): ?>
                                <li class="list-group-item d-flex justify-content-between">
                                    <div><div class="fw-semibold"><?= e($r['title']) ?></div><small class="text-secondary"><?= e($r['trainer'] ?: 'Open gym') ?></small></div>
                                    <div class="text-end"><div><?= fmt_date($r['slot_date'], 'D, M d') ?></div><small class="text-secondary"><?= fmt_time($r['start_time']) ?> – <?= fmt_time($r['end_time']) ?></small></div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="card h-100">
                    <div class="card-header"><i class="bi bi-clock-history"></i> Recent Attendance <a href="my_attendance.php" class="btn btn-sm btn-outline-light ms-auto">View All</a></div>
                    <?php if (!$recent): ?><div class="empty"><i class="bi bi-clock"></i>No attendance records yet.</div>
                    <?php else: ?>
                        <div class="table-responsive"><table class="table table-hover">
                            <thead><tr><th>Date & Time</th><th>Type</th><th>Note</th></tr></thead>
                            <tbody><?php foreach ($recent as $l): ?>
                                <tr><td><?= fmt_datetime($l['scanned_at']) ?></td><td><?= direction_badge($l['direction']) ?></td><td class="text-secondary small"><?= e($l['reason']) ?></td></tr>
                            <?php endforeach; ?></tbody>
                        </table></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endif;
    require __DIR__ . '/includes/footer.php';
    exit;
}

// ---- Admin / Staff dashboard ----
$stats = [
    'members' => (int)q("SELECT COUNT(*) FROM members WHERE status = 'active' AND membership_end >= ?", [$today])->fetchColumn(),
    'total_members' => (int)q('SELECT COUNT(*) FROM members')->fetchColumn(),
    'visits_today' => (int)q("SELECT COUNT(DISTINCT member_id) FROM access_logs WHERE direction = 'IN' AND scanned_at >= ?", [$today])->fetchColumn(),
    'res_today' => (int)q("SELECT COUNT(*) FROM reservations r JOIN workout_slots s ON s.id = r.slot_id WHERE s.slot_date = ? AND r.status IN ('booked','attended')", [$today])->fetchColumn(),
    'expiring' => (int)q("SELECT COUNT(*) FROM members WHERE status = 'active' AND membership_end BETWEEN ? AND ?", [$today, date('Y-m-d', strtotime('+7 days'))])->fetchColumn(),
    'equip_issues' => (int)q("SELECT COUNT(*) FROM equipment WHERE status <> 'operational'")->fetchColumn(),
    'denied_today' => (int)q("SELECT COUNT(*) FROM access_logs WHERE direction = 'DENIED' AND scanned_at >= ?", [$today])->fetchColumn(),
];
$hourly = array_fill(0, 24, 0);
foreach (q("SELECT HOUR(scanned_at) h, COUNT(*) c FROM access_logs WHERE direction = 'IN' AND scanned_at >= ? GROUP BY h", [$today])->fetchAll() as $r) {
    $hourly[(int)$r['h']] = (int)$r['c'];
}
$openH = (int)substr(setting('open_time', '06:00'), 0, 2);
$closeH = max($openH + 1, (int)substr(setting('close_time', '22:00'), 0, 2));
$hours = range($openH, min(23, $closeH));
$recentScans = q('SELECT l.*, m.full_name FROM access_logs l LEFT JOIN members m ON m.id = l.member_id ORDER BY l.id DESC LIMIT 8')->fetchAll();
$todaySlots = q("SELECT s.*, (SELECT COUNT(*) FROM reservations r WHERE r.slot_id = s.id AND r.status IN ('booked','attended')) booked
    FROM workout_slots s WHERE s.slot_date = ? ORDER BY s.start_time", [$today])->fetchAll();
$tickets = q("SELECT t.*, e.name equipment FROM maintenance_logs t JOIN equipment e ON e.id = t.equipment_id WHERE t.status <> 'resolved'
    ORDER BY FIELD(t.priority,'critical','high','medium','low'), t.reported_at LIMIT 5")->fetchAll();
$expiring = q("SELECT id, full_name, member_code, membership_end FROM members WHERE status = 'active' AND membership_end BETWEEN ? AND ? ORDER BY membership_end LIMIT 5",
    [$today, date('Y-m-d', strtotime('+7 days'))])->fetchAll();

require __DIR__ . '/includes/header.php';
?>
<div class="row g-3 mb-3">
    <?php foreach ([
        ['Active Members', $stats['members'] . '<small class="fs-6 text-secondary"> / ' . $stats['total_members'] . '</small>', 'people-fill', 'bg-red-soft', 'members.php'],
        ['Visits Today', $stats['visits_today'], 'door-open-fill', 'bg-green-soft', 'access_logs.php?from=' . $today],
        ['Reservations Today', $stats['res_today'], 'calendar-check-fill', 'bg-blue-soft', 'reservations.php?date=' . $today],
        ['Equipment Issues', $stats['equip_issues'], 'tools', 'bg-amber-soft', 'equipment.php'],
    ] as [$label, $value, $icon, $cls, $link]): ?>
        <div class="col-6 col-xl-3">
            <a href="<?= e($link) ?>" class="text-decoration-none"><div class="card stat-card">
                <div class="stat-icon <?= $cls ?>"><i class="bi bi-<?= $icon ?>"></i></div>
                <div><div class="stat-value"><?= $value ?></div><div class="stat-label"><?= e($label) ?></div></div>
            </div></a>
        </div>
    <?php endforeach; ?>
</div>

<div class="row g-3 mb-3">
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header"><i class="bi bi-people-fill"></i> Real-Time Capacity</div>
            <div class="card-body d-flex flex-column justify-content-center">
                <?php require __DIR__ . '/includes/gauge.php'; ?>
                <div class="d-flex justify-content-around mt-3 small text-secondary">
                    <span><i class="bi bi-x-octagon text-danger"></i> Denied today: <b class="text-white"><?= $stats['denied_today'] ?></b></span>
                    <span><i class="bi bi-hourglass-split text-warning"></i> Expiring: <b class="text-white"><?= $stats['expiring'] ?></b></span>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="card h-100">
            <div class="card-header"><i class="bi bi-graph-up"></i> Today's Check-ins by Hour</div>
            <div class="card-body"><canvas id="hourlyChart" height="120"></canvas></div>
        </div>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-lg-7">
        <div class="card h-100">
            <div class="card-header"><i class="bi bi-broadcast"></i> Recent RFID Activity <a href="rfid_monitor.php" class="btn btn-sm btn-outline-light ms-auto">Live Monitor</a></div>
            <?php if (!$recentScans): ?><div class="empty"><i class="bi bi-broadcast"></i>No scans yet.</div><?php else: ?>
            <div class="table-responsive"><table class="table table-hover">
                <thead><tr><th>Time</th><th>Member</th><th>Type</th><th>Note</th></tr></thead>
                <tbody><?php foreach ($recentScans as $s): ?>
                    <tr>
                        <td class="text-nowrap"><?= fmt_datetime($s['scanned_at']) ?></td>
                        <td><?= $s['full_name'] ? e($s['full_name']) : '<span class="text-secondary">Unknown (' . e($s['rfid_uid']) . ')</span>' ?></td>
                        <td><?= direction_badge($s['direction']) ?></td>
                        <td class="small text-secondary"><?= e($s['reason']) ?></td>
                    </tr>
                <?php endforeach; ?></tbody>
            </table></div>
            <?php endif; ?>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card h-100">
            <div class="card-header"><i class="bi bi-calendar3-week"></i> Today's Workout Slots <a href="slots.php" class="btn btn-sm btn-outline-light ms-auto">Manage</a></div>
            <?php if (!$todaySlots): ?><div class="empty"><i class="bi bi-calendar-x"></i>No slots scheduled today.</div><?php else: ?>
            <ul class="list-group list-group-flush">
                <?php foreach ($todaySlots as $s): $pct = $s['capacity'] ? round($s['booked'] / $s['capacity'] * 100) : 0; ?>
                    <li class="list-group-item">
                        <div class="d-flex justify-content-between"><span class="fw-semibold"><?= e($s['title']) ?></span><small><?= fmt_time($s['start_time']) ?> – <?= fmt_time($s['end_time']) ?></small></div>
                        <div class="d-flex align-items-center gap-2 mt-1">
                            <div class="progress flex-grow-1" style="height:6px"><div class="progress-bar bg-danger" style="width:<?= $pct ?>%"></div></div>
                            <small class="text-secondary"><?= $s['booked'] ?>/<?= $s['capacity'] ?></small>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-7">
        <div class="card h-100">
            <div class="card-header"><i class="bi bi-wrench-adjustable"></i> Open Maintenance Tickets <a href="equipment.php" class="btn btn-sm btn-outline-light ms-auto">View All</a></div>
            <?php if (!$tickets): ?><div class="empty"><i class="bi bi-check2-circle"></i>All equipment operational.</div><?php else: ?>
            <div class="table-responsive"><table class="table table-hover">
                <thead><tr><th>Equipment</th><th>Issue</th><th>Priority</th><th>Status</th></tr></thead>
                <tbody><?php foreach ($tickets as $t): ?>
                    <tr><td><?= e($t['equipment']) ?></td><td class="small"><?= e(mb_strimwidth($t['issue'], 0, 60, '…')) ?></td><td><?= priority_badge($t['priority']) ?></td><td><?= ticket_badge($t['status']) ?></td></tr>
                <?php endforeach; ?></tbody>
            </table></div>
            <?php endif; ?>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card h-100">
            <div class="card-header"><i class="bi bi-hourglass-split"></i> Memberships Expiring (7 days)</div>
            <?php if (!$expiring): ?><div class="empty"><i class="bi bi-emoji-smile"></i>No memberships expiring soon.</div><?php else: ?>
            <ul class="list-group list-group-flush">
                <?php foreach ($expiring as $m): ?>
                    <li class="list-group-item d-flex justify-content-between">
                        <a href="member_view.php?id=<?= $m['id'] ?>"><?= e($m['full_name']) ?></a>
                        <span class="text-warning small"><?= fmt_date($m['membership_end']) ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php
$labels = array_map(fn($h) => date('g A', mktime($h, 0)), $hours);
$data = array_map(fn($h) => $hourly[$h], $hours);
$page_scripts = '<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
Chart.defaults.color = "#9aa3ae"; Chart.defaults.borderColor = "#2e3540";
new Chart(document.getElementById("hourlyChart"), { type: "bar",
  data: { labels: ' . json_encode($labels) . ', datasets: [{ label: "Check-ins", data: ' . json_encode($data) . ', backgroundColor: "#d71920", borderRadius: 6 }] },
  options: { plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { precision: 0 } } } } });
</script>';
require __DIR__ . '/includes/footer.php';
