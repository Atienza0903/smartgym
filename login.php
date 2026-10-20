<?php
require __DIR__ . '/includes/bootstrap.php';

if (current_user()) {
    redirect('dashboard.php');
}

$error = '';
$email = '';
if (is_post()) {
    verify_csrf();
    $email = strtolower(post('email'));
    $_SESSION['login_attempts'] = $_SESSION['login_attempts'] ?? [];
    $_SESSION['login_attempts'] = array_filter($_SESSION['login_attempts'], fn($t) => $t > time() - 300);
    if (count($_SESSION['login_attempts']) >= 5) {
        $error = 'Too many failed attempts. Please wait a few minutes and try again.';
    } elseif (attempt_login($email, (string)($_POST['password'] ?? ''))) {
        unset($_SESSION['login_attempts']);
        redirect('dashboard.php');
    } else {
        $_SESSION['login_attempts'][] = time();
        $error = 'Invalid email or password.';
    }
}
$flashes = take_flashes();
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login · <?= e(APP_NAME) ?></title>
    <link rel="icon" href="assets/img/helmet.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>
<div class="login-page">
    <div class="card login-card shadow-lg">
        <img src="assets/img/logo-banner.jpg" alt="<?= e(setting('gym_name')) ?>" class="login-banner">
        <div class="card-body p-4">
            <h4 class="fw-bold mb-1 text-white">SmartGym Operations</h4>
            <p class="text-secondary mb-4">Sign in to your account</p>
            <?php foreach ($flashes as $f): ?>
                <div class="alert alert-<?= e($f['type']) ?> py-2"><?= e($f['message']) ?></div>
            <?php endforeach; ?>
            <?php if ($error): ?>
                <div class="alert alert-danger py-2"><i class="bi bi-exclamation-triangle me-1"></i><?= e($error) ?></div>
            <?php endif; ?>
            <form method="post" autocomplete="on">
                <?= csrf_field() ?>
                <div class="mb-3">
                    <label class="form-label">Email</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                        <input type="email" name="email" class="form-control" value="<?= e($email) ?>" required autofocus>
                    </div>
                </div>
                <div class="mb-4">
                    <label class="form-label">Password</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-lock"></i></span>
                        <input type="password" name="password" class="form-control" required>
                    </div>
                </div>
                <button class="btn btn-primary w-100 py-2 fw-semibold"><i class="bi bi-box-arrow-in-right me-1"></i> Login</button>
            </form>
            <div class="text-center mt-4">
                <a href="display.php" class="small"><i class="bi bi-display me-1"></i>Live gym capacity display</a>
            </div>
        </div>
    </div>
</div>
</body>
</html>
