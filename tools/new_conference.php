<?php
/**
 * Set up the sheet for a new conference. Run this every six months.
 *
 *   php tools/new_conference.php october-2026 "October 2026 General Conference" \
 *        --lock="2026-10-03 10:00"
 *
 *   --lock="YYYY-MM-DD HH:MM"  when picks freeze (Mountain Time); normally the
 *                              start of the Saturday morning session
 *   --open                     open it for picks right away instead of leaving
 *                              it in draft
 *
 * Creates the event and its questions if the slug is new, or refreshes the
 * questions in place if it already exists. Player picks and results are never
 * touched. The newest event is the one the site shows.
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit("Run this from the command line.\n");
}

require_once dirname(__DIR__) . '/lib/bootstrap.php';
require_once APP_ROOT . '/lib/seed.php';
require_once APP_ROOT . '/lib/questions.php';

$args = array_slice($argv, 1);
$lock = null;
$open = false;
$pos  = [];

foreach ($args as $a) {
    if (str_starts_with($a, '--lock=')) {
        $lock = trim(substr($a, 7), '"\'');
    } elseif ($a === '--open') {
        $open = true;
    } elseif (!str_starts_with($a, '--')) {
        $pos[] = $a;
    }
}

if (count($pos) < 2) {
    exit("Usage: php tools/new_conference.php <slug> \"<name>\" [--lock=\"YYYY-MM-DD HH:MM\"] [--open]\n");
}
[$slug, $name] = $pos;

if (!preg_match('/^[a-z0-9-]{3,64}$/', $slug)) {
    exit("Slug must be lowercase letters, numbers and dashes.\n");
}
if ($lock !== null && !strtotime($lock)) {
    exit("Could not read --lock as a date/time.\n");
}

$existing = q1('SELECT * FROM events WHERE slug = ?', [$slug]);
$eventId  = fgc_seed($slug, $name);

exec_sql(
    'UPDATE events SET lock_at = ?, status = ? WHERE id = ?',
    [
        $lock !== null ? date('Y-m-d H:i:s', (int)strtotime($lock)) : ($existing['lock_at'] ?? null),
        $open ? 'open' : ($existing['status'] ?? 'draft'),
        $eventId,
    ]
);

$event     = q1('SELECT * FROM events WHERE id = ?', [$eventId]);
$questions = get_questions($eventId);

echo ($existing ? "Refreshed" : "Created") . " event #$eventId — {$event['name']}\n";
echo "  slug      {$event['slug']}\n";
echo "  status    {$event['status']}\n";
echo "  picks lock " . ($event['lock_at'] ?: 'no deadline set') . "\n";
echo '  questions ' . count($questions) . ', ' . total_points_possible($questions) . " points\n\n";

foreach (group_by_section($questions) as $sec => $qs) {
    printf("  %-14s %2d question%s  %2d pts\n",
        section_meta()[$sec]['title'], count($qs), count($qs) === 1 ? ' ' : 's', total_points_possible($qs));
}

echo "\nOver/under lines:\n";
foreach ($questions as $qq) {
    if ($qq['type'] === 'over_under') {
        printf("  %-38s %s\n", mb_strimwidth($qq['prompt'], 0, 37, '…'), $qq['config']['line'] ?? '—');
    }
}

if (!$open) {
    echo "\nStill in draft. Open it on the Event page (or re-run with --open) when you're ready to share the link.\n";
}
