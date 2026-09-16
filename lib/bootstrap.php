<?php
declare(strict_types=1);

// lib/util.php uses a PHP 8.1 return type, so on an older PHP the app dies with
// a parse error and a blank page. Say what's wrong instead. This file itself
// stays old-PHP-parseable so the message can actually be reached.
if (version_compare(PHP_VERSION, '8.1.0', '<')) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    exit(
        "Fantasy General Conference needs PHP 8.1 or newer.\n"
        . 'This server is running PHP ' . PHP_VERSION . ".\n\n"
        . "On cPanel hosting: open MultiPHP Manager, tick this domain, and set\n"
        . "the PHP version to 8.1 or later.\n"
    );
}

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

// A plain session cookie disappears the moment the browser fully closes, and
// PHP's own default server-side lifetime (24 minutes of inactivity) is far
// shorter than the gap between conference sessions — so the "Welcome back"
// shortcut on index.php would keep losing people between Saturday morning
// and Saturday afternoon. Stretch both to cover the whole conference
// weekend, with room to spare on either side.
define('SESSION_LIFETIME_SECONDS', 7 * 86400);

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.gc_maxlifetime', (string)SESSION_LIFETIME_SECONDS);
    session_set_cookie_params([
        'lifetime' => SESSION_LIFETIME_SECONDS,
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => (bool)($CONFIG['secure_cookies'] ?? false),
    ]);
    session_start();
}

// Standings change between sessions, so nothing here may be cached. PHP's
// session cache limiter already sends these; say it explicitly so the pages
// behave the same however they happen to be served.
if (PHP_SAPI !== 'cli' && !headers_sent()) {
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
}

require_once APP_ROOT . '/lib/util.php';
require_once APP_ROOT . '/lib/db.php';
require_once APP_ROOT . '/lib/csrf.php';
require_once APP_ROOT . '/lib/layout.php';
