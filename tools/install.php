<?php
/**
 * One-time installer: creates the tables, seeds the current sheet and
 * creates the first admin account.
 *
 * Local:  php tools/install.php
 * Hosted: https://yoursite/tools/install.php?token=YOUR_SETUP_TOKEN
 *
 * Delete or re-protect this file once you are set up.
 */
declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';
require_once APP_ROOT . '/lib/seed.php';
require_once APP_ROOT . '/lib/auth.php';

$cli = PHP_SAPI === 'cli';
if (!$cli) {
    $token = $_GET['token'] ?? '';
    if (!hash_equals((string)($CONFIG['setup_token'] ?? ''), (string)$token)) {
        http_response_code(403);
        exit('Bad setup token.');
    }
    header('Content-Type: text/plain; charset=utf-8');
}

function say(string $msg): void
{
    echo $msg, "\n";
}

// ---- 1. schema ----
$sql = file_get_contents(APP_ROOT . '/sql/schema.sql');
// Strip comment lines first — otherwise a statement introduced by a comment
// looks like a comment itself once the file is split on ";".
$sql = preg_replace('/^\s*--.*$/m', '', $sql);
foreach (array_filter(array_map('trim', explode(';', $sql))) as $stmt) {
    db()->exec($stmt);
}
say('Schema applied.');

// ---- 2. seed ----
$eventId = fgc_seed();
$total = (int)q1('SELECT COALESCE(SUM(points),0) AS t FROM questions WHERE event_id=? AND active=1', [$eventId])['t'];
$count = (int)q1('SELECT COUNT(*) AS c FROM questions WHERE event_id=? AND active=1', [$eventId])['c'];
say("Seeded event #$eventId — $count questions, $total points possible.");

// ---- 3. first admin ----
if (admin_count() === 0) {
    if ($cli) {
        $user = trim((string)(getenv('FGC_ADMIN_USER') ?: 'admin'));
        $pass = (string)(getenv('FGC_ADMIN_PASS') ?: '');
        if ($pass === '') {
            $pass = bin2hex(random_bytes(6));
            say("Generated admin password: $pass");
        }
    } else {
        $user = trim((string)($_GET['admin_user'] ?? ''));
        $pass = (string)($_GET['admin_pass'] ?? '');
        if ($user === '' || strlen($pass) < 8) {
            exit("No admin yet. Re-run with &admin_user=you&admin_pass=at-least-8-chars\n");
        }
    }
    exec_sql(
        'INSERT INTO admins (username, password_hash) VALUES (?,?)',
        [$user, password_hash($pass, PASSWORD_DEFAULT)]
    );
    say("Admin account created: $user");
} else {
    say('Admin account already exists — left alone.');
}

say('Done. Open index.php to play, admin/ to run the game.');
