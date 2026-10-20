<?php
require_once __DIR__ . '/../config/config.php';

date_default_timezone_set(APP_TIMEZONE);

if (PHP_SAPI !== 'cli' && session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    ]);
    session_name('SMARTGYMSESSID');
    session_start();
}

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/auth.php';
