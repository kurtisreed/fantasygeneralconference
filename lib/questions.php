<?php
declare(strict_types=1);

/** Display metadata for each section of the sheet, in sheet order. */
function section_meta(): array
{
    return [
        'apostles' => [
            'title' => 'Who speaks when?',
            'blurb' => 'Pick one session for each apostle. One point per correct guess.',
            'layout' => 'grid',
        ],
        'conducting' => [
            'title' => 'Who conducts?',
            'blurb' => 'Which apostle will conduct each session? One point each.',
            'layout' => 'list',
        ],
        'first_speaker' => [
            'title' => 'First speaker',
            'blurb' => 'Who gives the first talk of each session? Two points each.',
            'layout' => 'list',
        ],
        'choir' => [
            'title' => 'The choir',
            'blurb' => 'Which choir sings in each session? One point each.',
            'layout' => 'list',
        ],
        'colors' => [
            'title' => 'Colors',
            'blurb' => 'Three points each. Pick the closest color from the list.',
            'layout' => 'list',
        ],
        'words' => [
            'title' => 'Over / under',
            'blurb' => 'Pick a side. Three points each. Every line ends in a half, so there are no ties.',
            'layout' => 'list',
        ],
        'watched' => [
            'title' => 'Sessions watched',
            'blurb' => 'Credit for every session you actually watched.',
            'layout' => 'list',
        ],
    ];
}

/** Newest conference first, by when it actually happened. */
const EVENT_ORDER = 'COALESCE(starts_at, DATE(lock_at), DATE(created_at)) DESC, id DESC';

function get_event(?string $slug = null): ?array
{
    if ($slug !== null) {
        return q1('SELECT * FROM events WHERE slug = ?', [$slug]);
    }
    // Latest by date, not by insertion order — a past conference backfilled
    // today must not become "current". Drafts are skipped so that setting up
    // the next conference early cannot hide the one people are still playing;
    // the fallback keeps a fresh install (everything still draft) working.
    return q1('SELECT * FROM events WHERE status <> "draft" ORDER BY ' . EVENT_ORDER . ' LIMIT 1')
        ?? q1('SELECT * FROM events ORDER BY ' . EVENT_ORDER . ' LIMIT 1');
}

/**
 * Display details for one section, with a sane fallback.
 *
 * A section can be retired in the code while a conference's questions still
 * carry it — right up until somebody runs Questions → Update the sheet. This
 * keeps that window from rendering a blank heading.
 */
function section_info(string $key): array
{
    $meta = section_meta();
    return $meta[$key] ?? [
        'title'  => ucfirst(str_replace('_', ' ', $key)),
        'blurb'  => '',
        'layout' => 'list',
    ];
}

/**
 * Points per session watched, read off the participation question rather than
 * written out in each place that mentions it.
 */
function watched_per(array $questions): int
{
    foreach ($questions as $q) {
        if ($q['type'] === 'watched') {
            return (int)($q['config']['per'] ?? 1);
        }
    }
    return 1;
}

/** Most sessions a player can be credited for, read off the same question. */
function watched_max(array $questions): int
{
    foreach ($questions as $q) {
        if ($q['type'] === 'watched') {
            return (int)($q['config']['max'] ?? 4);
        }
    }
    return 4;
}

/**
 * Real-world start time for each session, keyed by code. There's no
 * per-session time in the database — General Conference always runs two
 * sessions a day, Saturday and Sunday, at 10am and 2pm Mountain Time — so
 * this derives it from the conference's Saturday date instead. Empty when
 * that date isn't set yet.
 */
function session_start_times(array $event, array $sessions): array
{
    if (empty($event['starts_at'])) {
        return [];
    }
    $saturday = strtotime((string)$event['starts_at']);
    $out = [];
    foreach (array_values($sessions) as $i => $s) {
        $day  = intdiv($i, 2);
        $hour = $i % 2 === 0 ? 10 : 14;
        $t = strtotime("+$day day +$hour hours", $saturday);
        if ($t !== false) {
            $out[$s['code']] = $t;
        }
    }
    return $out;
}

/**
 * How many "sessions watched" points are even possible yet. That question
 * needs no admin result — it's self-reported — so without this, a session
 * that hasn't happened yet would already count as "scored" on the standings
 * page the moment the conference is created.
 */
function watched_points_scored_so_far(array $event, array $sessions, array $questions): int
{
    $per = watched_per($questions);
    $max = watched_max($questions);
    $times = session_start_times($event, $sessions);
    if (!$times) {
        return $per * $max; // unknown schedule: don't block on it
    }
    $now = time();
    $started = count(array_filter($times, static fn($t) => $t <= $now));
    return min($started, $max) * $per;
}

/**
 * Session codes that haven't started yet — a session nobody could have
 * watched, so the self-report checkbox for it stays disabled until then.
 */
function future_session_codes(array $event, array $sessions): array
{
    $times = session_start_times($event, $sessions);
    if (!$times) {
        return []; // unknown schedule: don't block anything
    }
    $now = time();
    return array_keys(array_filter($times, static fn($t) => $t > $now));
}

function get_event_by_id(int $id): ?array
{
    return q1('SELECT * FROM events WHERE id = ?', [$id]);
}

