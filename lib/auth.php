<?php
declare(strict_types=1);

function admin_user(): ?array
{
    $id = $_SESSION['admin_id'] ?? null;
    if (!$id) {
        return null;
    }
    return q1('SELECT id, username FROM admins WHERE id = ?', [(int)$id]);
}

function require_admin(): array
{
    resume_remembered_admin();
    $u = admin_user();
    if (!$u) {
        redirect('login.php');
    }
    return $u;
}

function admin_login(string $username, string $password): bool
{
    $row = q1('SELECT * FROM admins WHERE username = ?', [$username]);
    if (!$row || !password_verify($password, $row['password_hash'])) {
        return false;
    }
    session_regenerate_id(true);
    $_SESSION['admin_id'] = (int)$row['id'];
    remember_admin((int)$row['id']);
    return true;
}

function admin_logout(): void
{
    if (!empty($_SESSION['admin_id'])) {
        q('UPDATE admins SET remember_token_hash = NULL WHERE id = ?', [(int)$_SESSION['admin_id']]);
    }
    unset($_SESSION['admin_id']);
    session_regenerate_id(true);
    forget_admin_cookie();
}

/**
 * Same shared-hosting problem as players (see remember_player_cookie in
 * lib/questions.php): the server can drop the session file long before our
 * 14-day gc_maxlifetime, and an admin has no ?code= link to fall back on.
 * Unlike a player's entry code, a password can't sit in a cookie, so instead
 * we store a random token's hash in the DB and rotate it on every use.
 */
function remember_admin(int $adminId): void
{
    global $CONFIG;
    $token = bin2hex(random_bytes(32));
    q('UPDATE admins SET remember_token_hash = ? WHERE id = ?', [hash('sha256', $token), $adminId]);
    setcookie('fgc_admin_remember', $adminId . ':' . $token, [
        'expires'  => time() + SESSION_LIFETIME_SECONDS,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => (bool)($CONFIG['secure_cookies'] ?? false),
    ]);
}

function forget_admin_cookie(): void
{
    setcookie('fgc_admin_remember', '', ['expires' => time() - 3600, 'path' => '/']);
}

/** If the PHP session died early but the remember cookie survived, rebuild it. */
function resume_remembered_admin(): void
{
    if (!empty($_SESSION['admin_id'])) {
        return;
    }
    $raw = (string)($_COOKIE['fgc_admin_remember'] ?? '');
    if (!str_contains($raw, ':')) {
        return;
    }
    [$id, $token] = explode(':', $raw, 2);
    $row = q1('SELECT id, remember_token_hash FROM admins WHERE id = ?', [(int)$id]);
    if (!$row || !$row['remember_token_hash'] || !hash_equals($row['remember_token_hash'], hash('sha256', $token))) {
        return;
    }
    session_regenerate_id(true);
    $_SESSION['admin_id'] = (int)$row['id'];
    remember_admin((int)$row['id']); // rotate: sliding window, and a stolen cookie stops working once reused
}

function admin_count(): int
{
    return (int)q1('SELECT COUNT(*) AS c FROM admins')['c'];
}
