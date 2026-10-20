<?php
require __DIR__ . '/includes/bootstrap.php';
require_role('member');
$page_title = 'My Attendance';
$member = current_member();
$logs = [];
$p = paginate(0);
$monthly = [];
if ($member) {
    $total = (int)q('SELECT COUNT(*) FROM access_logs WHERE member_id = ?', [$member['id']])->fetchColumn();
    $p = paginate($total, 30);
    $logs = q("SELECT * FROM access_logs WHERE member_id = ? ORDER BY scanned_at DESC LIMIT {$p['limit']} OFFSET {$p['offset']}", [$member['id']])->fetchAll();
    $monthly = q("SELECT DATE_FORMAT(scanned_at, '%Y-%m') ym, COUNT(*) c FROM access_logs WHERE member_id = ? AND direction = 'IN' AND scanned_at >= ? GROUP BY ym ORDER BY ym",
        [$member['id'], date('Y-m-01', strtotime('-5 months'))])->fetchAll();
}
require __DIR__ . '/includes/header.php';
if (!$member): ?>
    <div class="alert alert-warning">Your account is not linked to a member profile.</div>
<?php else: ?>
<div class="row g-3 mb-3">
    <?php foreach ($monthly as $mo): ?>
        <div class="col-6 col-md-2"><div class="card stat-card"><div><div class="stat-value"><?= $mo['c'] ?></div><div class="stat-label"><?= date('M Y', strtotime($mo['ym'] . '-01')) ?></div></div></div></div>
    <?php endforeach; ?>
</div>
<div class="card">
    <div class="card-header"><i class="bi bi-clock-history"></i> Entry / Exit History</div>
    <div class="table-responsive"><table class="table table-hover">
        <thead><tr><th>Date & Time</th><th>Type</th><th>Note</th></tr></thead>
        <tbody>
        <?php if (!$logs): ?><tr><td colspan="3"><div class="empty"><i class="bi bi-clock"></i>No records yet.</div></td></tr><?php endif; ?>
        <?php foreach ($logs as $l): ?><tr><td><?= fmt_datetime($l['scanned_at']) ?></td><td><?= direction_badge($l['direction']) ?></td><td class="small"><?= e($l['reason']) ?></td></tr><?php endforeach; ?>
        </tbody>
    </table></div>
    <div class="card-body d-flex justify-content-end"><?= pagination_links($p) ?></div>
</div>
<?php endif;
require __DIR__ . '/includes/footer.php';
