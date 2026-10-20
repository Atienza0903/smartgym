<?php
require __DIR__ . '/includes/bootstrap.php';
require_role('admin', 'staff');
$page_title = 'Attendance / Access Logs';

$from = get('from', date('Y-m-d', strtotime('-6 days')));
$to = get('to', today());
$dir = get('direction');
$search = get('search');
$where = ['l.scanned_at >= ?', 'l.scanned_at < ?'];
$params = [$from . ' 00:00:00', date('Y-m-d', strtotime($to . ' +1 day')) . ' 00:00:00'];
if (in_array($dir, ['IN', 'OUT', 'DENIED'], true)) {
    $where[] = 'l.direction = ?';
    $params[] = $dir;
}
if ($search !== '') {
    $where[] = '(m.full_name LIKE ? OR m.member_code LIKE ? OR l.rfid_uid LIKE ?)';
    array_push($params, "%$search%", "%$search%", "%$search%");
}
$whereSql = implode(' AND ', $where);
$base = "FROM access_logs l LEFT JOIN members m ON m.id = l.member_id WHERE $whereSql";

if (get('export') === 'csv') {
    $rows = q("SELECT l.scanned_at, m.member_code, m.full_name, l.rfid_uid, l.direction, l.reason, l.source, l.device_id $base ORDER BY l.scanned_at DESC", $params)->fetchAll();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="access_logs_' . $from . '_to_' . $to . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Date/Time', 'Member Code', 'Member', 'RFID UID', 'Type', 'Note', 'Source', 'Device']);
    foreach ($rows as $r) {
        fputcsv($out, array_values($r));
    }
    exit;
}

$summary = q("SELECT SUM(l.direction='IN') ins, SUM(l.direction='OUT') outs, SUM(l.direction='DENIED') denied, COUNT(DISTINCT CASE WHEN l.direction='IN' THEN l.member_id END) uniq $base", $params)->fetch();
$total = (int)q("SELECT COUNT(*) $base", $params)->fetchColumn();
$p = paginate($total, 50);
$logs = q("SELECT l.*, m.full_name, m.member_code, m.id mid $base ORDER BY l.scanned_at DESC, l.id DESC LIMIT {$p['limit']} OFFSET {$p['offset']}", $params)->fetchAll();

require __DIR__ . '/includes/header.php';
?>
<div class="row g-3 mb-3">
    <?php foreach ([['Check-ins', $summary['ins'], 'box-arrow-in-right', 'bg-green-soft'], ['Check-outs', $summary['outs'], 'box-arrow-right', 'bg-blue-soft'], ['Denied', $summary['denied'], 'x-octagon', 'bg-red-soft'], ['Unique Members', $summary['uniq'], 'person-check', 'bg-amber-soft']] as [$l, $v, $i, $c]): ?>
        <div class="col-6 col-lg-3"><div class="card stat-card"><div class="stat-icon <?= $c ?>"><i class="bi bi-<?= $i ?>"></i></div><div><div class="stat-value"><?= (int)$v ?></div><div class="stat-label"><?= $l ?></div></div></div></div>
    <?php endforeach; ?>
</div>
<div class="card">
    <div class="card-header"><i class="bi bi-clock-history"></i> RFID Entry / Exit Records
        <a href="?<?= e(http_build_query(array_merge($_GET, ['export' => 'csv', 'page' => null]))) ?>" class="btn btn-sm btn-outline-light ms-auto"><i class="bi bi-download"></i> Export CSV</a>
    </div>
    <div class="card-body border-bottom" style="border-color:var(--sg-border)!important">
        <form class="row g-2">
            <div class="col-6 col-md-2"><input type="date" name="from" value="<?= e($from) ?>" class="form-control"></div>
            <div class="col-6 col-md-2"><input type="date" name="to" value="<?= e($to) ?>" class="form-control"></div>
            <div class="col-md-2"><select name="direction" class="form-select"><option value="">All types</option><?php foreach (['IN', 'OUT', 'DENIED'] as $d): ?><option <?= $dir === $d ? 'selected' : '' ?>><?= $d ?></option><?php endforeach; ?></select></div>
            <div class="col-md-4"><input type="search" name="search" value="<?= e($search) ?>" class="form-control" placeholder="Member name, code, or card UID"></div>
            <div class="col-md-2 d-grid"><button class="btn btn-outline-primary"><i class="bi bi-funnel"></i> Filter</button></div>
        </form>
    </div>
    <div class="table-responsive">
        <table class="table table-hover">
            <thead><tr><th>Date & Time</th><th>Member</th><th>Card UID</th><th>Type</th><th>Note</th><th>Source</th><th>Device</th></tr></thead>
            <tbody>
            <?php if (!$logs): ?><tr><td colspan="7"><div class="empty"><i class="bi bi-clock"></i>No records for this filter.</div></td></tr><?php endif; ?>
            <?php foreach ($logs as $l): ?>
                <tr>
                    <td class="text-nowrap"><?= fmt_datetime($l['scanned_at']) ?></td>
                    <td><?= $l['mid'] ? '<a href="member_view.php?id=' . $l['mid'] . '">' . e($l['full_name']) . '</a> <small class="text-secondary">' . e($l['member_code']) . '</small>' : '<span class="text-secondary">Unregistered</span> <a class="small" href="member_form.php?uid=' . e(urlencode($l['rfid_uid'])) . '">Register</a>' ?></td>
                    <td><code><?= e($l['rfid_uid']) ?></code></td>
                    <td><?= direction_badge($l['direction']) ?></td>
                    <td class="small"><?= e($l['reason']) ?></td>
                    <td class="small text-secondary"><?= e(ucfirst($l['source'])) ?></td>
                    <td class="small text-secondary"><?= e($l['device_id']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="card-body d-flex justify-content-between align-items-center"><small class="text-secondary"><?= $total ?> record(s)</small><?= pagination_links($p) ?></div>
</div>
<?php require __DIR__ . '/includes/footer.php';
