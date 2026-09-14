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
            'blurb' => 'Three points each. Close enough counts — the scorekeeper decides.',
            'layout' => 'list',
        ],
        'counts' => [
            'title' => 'Counting',
            'blurb' => 'Three points each, exact answers only.',
            'layout' => 'list',
        ],
        'words' => [
            'title' => 'Over / under',
            'blurb' => 'Pick a side. Three points each. An exact tie scores nothing.',
            'layout' => 'list',
        ],
        'watched' => [
            'title' => 'Sessions watched',
            'blurb' => 'One point for every session you actually watched.',
            'layout' => 'list',
        ],
    ];
}

function get_event(?string $slug = null): ?array
{
    if ($slug !== null) {
        return q1('SELECT * FROM events WHERE slug = ?', [$slug]);
    }
    return q1('SELECT * FROM events ORDER BY id DESC LIMIT 1');
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
        foreach ($question['options'] as $o) {
            if ($o['value'] === $value) {
                return $o['label'];
            }
        }
        return $value;
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
