<?php
declare(strict_types=1);

/**
 * Which group is this request for?
 *
 * The group comes from the hostname: <slug>.<base_domain>. The bare domain,
 * www, an IP, or anything that isn't under base_domain serves the default
 * group, so the original site keeps working untouched. *.localhost is also
 * understood so other groups can be tried locally.
 */
function current_org(): array
{
    static $org = null;
    if ($org !== null) {
        return $org;
    }

    global $CONFIG;
    $default = (string)($CONFIG['default_group'] ?? 'default');
    $host    = strtolower(preg_replace('/:\d+$/', '', (string)($_SERVER['HTTP_HOST'] ?? '')));
    $base    = strtolower((string)($CONFIG['base_domain'] ?? ''));

    $slug = $default;
    foreach (array_filter([$base, 'localhost']) as $suffix) {
        if (str_ends_with($host, '.' . $suffix)) {
            $label = substr($host, 0, -strlen('.' . $suffix));
            if ($label !== '' && $label !== 'www' && !str_contains($label, '.')) {
                $slug = $label;
            }
            break;
        }
    }

    $org = q1('SELECT * FROM orgs WHERE slug = ?', [$slug]);
    if (!$org) {
        if (PHP_SAPI !== 'cli') {
            http_response_code(404);
            header('Content-Type: text/plain; charset=utf-8');
        }
        exit($slug === $default
            ? "No default group yet. Run sql/migrate-multitenant.sql (or tools/install.php on a new install).\n"
            : "There is no group at this address.\n");
    }
    return $org;
}

function current_org_id(): int
{
    return (int)current_org()['id'];
}

/** The default group's row, created on a fresh install. */
function ensure_default_org(): int
{
    global $CONFIG;
    $slug = (string)($CONFIG['default_group'] ?? 'default');
    exec_sql(
        'INSERT INTO orgs (slug, name, leader_name, contact_line) VALUES (?,?,?,?)
         ON DUPLICATE KEY UPDATE slug = slug',
        [$slug, (string)($CONFIG['site_name'] ?? 'Fantasy General Conference'), '', '']
    );
    return (int)q1('SELECT id FROM orgs WHERE slug = ?', [$slug])['id'];
}

/** Where a group's players go: <slug>.<base_domain>, or the bare domain for the default. */
function org_url(array $org): string
{
    global $CONFIG;
    $scheme = !empty($CONFIG['secure_cookies']) ? 'https' : 'http';
    $base   = (string)($CONFIG['base_domain'] ?? 'localhost');
    $port   = preg_match('/:(\d+)$/', (string)($_SERVER['HTTP_HOST'] ?? ''), $m) ? ':' . $m[1] : '';
    $host   = $org['slug'] === ($CONFIG['default_group'] ?? 'default') ? $base : $org['slug'] . '.' . $base;
    return $scheme . '://' . $host . $port . '/';
}

/** Black or white text, whichever reads better on this accent color. */
function accent_ink(string $hex): string
{
    $r = hexdec(substr($hex, 1, 2));
    $g = hexdec(substr($hex, 3, 2));
    $b = hexdec(substr($hex, 5, 2));
    return (0.299 * $r + 0.587 * $g + 0.114 * $b) > 150 ? '#1a1a1a' : '#ffffff';
}

/** "Fantasy General Conference" is set as a small/big wordmark; other names sit whole. */
function wordmark_parts(string $name): array
{
    if (preg_match('/^Fantasy\s+(.+)$/i', $name, $m)) {
        return ['Fantasy', $m[1]];
    }
    return ['', $name];
}

/** This group's plain-text message for the main page and standings, if it has one. */
function org_announcement(): void
{
    $text = trim((string)(current_org()['announcement'] ?? ''));
    if ($text !== '') {
        // Escape everything first, then turn bare web addresses into links, so
        // nothing typed in the box can inject markup of its own.
        $html = preg_replace_callback(
            '~https?://[^\s<]+~i',
            static function (array $m): string {
                $url   = $m[0];
                $trail = '';
                while ($url !== '' && str_contains('.,;:!?)', substr($url, -1))) {
                    $trail = substr($url, -1) . $trail;
                    $url   = substr($url, 0, -1);
                }
                return '<a class="link" href="' . $url . '" target="_blank" rel="noopener noreferrer">' . $url . '</a>' . $trail;
            },
            e($text)
        );
        echo '<div class="announcement">' . nl2br($html) . '</div>' . "\n";
    }
}
