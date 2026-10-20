<?php
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/rfid.php';
$user = require_role('admin', 'staff');
$page_title = 'Member Management';

if (is_post()) {
    verify_csrf();
    $id = (int)post('id');
    $action = post('action');
    if ($action === 'checkin') {
        $r = manual_checkin($id);
        flash($r['ok'] ? 'success' : 'danger', $r['message']);
    } elseif ($action === 'checkout') {
        manual_checkout($id);
        flash('success', 'Member checked out.');
    } elseif ($action === 'delete' && $user['role'] === 'admin') {
        $m = q('SELECT user_id, full_name FROM members WHERE id = ?', [$id])->fetch();
        if ($m) {
            q('DELETE FROM members WHERE id = ?', [$id]);
            if ($m['user_id']) {
                q("DELETE FROM users WHERE id = ? AND role = 'member'", [$m['user_id']]);
            }
            flash('success', 'Deleted member ' . $m['full_name'] . '.');
        }
    }
    redirect('members.php?' . http_build_query(array_intersect_key($_GET, array_flip(['search', 'status', 'filter', 'page']))));
}

$search = get('search');
$status = get('status');
$filter = get('filter');
$where = ['1=1'];
$params = [];
if ($search !== '') {
    $where[] = '(m.full_name LIKE ? OR m.member_code LIKE ? OR m.email LIKE ? OR m.phone LIKE ? OR m.rfid_uid LIKE ?)';
    array_push($params, ...array_fill(0, 5, "%$search%"));
}
if (in_array($status, ['active', 'inactive', 'suspended'], true)) {
    $where[] = 'm.status = ?';
    $params[] = $status;
}
if ($filter === 'inside') {
    $where[] = 'm.is_inside = 1';
} elseif ($filter === 'expired') {
    $where[] = 'm.membership_end < ?';
    $params[] = today();
} elseif ($filter === 'expiring') {
    $where[] = 'm.membership_end BETWEEN ? AND ?';
    array_push($params, today(), date('Y-m-d', strtotime('+7 days')));
} elseif ($filter === 'nocard') {
    $where[] = 'm.rfid_uid IS NULL';
}
$whereSql = implode(' AND ', $where);
$total = (int)q("SELECT COUNT(*) FROM members m WHERE $whereSql", $params)->fetchColumn();
$p = paginate($total);
$members = q("SELECT m.*, p.name plan_name FROM members m LEFT JOIN membership_plans p ON p.id = m.plan_id
    WHERE $whereSql ORDER BY m.full_name LIMIT {$p['limit']} OFFSET {$p['offset']}", $params)->fetchAll();

require __DIR__ . '/includes/header.php';
?>
<div class="card">
    <div class="card-header flex-wrap">
        <i class="bi bi-people"></i> Members <span class="badge bg-secondary"><?= $total ?></span>
        <div class="ms-auto d-flex gap-2">
            <a href="members_export.php?<?= e(http_build_query($_GET)) ?>" class="btn btn-sm btn-outline-light"><i class="bi bi-download"></i> CSV</a>
            <a href="member_form.php" class="btn btn-sm btn-primary"><i class="bi bi-person-plus"></i> Add Member</a>
        </div>
    </div>
    <div class="card-body border-bottom" style="border-color:var(--sg-border)!important">
        <form class="row g-2">
            <div class="col-md-5"><input type="search" name="search" value="<?= e($search) ?>" class="form-control" placeholder="Search name, code, email, phone, RFID UID…"></div>
            <div class="col-6 col-md-2">
                <select name="status" class="form-select">
                    <option value="">All status</option>
                    <?php foreach (['active', 'inactive', 'suspended'] as $s): ?><option value="<?= $s ?>" <?= $status === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="col-6 col-md-3">
                <select name="filter" class="form-select">
                    <option value="">All members</option>
                    <?php foreach (['inside' => 'Currently inside', 'expiring' => 'Expiring in 7 days', 'expired' => 'Expired', 'nocard' => 'No RFID card'] as $k => $v): ?>
                        <option value="<?= $k ?>" <?= $filter === $k ? 'selected' : '' ?>><?= $v ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2 d-grid"><button class="btn btn-outline-primary"><i class="bi bi-funnel"></i> Filter</button></div>
        </form>
    </div>
    <div class="table-responsive">
        <table class="table table-hover">
            <thead><tr><th>Code</th><th>Name</th><th>Contact</th><th>Plan</th><th>Valid Until</th><th>Status</th><th>RFID</th><th>Inside</th><th class="text-end">Actions</th></tr></thead>
            <tbody>
            <?php if (!$members): ?><tr><td colspan="9"><div class="empty"><i class="bi bi-person-x"></i>No members found.</div></td></tr><?php endif; ?>
            <?php foreach ($members as $m): [$sl, $sc] = membership_state($m); ?>
                <tr>
                    <td class="text-secondary small"><?= e($m['member_code']) ?></td>
                    <td><a href="member_view.php?id=<?= $m['id'] ?>" class="fw-semibold text-white text-decoration-none"><?= e($m['full_name']) ?></a></td>
                    <td class="small"><?= e($m['phone']) ?><br><span class="text-secondary"><?= e($m['email']) ?></span></td>
                    <td><?= e($m['plan_name'] ?? '—') ?></td>
                    <td class="text-nowrap"><?= fmt_date($m['membership_end']) ?></td>
                    <td><?= badge($sl, $sc) ?></td>
                    <td><?= $m['rfid_uid'] ? '<code>' . e($m['rfid_uid']) . '</code>' : '<span class="text-secondary small">None</span>' ?></td>
                    <td><?= $m['is_inside'] ? badge('IN', 'success') : '<span class="text-secondary small">Out</span>' ?></td>
                    <td class="text-end text-nowrap">
                        <form method="post" class="d-inline">
                            <?= csrf_field() ?><input type="hidden" name="id" value="<?= $m['id'] ?>">
                            <?php if ($m['is_inside']): ?>
                                <button name="action" value="checkout" class="btn btn-sm btn-outline-secondary" title="Manual check-out"><i class="bi bi-box-arrow-right"></i></button>
                            <?php else: ?>
                                <button name="action" value="checkin" class="btn btn-sm btn-outline-success" title="Manual check-in"><i class="bi bi-box-arrow-in-right"></i></button>
                            <?php endif; ?>
                        </form>
                        <a href="member_view.php?id=<?= $m['id'] ?>" class="btn btn-sm btn-outline-light" title="View"><i class="bi bi-eye"></i></a>
                        <a href="member_form.php?id=<?= $m['id'] ?>" class="btn btn-sm btn-outline-light" title="Edit"><i class="bi bi-pencil"></i></a>
                        <?php if ($user['role'] === 'admin'): ?>
                            <form method="post" class="d-inline" data-confirm="Delete <?= e($m['full_name']) ?> and all their records?">
                                <?= csrf_field() ?><input type="hidden" name="id" value="<?= $m['id'] ?>">
                                <button name="action" value="delete" class="btn btn-sm btn-outline-danger" title="Delete"><i class="bi bi-trash"></i></button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="card-body d-flex justify-content-between align-items-center">
        <small class="text-secondary">Showing <?= count($members) ?> of <?= $total ?></small>
        <?= pagination_links($p) ?>
    </div>
</div>
<?php require __DIR__ . '/includes/footer.php';
