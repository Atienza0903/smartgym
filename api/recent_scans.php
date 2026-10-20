<?php
require __DIR__ . '/../includes/bootstrap.php';
if (!has_role('admin', 'staff')) {
    json_response(['error' => 'Unauthorized'], 401);
}
$afterId = (int)get('after', 0);
$rows = q("SELECT l.id, l.direction, l.reason, l.rfid_uid, l.source, l.scanned_at, m.full_name, m.member_code
           FROM access_logs l LEFT JOIN members m ON m.id = l.member_id
           WHERE l.id > ? ORDER BY l.id DESC LIMIT 20", [$afterId])->fetchAll();
foreach ($rows as &$r) {
    $r['id'] = (int)$r['id'];
    $r['time'] = date('h:i:s A', strtotime($r['scanned_at']));
}
json_response(['scans' => $rows, 'capacity' => capacity_info()]);
