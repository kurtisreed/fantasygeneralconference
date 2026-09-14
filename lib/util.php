<?php
declare(strict_types=1);

/** HTML-escape for output. */
function e(?string $s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Normalize free-text answers so "Navy ", "navy" and "NAVY" collapse together. */
function normalize_text(string $s): string
{
    $s = trim(mb_strtolower($s, 'UTF-8'));
    $s = str_replace(['’', '‘', '“', '”'], ["'", "'", '"', '"'], $s);
    $s = preg_replace('/[^\p{L}\p{N} ]+/u', ' ', $s) ?? $s;
    $s = preg_replace('/\s+/u', ' ', $s) ?? $s;
    return trim($s);
}

/** Random human-friendly entry code (no ambiguous characters). */
function make_entry_code(int $len = 6): string
{
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $out = '';
    for ($i = 0; $i < $len; $i++) {
        $out .= $alphabet[random_int(0, strlen($alphabet) - 1)];
    }
    return $out;
}

function redirect(string $url): never
{
    header('Location: ' . $url);
    exit;
}

function flash(?string $msg = null): ?string
{
    if ($msg !== null) {
        $_SESSION['flash'] = $msg;
        return null;
    }
    $m = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $m;
}

function post(string $key, ?string $default = null): ?string
{
    $v = $_POST[$key] ?? $default;
    return is_string($v) ? trim($v) : $default;
}

function query_int(string $key, int $default = 0): int
{
    return isset($_GET[$key]) ? (int)$_GET[$key] : $default;
}
