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
    return true;
}

function admin_logout(): void
{
    unset($_SESSION['admin_id']);
    session_regenerate_id(true);
}

function admin_count(): int
{
    return (int)q1('SELECT COUNT(*) AS c FROM admins')['c'];
}
