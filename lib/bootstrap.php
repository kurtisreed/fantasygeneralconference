<?php
declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__));

$configPath = APP_ROOT . '/config.php';
if (!is_file($configPath)) {
    http_response_code(500);
    exit('Missing config.php — copy config.example.php to config.php and fill it in.');
}
$CONFIG = require $configPath;

if (!empty($CONFIG['debug'])) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
}

date_default_timezone_set('America/Denver'); // conference runs on Mountain Time

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => (bool)($CONFIG['secure_cookies'] ?? false),
    ]);
    session_start();
}

require_once APP_ROOT . '/lib/util.php';
require_once APP_ROOT . '/lib/db.php';
require_once APP_ROOT . '/lib/csrf.php';
require_once APP_ROOT . '/lib/layout.php';
