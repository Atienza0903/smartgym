<?php

function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        $pdo->exec("SET time_zone = '" . date('P') . "'");
    }
    return $pdo;
}

function q(string $sql, array $params = []): PDOStatement
{
    $st = db()->prepare($sql);
    $st->execute($params);
    return $st;
}

function e($value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function now(): string
{
    return date('Y-m-d H:i:s');
}

function today(): string
{
    return date('Y-m-d');
}

function setting(string $key, $default = null)
{
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        foreach (q('SELECT `key`, `value` FROM settings')->fetchAll() as $row) {
            $cache[$row['key']] = $row['value'];
        }
    }
    return $cache[$key] ?? $default;
}

function set_setting(string $key, string $value): void
{
    q('INSERT INTO settings (`key`, `value`) VALUES (?, ?) ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)', [$key, $value]);
}

function redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function take_flashes(): array
{
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

function verify_csrf(): void
{
    $token = $_POST['csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (!is_string($token) || !hash_equals(csrf_token(), $token)) {
        http_response_code(419);
        exit('Invalid or expired form token. Please go back and refresh the page.');
    }
}

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

function post(string $key, $default = '')
{
    $v = $_POST[$key] ?? $default;
    return is_string($v) ? trim($v) : $v;
}

function get(string $key, $default = '')
{
    $v = $_GET[$key] ?? $default;
    return is_string($v) ? trim($v) : $v;
}

function json_response(array $data, int $code = 200): void
{
    http_response_code($code);
    header('Content-Type: application/json');
    header('Cache-Control: no-store');
    echo json_encode($data);
    exit;
}

function fmt_date(?string $d, string $format = 'M d, Y'): string
{
    return $d ? date($format, strtotime($d)) : '—';
}

function fmt_datetime(?string $d): string
{
    return $d ? date('M d, Y h:i A', strtotime($d)) : '—';
}

function fmt_time(?string $t): string
{
    return $t ? date('h:i A', strtotime($t)) : '—';
}

function peso($amount): string
{
    return '₱' . number_format((float)$amount, 2);
}

function max_capacity(): int
{
    return max(1, (int)setting('max_capacity', 30));
}

function inside_count(): int
{
    return (int)q('SELECT COUNT(*) FROM members WHERE is_inside = 1')->fetchColumn();
}

function capacity_info(): array
{
    $inside = inside_count();
    $max = max_capacity();
    $pct = (int)round($inside / $max * 100);
    if ($pct >= 100) {
        $level = ['Full', 'danger'];
    } elseif ($pct >= 80) {
        $level = ['Busy', 'warning'];
    } elseif ($pct >= 40) {
        $level = ['Moderate', 'info'];
    } else {
        $level = ['Not Busy', 'success'];
    }
    return ['inside' => $inside, 'capacity' => $max, 'percent' => min(100, $pct), 'label' => $level[0], 'color' => $level[1]];
}

function membership_state(array $m): array
{
    if ($m['status'] !== 'active') {
        return [ucfirst($m['status']), 'secondary'];
    }
    if (empty($m['membership_end'])) {
        return ['No Plan', 'secondary'];
    }
    $days = (int)floor((strtotime($m['membership_end']) - strtotime(today())) / 86400);
    if ($days < 0) {
        return ['Expired', 'danger'];
    }
    if ($days <= 7) {
        return ["Expiring ({$days}d)", 'warning'];
    }
    return ['Active', 'success'];
}

function badge(string $text, string $color): string
{
    $textCls = in_array($color, ['warning', 'info', 'light'], true) ? ' text-dark' : '';
    return '<span class="badge bg-' . e($color) . $textCls . '">' . e($text) . '</span>';
}

function direction_badge(string $dir): string
{
    $map = ['IN' => 'success', 'OUT' => 'secondary', 'DENIED' => 'danger'];
    return badge($dir, $map[$dir] ?? 'dark');
}

function equipment_badge(string $s): string
{
    $map = [
        'operational' => ['Operational', 'success'],
        'needs_repair' => ['Needs Repair', 'warning'],
        'under_maintenance' => ['Under Maintenance', 'info'],
        'out_of_service' => ['Out of Service', 'danger'],
    ];
    [$t, $c] = $map[$s] ?? [$s, 'dark'];
    return badge($t, $c);
}

function ticket_badge(string $s): string
{
    $map = ['reported' => ['Reported', 'danger'], 'in_progress' => ['In Progress', 'warning'], 'resolved' => ['Resolved', 'success']];
    [$t, $c] = $map[$s] ?? [$s, 'dark'];
    return badge($t, $c);
}

function priority_badge(string $p): string
{
    $map = ['low' => 'secondary', 'medium' => 'info', 'high' => 'warning', 'critical' => 'danger'];
    return badge(ucfirst($p), $map[$p] ?? 'dark');
}

function reservation_badge(string $s): string
{
    $map = ['booked' => 'primary', 'attended' => 'success', 'cancelled' => 'secondary', 'no_show' => 'danger'];
    return badge(ucwords(str_replace('_', ' ', $s)), $map[$s] ?? 'dark');
}

function normalize_uid(string $uid): string
{
    return strtoupper(preg_replace('/[^0-9A-Fa-f]/', '', $uid));
}

function next_member_code(): string
{
    $max = (int)q("SELECT COALESCE(MAX(CAST(SUBSTRING(member_code, 5) AS UNSIGNED)), 0) FROM members WHERE member_code LIKE 'GFG-%'")->fetchColumn();
    return sprintf('GFG-%04d', $max + 1);
}

function paginate(int $total, int $perPage = 25): array
{
    $pages = max(1, (int)ceil($total / $perPage));
    $page = min($pages, max(1, (int)get('page', 1)));
    return ['page' => $page, 'pages' => $pages, 'offset' => ($page - 1) * $perPage, 'limit' => $perPage, 'total' => $total];
}

function pagination_links(array $p): string
{
    if ($p['pages'] <= 1) {
        return '';
    }
    $params = $_GET;
    $html = '<nav><ul class="pagination pagination-sm mb-0">';
    $start = max(1, $p['page'] - 3);
    $end = min($p['pages'], $p['page'] + 3);
    for ($i = $start; $i <= $end; $i++) {
        $params['page'] = $i;
        $active = $i === $p['page'] ? ' active' : '';
        $html .= '<li class="page-item' . $active . '"><a class="page-link" href="?' . e(http_build_query($params)) . '">' . $i . '</a></li>';
    }
    return $html . '</ul></nav>';
}
