<?php
$user = current_user();
$page_title = $page_title ?? 'Dashboard';
$active = $active ?? basename($_SERVER['SCRIPT_NAME'], '.php');
$gymName = setting('gym_name', "Gleah's Fitness Gym");

$nav = [
    ['dashboard', 'Dashboard', 'speedometer2', ['admin', 'staff', 'member']],
    ['rfid_monitor', 'RFID Monitor', 'broadcast', ['admin', 'staff']],
    ['members', 'Members', 'people', ['admin', 'staff']],
    ['access_logs', 'Attendance Logs', 'clock-history', ['admin', 'staff']],
    ['my_attendance', 'My Attendance', 'clock-history', ['member']],
    ['reservations', 'Reservations', 'calendar-check', ['admin', 'staff', 'member']],
    ['slots', 'Workout Slots', 'calendar3-week', ['admin', 'staff']],
    ['equipment', 'Equipment', 'tools', ['admin', 'staff']],
    ['analytics', 'Analytics', 'bar-chart-line', ['admin']],
    ['plans', 'Membership Plans', 'card-list', ['admin']],
    ['users', 'User Accounts', 'person-gear', ['admin']],
    ['settings', 'Settings', 'gear', ['admin']],
];
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($page_title) ?> · <?= e(APP_NAME) ?></title>
    <link rel="icon" href="assets/img/helmet.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>
<div class="app-shell">
    <aside class="sidebar" id="sidebar">
        <a href="dashboard.php" class="brand">
            <img src="assets/img/helmet.png" alt="">
            <div>
                <div class="brand-title">SmartGym</div>
                <div class="brand-sub"><?= e($gymName) ?></div>
            </div>
        </a>
        <nav class="nav flex-column">
            <?php foreach ($nav as [$file, $label, $icon, $roles]): if (!in_array($user['role'], $roles, true)) continue; ?>
                <a class="nav-link<?= $active === $file ? ' active' : '' ?>" href="<?= $file ?>.php">
                    <i class="bi bi-<?= $icon ?>"></i><span><?= e($label) ?></span>
                </a>
            <?php endforeach; ?>
        </nav>
        <div class="sidebar-footer">
            <a class="nav-link<?= $active === 'profile' ? ' active' : '' ?>" href="profile.php"><i class="bi bi-person-circle"></i><span>My Profile</span></a>
            <a class="nav-link" href="logout.php"><i class="bi bi-box-arrow-right"></i><span>Logout</span></a>
        </div>
    </aside>
    <div class="sidebar-backdrop" onclick="document.body.classList.remove('sidebar-open')"></div>
    <main class="main">
        <header class="topbar">
            <button class="btn btn-sm btn-outline-light d-lg-none" onclick="document.body.classList.toggle('sidebar-open')"><i class="bi bi-list"></i></button>
            <h1 class="page-title"><?= e($page_title) ?></h1>
            <div class="ms-auto d-flex align-items-center gap-3">
                <div class="capacity-pill" data-capacity-pill title="Members currently inside">
                    <i class="bi bi-people-fill"></i> <span data-cap-inside>–</span>/<span data-cap-max>–</span>
                </div>
                <div class="text-end d-none d-sm-block lh-sm">
                    <div class="fw-semibold"><?= e($user['full_name']) ?></div>
                    <small class="text-secondary text-uppercase"><?= e($user['role']) ?></small>
                </div>
            </div>
        </header>
        <div class="content">
            <?php foreach (take_flashes() as $f): ?>
                <div class="alert alert-<?= e($f['type']) ?> alert-dismissible fade show" role="alert">
                    <?= e($f['message']) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endforeach; ?>
