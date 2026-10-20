<?php
/** Generates realistic demo data (members, 60 days of RFID logs, slots, reservations, maintenance). */
function seed_demo(): void
{
    mt_srand(2026);
    $first = ['Juan', 'Maria', 'Jose', 'Ana', 'Mark', 'Kristine', 'John Paul', 'Angelica', 'Carlo', 'Jasmine', 'Miguel', 'Patricia', 'Rafael', 'Camille', 'Paolo',
        'Nicole', 'Christian', 'Bea', 'Gabriel', 'Sofia', 'Joshua', 'Trisha', 'Kevin', 'Andrea', 'Ramon', 'Liza', 'Daniel', 'Hazel', 'Adrian', 'Mae', 'Enzo', 'Rica',
        'Vince', 'Joy', 'Ryan', 'Clarisse', 'Bryan', 'Denise', 'Aldrin', 'Faith'];
    $last = ['Santos', 'Reyes', 'Cruz', 'Bautista', 'Garcia', 'Mendoza', 'Ramos', 'Aquino', 'Castillo', 'Villanueva', 'Torres', 'Flores', 'Navarro', 'Dizon',
        'Manalo', 'Mercado', 'Pascual', 'Soriano', 'Rivera', 'Domingo', 'Cabanhao', 'Matalog', 'Panganiban', 'Lopez'];
    $plans = q('SELECT id, duration_days FROM membership_plans')->fetchAll(PDO::FETCH_KEY_PAIR);
    $planIds = array_keys($plans);
    $today = strtotime(today());

    // Existing default member gets history too.
    $memberIds = q('SELECT id FROM members')->fetchAll(PDO::FETCH_COLUMN);
    $used = [];
    for ($i = 0; $i < 45; $i++) {
        do {
            $name = $first[mt_rand(0, count($first) - 1)] . ' ' . $last[mt_rand(0, count($last) - 1)];
        } while (isset($used[$name]));
        $used[$name] = true;
        $planId = $planIds[mt_rand(1, count($planIds) - 1)];
        $joined = strtotime('-' . mt_rand(0, 170) . ' days', $today);
        $r = mt_rand(1, 100);
        if ($r <= 12) {
            $end = strtotime('-' . mt_rand(1, 20) . ' days', $today);      // expired
        } elseif ($r <= 22) {
            $end = strtotime('+' . mt_rand(0, 6) . ' days', $today);       // expiring soon
        } else {
            $end = strtotime('+' . mt_rand(8, 200) . ' days', $today);
        }
        $start = strtotime('-' . ($plans[$planId] - 1) . ' days', $end);
        $email = strtolower(str_replace(' ', '.', $name)) . $i . '@example.com';
        q('INSERT INTO members (member_code, full_name, email, phone, gender, rfid_uid, plan_id, membership_start, membership_end, status, created_at)
           VALUES (?,?,?,?,?,?,?,?,?,?,?)', [
            next_member_code(), $name, $email, '09' . mt_rand(100000000, 999999999), mt_rand(0, 1) ? 'Male' : 'Female',
            strtoupper(bin2hex(random_bytes(4))), $planId, date('Y-m-d', $start), date('Y-m-d', $end), mt_rand(1, 30) === 1 ? 'suspended' : 'active',
            date('Y-m-d H:i:s', $joined),
        ]);
        $memberIds[] = (int)db()->lastInsertId();
    }

    // Attendance logs: weighted toward morning (6-8) and evening (17-20) peaks, busier on weekdays.
    $hourWeights = [5 => 2, 6 => 9, 7 => 10, 8 => 6, 9 => 4, 10 => 3, 11 => 2, 12 => 3, 13 => 2, 14 => 2, 15 => 3, 16 => 6, 17 => 11, 18 => 13, 19 => 10, 20 => 5, 21 => 2];
    $pickHour = function () use ($hourWeights) {
        $t = mt_rand(1, array_sum($hourWeights));
        foreach ($hourWeights as $h => $w) {
            if (($t -= $w) <= 0) return $h;
        }
        return 18;
    };
    $dayFactor = [0 => 0.35, 1 => 1.0, 2 => 0.9, 3 => 0.95, 4 => 0.85, 5 => 0.8, 6 => 0.6];
    $members = q('SELECT id, rfid_uid, membership_end, created_at FROM members')->fetchAll();
    $ins = db()->prepare('INSERT INTO access_logs (member_id, rfid_uid, direction, reason, device_id, source, scanned_at) VALUES (?,?,?,?,?,?,?)');
    db()->beginTransaction();
    foreach ($members as $m) {
        $habit = mt_rand(20, 65) / 100;
        for ($d = 60; $d >= 1; $d--) {
            $day = strtotime("-$d days", $today);
            if ($day < strtotime(substr($m['created_at'], 0, 10))) continue;
            if (mt_rand(1, 100) > $habit * 100 * $dayFactor[(int)date('w', $day)]) continue;
            $inTs = $day + $pickHour() * 3600 + mt_rand(0, 59) * 60;
            if (date('Y-m-d', $day) > $m['membership_end']) {
                $ins->execute([$m['id'], $m['rfid_uid'], 'DENIED', 'Membership expired', 'ESP32-01', 'rfid', date('Y-m-d H:i:s', $inTs)]);
                continue;
            }
            $outTs = $inTs + mt_rand(40, 130) * 60;
            $ins->execute([$m['id'], $m['rfid_uid'], 'IN', null, 'ESP32-01', 'rfid', date('Y-m-d H:i:s', $inTs)]);
            $ins->execute([$m['id'], $m['rfid_uid'], 'OUT', null, 'ESP32-01', 'rfid', date('Y-m-d H:i:s', $outTs)]);
        }
    }
    foreach (['04A3B2C1', '7F21DE90', 'C0FFEE12'] as $i => $uid) {
        $ins->execute([null, $uid, 'DENIED', 'Unregistered card', 'ESP32-01', 'rfid', date('Y-m-d H:i:s', strtotime('-' . ($i * 9 + 2) . ' days 18:10', $today))]);
    }
    db()->commit();

    // Today: a handful of members inside right now and some who already left.
    $nowTs = time();
    $active = q("SELECT id, rfid_uid FROM members WHERE status='active' AND membership_end >= ? ORDER BY RAND(2026)", [today()])->fetchAll();
    $opened = strtotime(today() . ' ' . setting('open_time', '06:00'));
    if ($nowTs > $opened + 600) {
        foreach (array_slice($active, 0, 14) as $i => $m) {
            $inTs = $i < 6 ? mt_rand($opened, $nowTs - 300) : mt_rand(max($opened, $nowTs - 6000), $nowTs - 120);
            q('INSERT INTO access_logs (member_id, rfid_uid, direction, device_id, source, scanned_at) VALUES (?,?,?,?,?,?)', [$m['id'], $m['rfid_uid'], 'IN', 'ESP32-01', 'rfid', date('Y-m-d H:i:s', $inTs)]);
            if ($i < 6) {
                $outTs = min($nowTs - 60, $inTs + mt_rand(40, 110) * 60);
                q('INSERT INTO access_logs (member_id, rfid_uid, direction, device_id, source, scanned_at) VALUES (?,?,?,?,?,?)', [$m['id'], $m['rfid_uid'], 'OUT', 'ESP32-01', 'rfid', date('Y-m-d H:i:s', $outTs)]);
                q('UPDATE members SET last_scan_at = ? WHERE id = ?', [date('Y-m-d H:i:s', $outTs), $m['id']]);
            } else {
                q('UPDATE members SET is_inside = 1, last_scan_at = ? WHERE id = ?', [date('Y-m-d H:i:s', $inTs), $m['id']]);
            }
        }
    }

    // Workout slots for the past 14 days and next 7 days, with reservations.
    $templates = [['Early Bird Session', '06:00', '08:00', 25, 'Coach Mark'], ['Midday Session', '11:00', '13:00', 20, null],
        ['After-Work Session', '17:00', '19:00', 30, 'Coach Liza'], ['Night Session', '19:00', '21:00', 25, 'Coach Ramon']];
    $activeIds = array_column($active, 'id');
    for ($d = -14; $d <= 7; $d++) {
        $date = date('Y-m-d', strtotime("$d days", $today));
        foreach ($templates as [$title, $s, $e, $cap, $coach]) {
            if (date('w', strtotime($date)) === '0' && $title === 'Night Session') continue;
            q('INSERT INTO workout_slots (title, slot_date, start_time, end_time, capacity, trainer, created_by) VALUES (?,?,?,?,?,?,1)', [$title, $date, $s, $e, $cap, $coach]);
            $slotId = (int)db()->lastInsertId();
            shuffle($activeIds);
            $n = mt_rand((int)($cap * 0.2), (int)($cap * 0.9));
            foreach (array_slice($activeIds, 0, min($n, count($activeIds))) as $mid) {
                $past = $date . ' ' . $e < now();
                $status = $past ? (mt_rand(1, 100) <= 78 ? 'attended' : (mt_rand(0, 1) ? 'no_show' : 'cancelled')) : (mt_rand(1, 100) <= 92 ? 'booked' : 'cancelled');
                q('INSERT IGNORE INTO reservations (slot_id, member_id, status, created_at) VALUES (?,?,?,?)', [$slotId, $mid, $status, date('Y-m-d H:i:s', strtotime("$date -2 days"))]);
            }
        }
    }
    // The demo member account always has an upcoming booking.
    $demoSlot = q("SELECT id FROM workout_slots WHERE slot_date = ? AND title = 'After-Work Session'", [date('Y-m-d', strtotime('+1 day', $today))])->fetchColumn();
    if ($demoSlot) q("INSERT INTO reservations (slot_id, member_id, status) VALUES (?, 1, 'booked') ON DUPLICATE KEY UPDATE status='booked'", [$demoSlot]);

    // Maintenance history
    $tickets = [
        [1, 'Belt slipping at high speed and making a grinding noise.', 'high', 'in_progress', 'Ordered replacement belt.', 1500, -3],
        [6, 'Left cable frayed near the pulley.', 'critical', 'reported', null, null, -1],
        [3, 'Display not turning on.', 'medium', 'resolved', 'Replaced console batteries and cleaned contacts.', 250, -12],
        [7, 'Seat adjustment pin is loose.', 'low', 'resolved', 'Tightened and lubricated pin.', 0, -20],
    ];
    foreach ($tickets as [$eid, $issue, $prio, $status, $actionTaken, $cost, $ago]) {
        $at = date('Y-m-d H:i:s', strtotime("$ago days 10:30", $today));
        q('INSERT INTO maintenance_logs (equipment_id, reported_by, issue, priority, status, action_taken, cost, reported_at, resolved_at) VALUES (?,2,?,?,?,?,?,?,?)',
            [$eid, $issue, $prio, $status, $actionTaken, $cost, $at, $status === 'resolved' ? date('Y-m-d H:i:s', strtotime($at . ' +1 day')) : null]);
        $eqStatus = ['reported' => $prio === 'critical' ? 'out_of_service' : 'needs_repair', 'in_progress' => 'under_maintenance', 'resolved' => 'operational'][$status];
        q('UPDATE equipment SET status = ?' . ($status === 'resolved' ? ', last_maintenance = ?' : ', last_maintenance = last_maintenance') . ' WHERE id = ?',
            $status === 'resolved' ? [$eqStatus, substr($at, 0, 10), $eid] : [$eqStatus, $eid]);
    }
}
