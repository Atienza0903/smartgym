<?php
require __DIR__ . '/includes/bootstrap.php';
require_role('admin');
$page_title = 'Gym Analytics & Reports';

$from = get('from', date('Y-m-d', strtotime('-29 days')));
$to = get('to', today());
if ($to < $from) [$from, $to] = [$to, $from];
$rangeStart = $from . ' 00:00:00';
$rangeEnd = date('Y-m-d', strtotime($to . ' +1 day')) . ' 00:00:00';
$days = max(1, (int)round((strtotime($to) - strtotime($from)) / 86400) + 1);
$rp = [$rangeStart, $rangeEnd];

// Peak hours
$byHour = array_fill(0, 24, 0);
foreach (q("SELECT HOUR(scanned_at) h, COUNT(*) c FROM access_logs WHERE direction='IN' AND scanned_at >= ? AND scanned_at < ? GROUP BY h", $rp)->fetchAll() as $r) {
    $byHour[(int)$r['h']] = (int)$r['c'];
}
// Busy days (MySQL DAYOFWEEK: 1=Sun)
$dayNames = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
$byDay = array_fill(0, 7, 0);
foreach (q("SELECT DAYOFWEEK(scanned_at) d, COUNT(*) c FROM access_logs WHERE direction='IN' AND scanned_at >= ? AND scanned_at < ? GROUP BY d", $rp)->fetchAll() as $r) {
    $byDay[(int)$r['d'] - 1] = (int)$r['c'];
}
// Heatmap weekday x hour
$heat = [];
$heatMax = 1;
foreach (q("SELECT DAYOFWEEK(scanned_at) d, HOUR(scanned_at) h, COUNT(*) c FROM access_logs WHERE direction='IN' AND scanned_at >= ? AND scanned_at < ? GROUP BY d, h", $rp)->fetchAll() as $r) {
    $heat[(int)$r['d'] - 1][(int)$r['h']] = (int)$r['c'];
    $heatMax = max($heatMax, (int)$r['c']);
}
// Daily trend
$daily = [];
for ($d = strtotime($from); $d <= strtotime($to); $d = strtotime('+1 day', $d)) {
    $daily[date('Y-m-d', $d)] = 0;
}
foreach (q("SELECT DATE(scanned_at) d, COUNT(DISTINCT member_id) c FROM access_logs WHERE direction='IN' AND scanned_at >= ? AND scanned_at < ? GROUP BY d", $rp)->fetchAll() as $r) {
    $daily[$r['d']] = (int)$r['c'];
}
$totals = q("SELECT SUM(direction='IN') visits, COUNT(DISTINCT CASE WHEN direction='IN' THEN member_id END) uniq, SUM(direction='DENIED') denied FROM access_logs WHERE scanned_at >= ? AND scanned_at < ?", $rp)->fetch();
$deniedReasons = q("SELECT reason, COUNT(*) c FROM access_logs WHERE direction='DENIED' AND scanned_at >= ? AND scanned_at < ? GROUP BY reason ORDER BY c DESC", $rp)->fetchAll();

// Average session duration (pair each IN with the member's next OUT)
$durations = [];
$open = [];
foreach (q("SELECT member_id, direction, scanned_at FROM access_logs WHERE member_id IS NOT NULL AND direction IN ('IN','OUT') AND scanned_at >= ? AND scanned_at < ? ORDER BY scanned_at, id", $rp) as $r) {
    if ($r['direction'] === 'IN') {
        $open[$r['member_id']] = strtotime($r['scanned_at']);
    } elseif (isset($open[$r['member_id']])) {
        $mins = (strtotime($r['scanned_at']) - $open[$r['member_id']]) / 60;
        if ($mins > 0 && $mins < 600) $durations[] = $mins;
        unset($open[$r['member_id']]);
    }
}
$avgDuration = $durations ? round(array_sum($durations) / count($durations)) : 0;

