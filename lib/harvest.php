<?php
declare(strict_types=1);

/**
 * Fetching and counting the conference talks.
 *
 * Shared by tools/count_words.php (command line) and admin/harvest.php (over
 * the web, for hosting without a shell). Every response is cached on disk, so
 * the web version can do a handful of talks per request and pick up where it
 * left off — shared hosting will not keep a script alive for thirty-five
 * fetches.
 */

require_once __DIR__ . '/questions.php';

const HARVEST_API  = 'https://www.churchofjesuschrist.org/study/api/v3/language-pages/type/content?lang=eng&uri=';
const HARVEST_UA   = 'FantasyGeneralConference/1.0 (personal word-count for a youth guessing game)';
const HARVEST_WAIT = 400000;   // microseconds between requests — be a good guest

function harvest_cache_dir(string $conf): string
{
    $dir = APP_ROOT . '/tools/cache/' . str_replace('/', '-', $conf);
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    return $dir;
}

function harvest_cache_file(string $conf, string $uri): string
{
    return harvest_cache_dir($conf) . '/' . preg_replace('#[^a-z0-9]+#i', '_', $uri) . '.json';
}

function harvest_is_cached(string $conf, string $uri): bool
{
    return is_file(harvest_cache_file($conf, $uri));
}

/** Throw away everything fetched for a conference, so the next run refetches. */
function harvest_clear_cache(string $conf): int
{
    $n = 0;
    foreach (glob(harvest_cache_dir($conf) . '/*.json') ?: [] as $f) {
        if (unlink($f)) {
            $n++;
        }
    }
    return $n;
}

/** Fetch one study-API document, reading from (and writing to) the disk cache. */
function harvest_fetch(string $conf, string $uri, bool $fresh = false): ?array
{
    $file = harvest_cache_file($conf, $uri);
    if (!$fresh && is_file($file)) {
        return json_decode((string)file_get_contents($file), true);
    }

    $ch = curl_init(HARVEST_API . str_replace('%2F', '/', rawurlencode($uri)));
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_USERAGENT      => HARVEST_UA,
        CURLOPT_ENCODING       => '',
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_FOLLOWLOCATION => true,
    ]);
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    usleep(HARVEST_WAIT);

    if ($body === false || $code !== 200) {
        return null;
    }
    file_put_contents($file, $body);
    return json_decode((string)$body, true);
}

/** The talk slugs for a conference, from its table of contents. */
function harvest_slugs(string $conf, bool $fresh = false): array
{
    $toc = harvest_fetch($conf, "/general-conference/$conf", $fresh);
    if (!$toc) {
        return [];
    }
    preg_match_all(
        '#/study/general-conference/' . preg_quote($conf, '#') . '/([0-9a-z\-]+)#i',
        json_encode($toc, JSON_UNESCAPED_SLASHES),
        $m
    );
    $slugs = array_values(array_unique($m[1]));
    $slugs = array_values(array_filter($slugs, static fn($s) => !str_ends_with($s, '-session')));
    sort($slugs);
    return $slugs;
}

/**
 * Spoken text only: the talk body, minus footnotes, footnote markers, the
 * editorial summary line, the title and the byline. Those are printed but
 * never said, and footnotes especially are thick with the very phrases being
 * counted.
 */
function harvest_spoken_text(string $html): string
{
    $doc = new DOMDocument();
    libxml_use_internal_errors(true);
    $doc->loadHTML(
        '<!DOCTYPE html><html><head><meta http-equiv="Content-Type" content="text/html; charset=utf-8">'
        . '</head><body>' . $html . '</body></html>',
        LIBXML_NOERROR | LIBXML_NOWARNING
    );
    libxml_clear_errors();

    $xp = new DOMXPath($doc);
    $drop = $xp->query(
        '//footer[contains(@class,"notes")]'
        . ' | //sup[contains(@class,"marker")]'
        . ' | //p[contains(@class,"kicker")]'
        . ' | //div[contains(@class,"byline")]'
        . ' | //p[contains(@class,"author-name")]'
        . ' | //p[contains(@class,"author-role")]'
        . ' | //p[contains(@class,"title")]'
        . ' | //h1 | //header'
    );
    foreach (iterator_to_array($drop) as $n) {
        $n->parentNode?->removeChild($n);
    }

    $blocks = $xp->query('//div[contains(@class,"body-block")]');
    $text = '';
    if ($blocks !== false && $blocks->length > 0) {
        foreach ($blocks as $b) {
            $text .= ' ' . $b->textContent;
        }
    } else {
        $text = $doc->textContent;   // fall back to the whole payload
    }

    $text = str_replace(["\u{00A0}", "\u{2019}", "\u{2018}"], [' ', "'", "'"], $text);
    return trim((string)preg_replace('/\s+/u', ' ', $text));
}

