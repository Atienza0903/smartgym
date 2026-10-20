<?php
/**
 * Core RFID tap logic shared by the ESP32 API endpoint, the staff simulator, and manual check-in/out.
 * A tap toggles the member between IN and OUT. Entry is denied for unknown cards,
 * inactive/expired memberships, or when the gym is at maximum capacity.
 */

function log_access(?int $memberId, string $uid, string $direction, ?string $reason, ?string $device, string $source): void
{
    q('INSERT INTO access_logs (member_id, rfid_uid, direction, reason, device_id, source, scanned_at) VALUES (?, ?, ?, ?, ?, ?, ?)',
        [$memberId, $uid, $direction, $reason, $device, $source, now()]);
}

function process_scan(string $rawUid, string $device = 'ESP32-01', string $source = 'rfid'): array
{
    $uid = normalize_uid($rawUid);
    if ($uid === '' || strlen($uid) > 32) {
        return ['ok' => false, 'action' => 'DENIED', 'message' => 'Invalid card UID'];
    }

    $pdo = db();
    $pdo->beginTransaction();
    try {
        $m = q('SELECT * FROM members WHERE rfid_uid = ? FOR UPDATE', [$uid])->fetch();
        if (!$m) {
            log_access(null, $uid, 'DENIED', 'Unregistered card', $device, $source);
            $pdo->commit();
            return scan_result('DENIED', 'Unregistered card', null, $uid);
        }

        $cooldown = (int)setting('scan_cooldown_seconds', 5);
        if ($source === 'rfid' && $m['last_scan_at'] && (time() - strtotime($m['last_scan_at'])) < $cooldown) {
            $pdo->commit();
            return scan_result('IGNORED', 'Duplicate tap ignored', $m, $uid);
        }

        $result = $m['is_inside'] ? check_out($m, $uid, $device, $source) : check_in($m, $uid, $device, $source);
        $pdo->commit();
        return $result;
    } catch (Throwable $ex) {
        $pdo->rollBack();
        throw $ex;
    }
}

function check_in(array $m, string $uid, ?string $device, string $source): array
{
    $deny = null;
    if ($m['status'] !== 'active') {
        $deny = 'Membership ' . $m['status'];
    } elseif (empty($m['membership_end']) || $m['membership_end'] < today()) {
        $deny = 'Membership expired';
    } else {
        $inside = (int)q('SELECT COUNT(*) FROM members WHERE is_inside = 1 FOR UPDATE')->fetchColumn();
        if ($inside >= max_capacity()) {
            $deny = 'Gym is at full capacity';
        }
    }

    if ($deny) {
        q('UPDATE members SET last_scan_at = ? WHERE id = ?', [now(), $m['id']]);
        log_access((int)$m['id'], $uid, 'DENIED', $deny, $device, $source);
        return scan_result('DENIED', $deny, $m, $uid);
    }

    q('UPDATE members SET is_inside = 1, last_scan_at = ? WHERE id = ?', [now(), $m['id']]);
    log_access((int)$m['id'], $uid, 'IN', null, $device, $source);

    // Mark a booked reservation as attended if the member arrives within the slot window (30 min early allowed).
    q("UPDATE reservations r JOIN workout_slots s ON s.id = r.slot_id
       SET r.status = 'attended', r.updated_at = ?
       WHERE r.member_id = ? AND r.status = 'booked' AND s.slot_date = ?
         AND ? BETWEEN SUBTIME(s.start_time, '00:30:00') AND s.end_time",
        [now(), $m['id'], today(), date('H:i:s')]);

    return scan_result('IN', 'Welcome, ' . first_name($m['full_name']) . '!', $m, $uid);
}

function check_out(array $m, string $uid, ?string $device, string $source): array
{
    q('UPDATE members SET is_inside = 0, last_scan_at = ? WHERE id = ?', [now(), $m['id']]);
    log_access((int)$m['id'], $uid, 'OUT', null, $device, $source);
    return scan_result('OUT', 'Goodbye, ' . first_name($m['full_name']) . '!', $m, $uid);
}

function first_name(string $full): string
{
    return explode(' ', trim($full))[0];
}

function scan_result(string $action, string $message, ?array $m, string $uid): array
{
    $cap = capacity_info();
    return [
        'ok' => in_array($action, ['IN', 'OUT'], true),
        'action' => $action,
        'message' => $message,
        'uid' => $uid,
        'member' => $m ? ['id' => (int)$m['id'], 'name' => $m['full_name'], 'code' => $m['member_code']] : null,
        'inside' => $cap['inside'],
        'capacity' => $cap['capacity'],
        'time' => date('h:i:s A'),
    ];
}

/** Force check-out for one member (manual) or everyone (end of day). */
function manual_checkout(int $memberId, string $reason = 'Manual check-out'): void
{
    $m = q('SELECT * FROM members WHERE id = ? AND is_inside = 1', [$memberId])->fetch();
    if ($m) {
        q('UPDATE members SET is_inside = 0, last_scan_at = ? WHERE id = ?', [now(), $memberId]);
        log_access($memberId, $m['rfid_uid'] ?? 'MANUAL', 'OUT', $reason, 'WEB', 'manual');
    }
}

/** Staff-initiated check-in for members without a card on hand. Same rules as an RFID tap. */
function manual_checkin(int $memberId): array
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $m = q('SELECT * FROM members WHERE id = ? FOR UPDATE', [$memberId])->fetch();
        if (!$m) {
            $pdo->rollBack();
            return ['ok' => false, 'action' => 'DENIED', 'message' => 'Member not found'];
        }
        $r = $m['is_inside'] ? scan_result('IN', 'Already inside', $m, (string)$m['rfid_uid'])
            : check_in($m, $m['rfid_uid'] ?: 'MANUAL', 'WEB', 'manual');
        $pdo->commit();
        return $r;
    } catch (Throwable $ex) {
        $pdo->rollBack();
        throw $ex;
    }
}
