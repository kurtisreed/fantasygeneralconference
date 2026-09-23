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

/**
 * Random numeric entry code, easy to type or read aloud over a phone. Only
 * 10,000 possible 4-digit codes exist, unlike the old 6-character alphabet's
 * ~1.3 billion, so this checks the event for a free one instead of trusting
 * a fresh random draw not to collide.
 */
function make_entry_code(int $eventId, int $len = 4): string
{
    do {
        $code = '';
        for ($i = 0; $i < $len; $i++) {
            $code .= (string)random_int(0, 9);
        }
    } while (q1('SELECT id FROM players WHERE event_id = ? AND entry_code = ?', [$eventId, $code]));
    return $code;
}

function redirect(string $url): never
{
    // If a page has already started printing, header() is a silent no-op and
    // the browser just re-renders stale form state. Fall back to markup so the
    // navigation still happens.
    if (headers_sent()) {
        printf(
            '<meta http-equiv="refresh" content="0;url=%1$s">'
            . '<script>location.replace(%2$s);</script>'
            . '<p>Saved. <a href="%1$s">Continue</a>.</p>',
            htmlspecialchars($url, ENT_QUOTES, 'UTF-8'),
            json_encode($url)
        );
        exit;
    }
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

/**
 * Absolute URL to a path at the site root — for a link meant to work outside
 * the browser (a share sheet, a text message), where a relative link makes
 * no sense. $prefix is the same one page_head()/asset_url() take: '' from a
 * top-level page, '../' from anything under admin/.
 */
function site_url(string $path, string $prefix = ''): string
{
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $dir = rtrim(dirname((string)($_SERVER['SCRIPT_NAME'] ?? '')), '/');
    if ($prefix !== '') {
        $dir = rtrim(dirname($dir), '/');
    }
    return $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? '') . $dir . '/' . ltrim($path, '/');
}