$topMembers = q("SELECT m.id, m.full_name, m.member_code, COUNT(*) visits FROM access_logs l JOIN members m ON m.id = l.member_id
    WHERE l.direction='IN' AND l.scanned_at >= ? AND l.scanned_at < ? GROUP BY m.id ORDER BY visits DESC LIMIT 10", $rp)->fetchAll();
$planDist = q("SELECT COALESCE(p.name, 'No plan') name, COUNT(*) c FROM members m LEFT JOIN membership_plans p ON p.id = m.plan_id WHERE m.status='active' AND m.membership_end >= ? GROUP BY name ORDER BY c DESC", [today()])->fetchAll();
$newMembers = q("SELECT DATE_FORMAT(created_at, '%Y-%m') ym, COUNT(*) c FROM members WHERE created_at >= ? GROUP BY ym ORDER BY ym", [date('Y-m-01', strtotime('-5 months'))])->fetchAll();
$resStats = q("SELECT r.status, COUNT(*) c FROM reservations r JOIN workout_slots s ON s.id = r.slot_id WHERE s.slot_date BETWEEN ? AND ? GROUP BY r.status", [$from, $to])->fetchAll(PDO::FETCH_KEY_PAIR);
$slotUtil = q("SELECT s.title, ROUND(AVG(b.cnt / s.capacity) * 100) util FROM workout_slots s
    JOIN (SELECT s2.id, (SELECT COUNT(*) FROM reservations r WHERE r.slot_id = s2.id AND r.status IN ('booked','attended')) cnt FROM workout_slots s2) b ON b.id = s.id
    WHERE s.slot_date BETWEEN ? AND ? GROUP BY s.title ORDER BY util DESC", [$from, $to])->fetchAll();
$maint = q("SELECT COUNT(*) tickets, SUM(status='resolved') resolved, COALESCE(SUM(cost),0) cost FROM maintenance_logs WHERE reported_at >= ? AND reported_at < ?", $rp)->fetch();

$peakHour = array_search(max($byHour), $byHour);
$busiestDay = $dayNames[array_search(max($byDay), $byDay)];

require __DIR__ . '/includes/header.php';
?>
<form class="row g-2 mb-3 align-items-end">
    <div class="col-auto"><label class="form-label">From</label><input type="date" name="from" value="<?= e($from) ?>" class="form-control"></div>
    <div class="col-auto"><label class="form-label">To</label><input type="date" name="to" value="<?= e($to) ?>" class="form-control"></div>
    <div class="col-auto"><button class="btn btn-primary"><i class="bi bi-arrow-repeat"></i> Update</button></div>
    <div class="col-auto">
        <?php foreach ([7 => '7 days', 30 => '30 days', 90 => '90 days'] as $n => $l): ?>
            <a class="btn btn-outline-light" href="?from=<?= date('Y-m-d', strtotime('-' . ($n - 1) . ' days')) ?>&to=<?= today() ?>"><?= $l ?></a>
        <?php endforeach; ?>
    </div>
    <div class="col-auto ms-auto"><button type="button" class="btn btn-outline-light" onclick="window.print()"><i class="bi bi-printer"></i> Print Report</button></div>
</form>

<div class="row g-3 mb-3">
    <?php foreach ([
        ['Total Visits', (int)$totals['visits'], 'door-open', 'bg-red-soft'],
        ['Unique Members', (int)$totals['uniq'], 'people', 'bg-blue-soft'],
        ['Avg Visits / Day', round($totals['visits'] / $days, 1), 'calendar-day', 'bg-green-soft'],
        ['Peak Hour', $totals['visits'] ? date('g A', mktime($peakHour, 0)) : '—', 'clock', 'bg-amber-soft'],
        ['Busiest Day', $totals['visits'] ? $busiestDay : '—', 'calendar-week', 'bg-red-soft'],
        ['Avg Workout', $avgDuration ? $avgDuration . ' min' : '—', 'stopwatch', 'bg-blue-soft'],
    ] as [$l, $v, $i, $c]): ?>
        <div class="col-6 col-md-4 col-xl-2"><div class="card stat-card"><div class="stat-icon <?= $c ?>"><i class="bi bi-<?= $i ?>"></i></div><div><div class="stat-value fs-4"><?= e($v) ?></div><div class="stat-label"><?= $l ?></div></div></div></div>
    <?php endforeach; ?>
</div>

<div class="row g-3 mb-3">
    <div class="col-lg-8"><div class="card h-100"><div class="card-header"><i class="bi bi-clock"></i> Peak Hours (total check-ins per hour)</div><div class="card-body"><canvas id="hourChart" height="110"></canvas></div></div></div>
    <div class="col-lg-4"><div class="card h-100"><div class="card-header"><i class="bi bi-calendar-week"></i> Busy Days</div><div class="card-body"><canvas id="dayChart" height="220"></canvas></div></div></div>
</div>
<div class="row g-3 mb-3">
    <div class="col-12"><div class="card"><div class="card-header"><i class="bi bi-graph-up"></i> Daily Unique Visitors</div><div class="card-body"><canvas id="dailyChart" height="70"></canvas></div></div></div>
</div>
<div class="card mb-3">
    <div class="card-header"><i class="bi bi-grid-3x3"></i> Traffic Heatmap (day × hour)</div>
    <div class="card-body table-responsive">
        <table class="table table-sm text-center small mb-0" style="table-layout:fixed;min-width:760px">
            <thead><tr><th style="width:50px"></th><?php for ($h = 5; $h <= 22; $h++): ?><th><?= date('ga', mktime($h, 0)) ?></th><?php endfor; ?></tr></thead>
            <tbody>
            <?php foreach ($dayNames as $di => $dn): ?>
                <tr><th class="text-start"><?= $dn ?></th>
                    <?php for ($h = 5; $h <= 22; $h++): $v = $heat[$di][$h] ?? 0; $a = $v ? 0.12 + 0.88 * $v / $heatMax : 0; ?>
                        <td style="background:rgba(215,25,32,<?= round($a, 2) ?>)" title="<?= $dn ?> <?= date('g A', mktime($h, 0)) ?>: <?= $v ?> check-ins"><?= $v ?: '' ?></td>
                    <?php endfor; ?>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<div class="row g-3 mb-3">
    <div class="col-lg-4"><div class="card h-100"><div class="card-header"><i class="bi bi-pie-chart"></i> Active Members by Plan</div><div class="card-body"><canvas id="planChart" height="220"></canvas></div></div></div>
    <div class="col-lg-4"><div class="card h-100"><div class="card-header"><i class="bi bi-person-plus"></i> New Members (6 months)</div><div class="card-body"><canvas id="newChart" height="220"></canvas></div></div></div>
    <div class="col-lg-4"><div class="card h-100"><div class="card-header"><i class="bi bi-calendar-check"></i> Reservations</div><div class="card-body">
        <canvas id="resChart" height="160"></canvas>
        <?php if ($slotUtil): ?><hr><div class="small fw-semibold mb-2">Avg slot utilization</div>
            <?php foreach ($slotUtil as $su): ?><div class="d-flex justify-content-between small"><span><?= e($su['title']) ?></span><span><?= (int)$su['util'] ?>%</span></div>
            <div class="progress mb-2" style="height:5px"><div class="progress-bar bg-danger" style="width:<?= (int)$su['util'] ?>%"></div></div><?php endforeach; ?>
        <?php endif; ?>
    </div></div></div>
</div>
<div class="row g-3">
    <div class="col-lg-6"><div class="card h-100"><div class="card-header"><i class="bi bi-trophy"></i> Most Active Members</div>
        <div class="table-responsive"><table class="table table-hover"><thead><tr><th>#</th><th>Member</th><th class="text-end">Visits</th></tr></thead><tbody>
            <?php if (!$topMembers): ?><tr><td colspan="3"><div class="empty">No data.</div></td></tr><?php endif; ?>
            <?php foreach ($topMembers as $i => $tm): ?><tr><td><?= $i + 1 ?></td><td><a href="member_view.php?id=<?= $tm['id'] ?>"><?= e($tm['full_name']) ?></a> <small class="text-secondary"><?= e($tm['member_code']) ?></small></td><td class="text-end fw-bold"><?= $tm['visits'] ?></td></tr><?php endforeach; ?>
        </tbody></table></div></div></div>
    <div class="col-lg-6"><div class="card h-100"><div class="card-header"><i class="bi bi-shield-exclamation"></i> Access Denials & Maintenance</div><div class="card-body">
        <div class="row text-center mb-3">
            <div class="col"><div class="stat-value"><?= (int)$totals['denied'] ?></div><div class="stat-label">Denied Taps</div></div>
            <div class="col"><div class="stat-value"><?= (int)$maint['tickets'] ?></div><div class="stat-label">Tickets</div></div>
            <div class="col"><div class="stat-value"><?= (int)$maint['resolved'] ?></div><div class="stat-label">Resolved</div></div>
            <div class="col"><div class="stat-value fs-5 pt-1"><?= peso($maint['cost']) ?></div><div class="stat-label">Repair Cost</div></div>
        </div>
        <?php foreach ($deniedReasons as $dr): ?><div class="d-flex justify-content-between border-bottom py-1 small" style="border-color:var(--sg-border)!important"><span><?= e($dr['reason']) ?></span><b><?= $dr['c'] ?></b></div><?php endforeach; ?>
    </div></div></div>
</div>
<?php
$j = fn($v) => json_encode($v);
$hoursRange = range(5, 22);
$page_scripts = '<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
Chart.defaults.color = "#9aa3ae"; Chart.defaults.borderColor = "#2e3540";
const red = "#d71920", pal = ["#d71920","#ff6b6b","#ffc107","#0dcaf0","#6ea8fe","#4cd38f","#adb5bd"];
const noLegend = { plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { precision: 0 } } } };
new Chart(hourChart, { type: "bar", data: { labels: ' . $j(array_map(fn($h) => date('g A', mktime($h, 0)), $hoursRange)) . ', datasets: [{ data: ' . $j(array_map(fn($h) => $byHour[$h], $hoursRange)) . ', backgroundColor: red, borderRadius: 6 }] }, options: noLegend });
new Chart(dayChart, { type: "bar", data: { labels: ' . $j($dayNames) . ', datasets: [{ data: ' . $j($byDay) . ', backgroundColor: "#ff6b6b", borderRadius: 6 }] }, options: noLegend });
new Chart(dailyChart, { type: "line", data: { labels: ' . $j(array_map(fn($d) => date('M d', strtotime($d)), array_keys($daily))) . ', datasets: [{ data: ' . $j(array_values($daily)) . ', borderColor: red, backgroundColor: "rgba(215,25,32,.15)", fill: true, tension: .3 }] }, options: noLegend });
new Chart(planChart, { type: "doughnut", data: { labels: ' . $j(array_column($planDist, 'name')) . ', datasets: [{ data: ' . $j(array_map('intval', array_column($planDist, 'c'))) . ', backgroundColor: pal, borderWidth: 0 }] }, options: { plugins: { legend: { position: "bottom" } } } });
new Chart(newChart, { type: "bar", data: { labels: ' . $j(array_map(fn($r) => date('M Y', strtotime($r['ym'] . '-01')), $newMembers)) . ', datasets: [{ data: ' . $j(array_map('intval', array_column($newMembers, 'c'))) . ', backgroundColor: "#0dcaf0", borderRadius: 6 }] }, options: noLegend });
new Chart(resChart, { type: "doughnut", data: { labels: ["Booked","Attended","Cancelled","No Show"], datasets: [{ data: ' . $j([(int)($resStats['booked'] ?? 0), (int)($resStats['attended'] ?? 0), (int)($resStats['cancelled'] ?? 0), (int)($resStats['no_show'] ?? 0)]) . ', backgroundColor: ["#6ea8fe","#4cd38f","#adb5bd",red], borderWidth: 0 }] }, options: { plugins: { legend: { position: "bottom" } } } });
</script>';
require __DIR__ . '/includes/footer.php';
