<?php
/**
 * Count the over/under words for a conference, from the command line.
 *
 * The same job as Admin → Word counts, for when a shell is handier than a
 * browser. Both share lib/harvest.php.
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
    exit("Run this from the command line, or use Admin → Word counts in a browser.\n");
}

require_once dirname(__DIR__) . '/lib/bootstrap.php';
require_once APP_ROOT . '/lib/questions.php';
require_once APP_ROOT . '/lib/scoring.php';
require_once APP_ROOT . '/lib/harvest.php';

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

if ($fresh) {
    $n = harvest_clear_cache($conf);
    echo "Cleared $n cached files.\n";
}

$event    = get_event();
$patterns = harvest_patterns((int)$event['id']);
if (!$patterns) {
    exit("No over/under questions carry a 'pattern' in their config — nothing to count.\n");
}

echo "Fetching the talk list for $conf ...\n";
$slugs = harvest_slugs($conf);
if (!$slugs) {
    exit("Could not read the conference table of contents.\n");
}
echo 'Found ' . count($slugs) . " talks.\n";

foreach ($slugs as $i => $slug) {
    $uri = "/general-conference/$conf/$slug";
    $had = harvest_is_cached($conf, $uri);
    printf("  [%2d/%2d] %-22s %s\n", $i + 1, count($slugs), $slug,
        $had ? 'cached' : (harvest_fetch($conf, $uri) === null ? 'FAILED' : 'fetched'));
}

$counts = harvest_count($conf, $slugs, $patterns);

echo "\n" . str_repeat('=', 74) . "\n";
printf("%-46s %7s %7s  %s\n", 'Term', 'Count', 'Line', 'Result');
echo str_repeat('-', 74) . "\n";
foreach ($patterns as $key => $p) {
    $n    = $counts['totals'][$key] ?? 0;
    $line = $p['line'];
    $verdict = $line === null ? '—'
        : ($n > (float)$line ? 'OVER' : ($n < (float)$line ? 'UNDER' : 'PUSH (nobody scores)'));
    printf("%-46s %7d %7s  %s\n", mb_strimwidth($p['label'], 0, 45, '…'), $n, $line ?? '—', $verdict);
}
echo str_repeat('-', 74) . "\n";
printf("%-46s %7d\n", 'spoken words across ' . $counts['talks'] . ' talks', $counts['words']);
echo str_repeat('=', 74) . "\n";

if ($write) {
    $n = harvest_write_results($patterns, $counts['totals']);
    recompute_event_scores((int)$event['id']);
    echo "\n$n results stored and everyone rescored.\n";
} else {
    echo "\nDry run. Add --write to store these as results and rescore.\n";
}