/**
 * What this event wants counted, taken from the over/under questions.
 *
 * The regex lives on the question, so the rule printed on the sheet and the
 * rule applied here cannot drift apart.
 */
function harvest_patterns(int $eventId): array
{
    $out = [];
    foreach (get_questions($eventId, true) as $qq) {
        if ($qq['type'] === 'over_under' && !empty($qq['config']['pattern'])) {
            $out[$qq['qkey']] = [
                'id'      => (int)$qq['id'],
                'label'   => $qq['prompt'],
                'pattern' => $qq['config']['pattern'],
                'line'    => $qq['config']['line'] ?? null,
            ];
        }
    }
    return $out;
}

/**
 * Fetch up to $limit talks that aren't cached yet.
 * Returns ['fetched' => n, 'failed' => [slugs], 'remaining' => n].
 */
function harvest_step(string $conf, array $slugs, int $limit): array
{
    $fetched = 0;
    $failed  = [];
    $remaining = 0;

    foreach ($slugs as $slug) {
        $uri = "/general-conference/$conf/$slug";
        if (harvest_is_cached($conf, $uri)) {
            continue;
        }
        if ($fetched >= $limit) {
            $remaining++;
            continue;
        }
        if (harvest_fetch($conf, $uri) === null) {
            $failed[] = $slug;
        }
        $fetched++;
    }

    return ['fetched' => $fetched, 'failed' => $failed, 'remaining' => $remaining];
}

/** How many of a conference's talks are already on disk. */
function harvest_progress(string $conf, array $slugs): array
{
    $have = 0;
    foreach ($slugs as $slug) {
        if (harvest_is_cached($conf, "/general-conference/$conf/$slug")) {
            $have++;
        }
    }
    return ['have' => $have, 'total' => count($slugs)];
}

/**
 * Count every pattern across the cached talks.
 * Returns ['totals' => [qkey => n], 'words' => n, 'talks' => n].
 */
function harvest_count(string $conf, array $slugs, array $patterns): array
{
    $totals = array_fill_keys(array_keys($patterns), 0);
    $words  = 0;
    $talks  = 0;

    foreach ($slugs as $slug) {
        $data = harvest_fetch($conf, "/general-conference/$conf/$slug");
        if (!$data || empty($data['content']['body'])) {
            continue;
        }
        $text   = harvest_spoken_text($data['content']['body']);
        $words += str_word_count($text);
        $talks++;
        foreach ($patterns as $key => $p) {
            $totals[$key] += preg_match_all($p['pattern'], $text);
        }
    }

    return ['totals' => $totals, 'words' => $words, 'talks' => $talks];
}

/** Store the counts as this event's over/under results. */
function harvest_write_results(array $patterns, array $totals): int
{
    $pdo = db();
    $owns = !$pdo->inTransaction();
    if ($owns) {
        $pdo->beginTransaction();
    }
    $st = $pdo->prepare(
        'INSERT INTO results (question_id, value, numeric_value) VALUES (?, NULL, ?)
         ON DUPLICATE KEY UPDATE numeric_value = VALUES(numeric_value), value = NULL'
    );
    $n = 0;
    foreach ($patterns as $key => $p) {
        if (array_key_exists($key, $totals)) {
            $st->execute([$p['id'], (float)$totals[$key]]);
            $n++;
        }
    }
    if ($owns) {
        $pdo->commit();
    }
    return $n;
}

/** Guess the conference code (YYYY/MM) an event refers to. */
function harvest_conf_for_event(array $event): string
{
    $when = $event['starts_at'] ?? $event['lock_at'] ?? null;
    $ts   = $when ? strtotime((string)$when) : time();
    $month = (int)date('n', (int)$ts) >= 7 ? '10' : '04';
    return date('Y', (int)$ts) . '/' . $month;
}
