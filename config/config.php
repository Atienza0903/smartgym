<?php
/*
 * SmartGym Operations - configuration
 * Edit the database credentials below to match your server (XAMPP default: root / empty password).
 * Environment variables override these values when present.
 */
define('DB_HOST', getenv('DB_HOST') ?: '127.0.0.1');
define('DB_PORT', getenv('DB_PORT') ?: '3306');
define('DB_NAME', getenv('DB_NAME') ?: 'smartgym');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') !== false ? getenv('DB_PASS') : '');

define('APP_NAME', 'SmartGym Operations');
define('APP_TIMEZONE', 'Asia/Manila');
