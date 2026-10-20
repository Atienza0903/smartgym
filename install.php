<?php
/**
 * One-time installer: creates the database, tables, default accounts and (optionally) demo data.
 * Browser: open /install.php      CLI: php install.php [--demo] [--force]
 * Refuses to run if the system is already installed (unless --force from CLI).
 */
require_once __DIR__ . '/config/config.php';
date_default_timezone_set(APP_TIMEZONE);
$cli = PHP_SAPI === 'cli';
if (!$cli) {
    session_name('SMARTGYMSESSID');
    session_start();
}

function installed(PDO $server): bool
{
    try {
        $server->exec('USE `' . DB_NAME . '`');
        return (int)$server->query('SELECT COUNT(*) FROM users')->fetchColumn() > 0;
    } catch (PDOException $e) {
        return false;
    }
}

function run_install(bool $demo): string
{
    $server = new PDO('mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';charset=utf8mb4', DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $server->exec('CREATE DATABASE IF NOT EXISTS `' . DB_NAME . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    $server->exec('USE `' . DB_NAME . '`');
    $sql = file_get_contents(__DIR__ . '/database/smartgym.sql');
    $sql = preg_replace('/^\s*(CREATE DATABASE|USE)\b.*$/mi', '', $sql);
    $sql = preg_replace('/^\s*--.*$/m', '', $sql);
    foreach (array_filter(array_map('trim', preg_split('/;\s*\n/', $sql))) as $stmt) {
        $server->exec($stmt);
    }
    require_once __DIR__ . '/includes/helpers.php';
    set_setting('device_api_key', bin2hex(random_bytes(16)));
    if ($demo) {
        require_once __DIR__ . '/includes/seed.php';
        seed_demo();
    }
    return 'Installation complete.' . ($demo ? ' Demo data generated.' : '');
}

$force = $cli && in_array('--force', $argv, true);
try {
    $server = new PDO('mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';charset=utf8mb4', DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $already = installed($server);
    $connError = null;
} catch (PDOException $e) {
    $already = false;
    $connError = $e->getMessage();
}

if ($cli) {
    if ($connError) { fwrite(STDERR, "DB connection failed: $connError\n"); exit(1); }
    if ($already && !$force) { fwrite(STDERR, "Already installed. Use --force to wipe and reinstall.\n"); exit(1); }
    echo run_install(in_array('--demo', $argv, true)), "\n";
    exit(0);
}

$message = $error = null;
if (!$connError && !$already && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!hash_equals($_SESSION['install_csrf'] ?? '', $_POST['csrf'] ?? '')) {
        $error = 'Invalid form token, please retry.';
    } else {
        try {
            $message = run_install(!empty($_POST['demo']));
            $already = true;
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }
    }
}
$_SESSION['install_csrf'] = $_SESSION['install_csrf'] ?? bin2hex(random_bytes(16));
?>
<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Install · SmartGym</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"><link href="assets/css/style.css" rel="stylesheet"></head>
<body><div class="login-page"><div class="card login-card"><img src="assets/img/logo-banner.jpg" class="login-banner" alt=""><div class="card-body p-4">
<h4 class="fw-bold text-white">SmartGym Installer</h4>
<?php if ($connError): ?>
    <div class="alert alert-danger small">Cannot connect to MySQL: <?= htmlspecialchars($connError) ?><br>Check <code>config/config.php</code> and make sure MySQL is running.</div>
<?php elseif ($message): ?>
    <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
    <p class="small">Default logins (change after first login):</p>
    <ul class="small"><li>Admin: admin@smartgym.local / admin123</li><li>Staff: staff@smartgym.local / staff123</li><li>Member: member@smartgym.local / member123</li></ul>
    <p class="small text-warning">For security, delete <code>install.php</code> from the server now.</p>
    <a href="login.php" class="btn btn-primary w-100">Go to Login</a>
<?php elseif ($already): ?>
    <div class="alert alert-info">SmartGym is already installed.</div><a href="login.php" class="btn btn-primary w-100">Go to Login</a>
<?php else: ?>
    <?php if ($error): ?><div class="alert alert-danger small"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <p class="text-secondary small">This creates the <code><?= htmlspecialchars(DB_NAME) ?></code> database, all tables, and default accounts.</p>
    <form method="post"><input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['install_csrf']) ?>">
        <div class="form-check mb-3"><input class="form-check-input" type="checkbox" name="demo" value="1" id="demo" checked><label class="form-check-label" for="demo">Include demo data (45 members, 60 days of RFID logs, slots, reservations)</label></div>
        <button class="btn btn-primary w-100">Install SmartGym</button>
    </form>
<?php endif; ?>
</div></div></div></body></html>