/** Every conference, newest first, with enough counts to summarise each one. */
function get_events(): array
{
    return q(
        'SELECT e.*,
                (SELECT COUNT(*) FROM players p
                  WHERE p.event_id = e.id AND p.submitted_at IS NOT NULL) AS player_count,
                (SELECT COUNT(*) FROM questions q2
                  WHERE q2.event_id = e.id AND q2.active = 1) AS question_count,
                (SELECT COUNT(*) FROM results r
                   JOIN questions q3 ON q3.id = r.question_id
                  WHERE q3.event_id = e.id) AS result_count
           FROM events e
          ORDER BY COALESCE(e.starts_at, DATE(e.lock_at), DATE(e.created_at)) DESC, e.id DESC'
    );
}

function event_count(): int
{
    return (int)q1('SELECT COUNT(*) AS c FROM events')['c'];
}

/** True once a conference has something worth showing on a standings page. */
function event_has_standings(array $event): bool
{
    return (int)($event['player_count'] ?? 0) > 0;
}

function get_sessions(int $eventId): array
{
    return q('SELECT * FROM sessions WHERE event_id = ? ORDER BY sort_order', [$eventId]);
}

/** All active questions for an event, each with decoded config and its options. */
function get_questions(int $eventId, bool $includeInactive = false): array
{
    $rows = q(
        'SELECT q.*, s.short_name AS session_short, s.name AS session_name
           FROM questions q
           LEFT JOIN sessions s ON s.id = q.session_id
          WHERE q.event_id = ?' . ($includeInactive ? '' : ' AND q.active = 1') . '
          ORDER BY q.sort_order, q.id',
        [$eventId]
    );
    if (!$rows) {
        return [];
    }

    $byId = [];
    foreach ($rows as &$r) {
        $r['config'] = $r['config'] ? (json_decode($r['config'], true) ?: []) : [];
        $r['options'] = [];
        $r['points'] = (int)$r['points'];
        $byId[(int)$r['id']] = &$r;
    }
    unset($r);

    $ids = implode(',', array_map('intval', array_keys($byId)));
    $opts = q("SELECT * FROM question_options WHERE question_id IN ($ids) ORDER BY question_id, sort_order");
    foreach ($opts as $o) {
        $byId[(int)$o['question_id']]['options'][] = $o;
    }

    return $rows;
}

/** Group a question list by section, preserving section_meta() order. */
function group_by_section(array $questions): array
{
    $out = [];
    foreach (array_keys(section_meta()) as $sec) {
        $out[$sec] = [];
    }
    foreach ($questions as $q) {
        $out[$q['section']][] = $q;
    }
    return array_filter($out, static fn($v) => $v !== []);
}

function total_points_possible(array $questions): int
{
    $t = 0;
    foreach ($questions as $q) {
        $t += (int)$q['points'];
    }
    return $t;
}

/** True when players may still create or edit an entry. */
function entries_open(array $event): bool
{
    if ($event['status'] !== 'open') {
        return false;
    }
    if (!empty($event['lock_at']) && strtotime((string)$event['lock_at']) <= time()) {
        return false;
    }
    return true;
}

function get_answers(int $playerId): array
{
    $out = [];
    foreach (q('SELECT question_id, value FROM answers WHERE player_id = ?', [$playerId]) as $r) {
        $out[(int)$r['question_id']] = $r['value'];
    }
    return $out;
}

function get_results(int $eventId): array
{
    $out = [];
    $rows = q(
        'SELECT r.* FROM results r
           JOIN questions q ON q.id = r.question_id
          WHERE q.event_id = ?',
        [$eventId]
    );
    foreach ($rows as $r) {
        $out[(int)$r['question_id']] = $r;
    }
    return $out;
}

/** Human-readable form of a stored answer or result value. */
function display_value(array $question, ?string $value): string
{
    if ($value === null || $value === '') {
        return '—';
    }
    if ($question['type'] === 'pick_one') {
        // A result can list more than one accepted session (see the apostles
        // section), so look up each piece rather than the whole string.
        $byValue = array_column($question['options'], 'label', 'value');
        $labels = [];
        foreach (explode(',', $value) as $v) {
            $labels[] = $byValue[$v] ?? $v;
        }
        return implode(', ', $labels);
    }
    if ($question['type'] === 'over_under') {
        $line = $question['config']['line'] ?? '?';
        return ucfirst($value) . ' ' . $line;
    }
    return $value;
}

/** Human-readable form of the official result for a question. */
function display_result(array $question, ?array $result): string
{
    if ($result === null) {
        return '—';
    }
    if ($question['type'] === 'number' || $question['type'] === 'over_under') {
        if ($result['numeric_value'] === null) {
            return '—';
        }
        $n = (float)$result['numeric_value'];
        $s = rtrim(rtrim(number_format($n, 2, '.', ''), '0'), '.');
        if ($question['type'] === 'over_under') {
            $line = (float)($question['config']['line'] ?? 0);
            $side = $n > $line ? 'over' : ($n < $line ? 'under' : 'push');
            return $s . ' (' . $side . ')';
        }
        return $s;
    }
    return display_value($question, $result['value'] ?? null);
}
