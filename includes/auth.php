<?php

function current_user(): ?array
{
    static $user = false;
    if ($user === false) {
        $user = null;
        if (!empty($_SESSION['user_id'])) {
            $user = q("SELECT * FROM users WHERE id = ? AND status = 'active'", [$_SESSION['user_id']])->fetch() ?: null;
            if (!$user) {
                unset($_SESSION['user_id']);
            }
        }
    }
    return $user;
}

function user_role(): ?string
{
    return current_user()['role'] ?? null;
}

function has_role(string ...$roles): bool
{
    return in_array(user_role(), $roles, true);
}

function require_login(): array
{
    $user = current_user();
    if (!$user) {
        flash('warning', 'Please log in to continue.');
        redirect('login.php');
    }
    return $user;
}

function require_role(string ...$roles): array
{
    $user = require_login();
    if (!in_array($user['role'], $roles, true)) {
        http_response_code(403);
        $page_title = 'Access Denied';
        require __DIR__ . '/header.php';
        echo '<div class="alert alert-danger"><i class="bi bi-shield-lock me-2"></i>You do not have permission to access this page.</div>';
        require __DIR__ . '/footer.php';
        exit;
    }
    return $user;
}

function attempt_login(string $email, string $password): bool
{
    $user = q('SELECT * FROM users WHERE email = ?', [$email])->fetch();
    if (!$user || $user['status'] !== 'active' || !password_verify($password, $user['password_hash'])) {
        return false;
    }
    if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
        q('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($password, PASSWORD_DEFAULT), $user['id']]);
    }
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int)$user['id'];
    q('UPDATE users SET last_login_at = ? WHERE id = ?', [now(), $user['id']]);
    return true;
}

function logout(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

/** The member profile linked to the logged-in user (members only). */
function current_member(): ?array
{
    $user = current_user();
    if (!$user) {
        return null;
    }
    return q('SELECT m.*, p.name AS plan_name FROM members m LEFT JOIN membership_plans p ON p.id = m.plan_id WHERE m.user_id = ?', [$user['id']])->fetch() ?: null;
}
