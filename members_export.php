<?php
require __DIR__ . '/includes/bootstrap.php';
require_role('admin', 'staff');
$rows = q('SELECT m.member_code, m.full_name, m.email, m.phone, m.gender, m.rfid_uid, p.name plan, m.membership_start, m.membership_end, m.status, m.created_at
    FROM members m LEFT JOIN membership_plans p ON p.id = m.plan_id ORDER BY m.full_name')->fetchAll();
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="members_' . date('Ymd') . '.csv"');
$out = fopen('php://output', 'w');
fputcsv($out, ['Member Code', 'Full Name', 'Email', 'Phone', 'Gender', 'RFID UID', 'Plan', 'Start', 'End', 'Status', 'Registered']);
foreach ($rows as $r) {
    fputcsv($out, array_values($r));
}
