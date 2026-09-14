<?php
/**
 * Harvest the talks for a conference from churchofjesuschrist.org and count the
 * over/under words.
 *
 * Talk text posts to the site within a day or two of each session — weeks before
 * the Liahona PDF — so this is how you settle the over/unders on the Monday
 * after conference.
 *
 *   php tools/count_words.php 2026/10           # fetch and print the counts
 *   php tools/count_words.php 2026/10 --write   # also store them as results
 *   php tools/count_words.php 2026/10 --fresh   # ignore the local cache
 *
 * Counts only what was actually spoken: the talk body, minus footnotes, titles,
 * bylines and the editorial summary line. Responses are cached under
 * tools/cache/ so re-runs don't re-hit the site.
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit("Run this from the command line.\n");
}

require_once dirname(__DIR__) . '/lib/bootstrap.php';
require_once APP_ROOT . '/lib/questions.php';
require_once APP_ROOT . '/lib/scoring.php';

const API  = 'https://www.churchofjesuschrist.org/study/api/v3/language-pages/type/content?lang=eng&uri=';
const UA   = 'FantasyGeneralConference/1.0 (personal word-count for a youth guessing game)';
const WAIT = 400000; // microseconds between requests — be a good guest

$args  = array_slice($argv, 1);
$conf  = null;
$write = in_array('--write', $args, true);
$fresh = in_array('--fresh', $args, true);
foreach ($args as $a) {
    if (preg_match('#^(20\d\d)/(0[41]|10)$#', $a)) {
        $conf = $a;
    }
}
if ($conf === null) {
    exit("Usage: php tools/count_words.php YYYY/MM [--write] [--fresh]\n"
       . "  e.g. php tools/count_words.php 2026/10 --write\n");
}

$cacheDir = __DIR__ . '/cache/' . str_replace('/', '-', $conf);
if (!is_dir($cacheDir)) {
    mkdir($cacheDir, 0775, true);
}

function fetch_uri(string $uri, string $cacheDir, bool $fresh): ?array
{
    $file = $cacheDir . '/' . preg_replace('#[^a-z0-9]+#i', '_', $uri) . '.json';
    if (!$fresh && is_file($file)) {
        return json_decode((string)file_get_contents($file), true);
    }

    $ch = curl_init(API . str_replace('%2F', '/', rawurlencode($uri)));
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_USERAGENT      => UA,
        CURLOPT_ENCODING       => '',
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_FOLLOWLOCATION => true,
    ]);
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    usleep(WAIT);

    if ($body === false || $code !== 200) {
        fwrite(STDERR, "  !! HTTP $code for $uri\n");
        return null;
    }
    file_put_contents($file, $body);
    return json_decode((string)$body, true);
}

/** Spoken text only: body-block minus footnotes, title, byline and kicker. */
function spoken_text(string $html): string
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
    // Drop everything that is printed but not said.
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
        $text = $doc->textContent; // fall back to the whole payload
    }

    $text = str_replace(["\u{00A0}", "\u{2019}", "\u{2018}"], [' ', "'", "'"], $text);
    return trim((string)preg_replace('/\s+/u', ' ', $text));
}

// ---- 1. table of contents ----
echo "Fetching table of contents for $conf ...\n";
$toc = fetch_uri("/general-conference/$conf", $cacheDir, $fresh);
if (!$toc) {
    exit("Could not load the conference table of contents.\n");
}

preg_match_all(
    '#/study/general-conference/' . preg_quote($conf, '#') . '/([0-9a-z\-]+)#i',
    json_encode($toc, JSON_UNESCAPED_SLASHES),
    $m
);
$slugs = array_values(array_unique($m[1]));
$slugs = array_filter($slugs, static fn($s) => !str_ends_with($s, '-session'));
sort($slugs);

if (!$slugs) {
    exit("No talks listed yet for $conf — the site may not have posted them.\n");
}
echo 'Found ' . count($slugs) . " talks.\n";

// ---- 2. what are we counting? ----
$event = get_event();
$patterns = [];
foreach (get_questions((int)$event['id'], true) as $qq) {
    if ($qq['type'] === 'over_under' && !empty($qq['config']['pattern'])) {
        $patterns[$qq['qkey']] = [
            'pattern' => $qq['config']['pattern'],
            'line'    => $qq['config']['line'] ?? null,
            'label'   => $qq['prompt'],
            'id'      => (int)$qq['id'],
        ];
    }
}
if (!$patterns) {
    exit("No over/under questions carry a 'pattern' in their config — nothing to count.\n");
}

// ---- 3. fetch and count ----
$totals  = array_fill_keys(array_keys($patterns), 0);
$words   = 0;
$perTalk = [];

foreach ($slugs as $i => $slug) {
    printf("  [%2d/%2d] %s", $i + 1, count($slugs), $slug);
    $data = fetch_uri("/general-conference/$conf/$slug", $cacheDir, $fresh);
    if (!$data || empty($data['content']['body'])) {
        echo "  — skipped\n";
        continue;
    }
    $text = spoken_text($data['content']['body']);
    $words += str_word_count($text);

    $row = ['slug' => $slug, 'words' => str_word_count($text)];
    foreach ($patterns as $key => $p) {
        $n = preg_match_all($p['pattern'], $text);
        $totals[$key] += $n;
        $row[$key] = $n;
    }
    $perTalk[] = $row;
    echo "  " . str_pad((string)str_word_count($text), 5, ' ', STR_PAD_LEFT) . " words\n";
}

// ---- 4. report ----
echo "\n" . str_repeat('=', 74) . "\n";
printf("%-46s %7s %7s  %s\n", "Term", "Count", "Line", "Result");
echo str_repeat('-', 74) . "\n";
foreach ($patterns as $key => $p) {
    $n = $totals[$key];
    $line = $p['line'];
    $verdict = $line === null ? '—'
        : ($n > $line ? 'OVER' : ($n < $line ? 'UNDER' : 'PUSH (nobody scores)'));
    printf("%-46s %7d %7s  %s\n", mb_strimwidth($p['label'], 0, 45, '…'), $n, $line ?? '—', $verdict);
}
echo str_repeat('-', 74) . "\n";
printf("%-46s %7d\n", 'spoken words across ' . count($perTalk) . ' talks', $words);
echo str_repeat('=', 74) . "\n";

// ---- 5. optionally store ----
if ($write) {
    $pdo = db();
    $pdo->beginTransaction();
    $st = $pdo->prepare(
        'INSERT INTO results (question_id, value, numeric_value) VALUES (?, NULL, ?)
         ON DUPLICATE KEY UPDATE numeric_value = VALUES(numeric_value), value = NULL'
    );
    foreach ($patterns as $key => $p) {
        $st->execute([$p['id'], (float)$totals[$key]]);
    }
    $pdo->commit();
    $n = recompute_event_scores((int)$event['id']);
    echo "\nResults stored and everyone rescored ($n scoring rows).\n";
} else {
    echo "\nDry run. Add --write to store these as results and rescore.\n";
}
