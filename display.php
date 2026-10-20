<?php
require __DIR__ . '/includes/bootstrap.php';
$cap = capacity_info();
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Live Capacity · <?= e(setting('gym_name')) ?></title>
    <link rel="icon" href="assets/img/helmet.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>
<div class="display-page">
    <img src="assets/img/logo-banner.jpg" alt="" style="max-width:520px;width:100%;border-radius:1rem">
    <h2 class="fw-bold text-white mb-0">LIVE GYM CAPACITY</h2>
    <div class="gauge" data-gauge>
        <svg viewBox="0 0 200 200" width="100%" height="100%">
            <circle class="gauge-bg" cx="100" cy="100" r="85" fill="none" stroke-width="16"/>
            <circle class="gauge-fg" cx="100" cy="100" r="85" fill="none" stroke-width="16"/>
        </svg>
        <div class="gauge-text">
            <div class="num" data-cap-inside><?= $cap['inside'] ?></div>
            <div class="of">of <span data-cap-max><?= $cap['capacity'] ?></span> members</div>
        </div>
    </div>
    <div class="fs-3"><span data-cap-label class="badge bg-<?= e($cap['color']) ?>"><?= e($cap['label']) ?></span></div>
    <div class="text-secondary">Hours: <?= e(fmt_time(setting('open_time'))) ?> – <?= e(fmt_time(setting('close_time'))) ?> · Updates automatically</div>
    <a href="login.php" class="small">Staff / Member login</a>
</div>
<script src="assets/js/app.js"></script>
</body>
</html>
