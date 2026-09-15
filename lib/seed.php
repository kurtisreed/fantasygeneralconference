<?php
declare(strict_types=1);

/**
 * Question definitions for the sheet.
 *
 * Re-running the seed RESETS prompts, point values and over/under lines to the
 * defaults below. Player answers and results are never touched.
 */

const FGC_APOSTLES = [
    'oaks'           => 'Oaks',
    'eyring'         => 'Eyring',
    'christofferson' => 'Christofferson',
    'uchtdorf'       => 'Uchtdorf',
    'bednar'         => 'Bednar',
    'cook'           => 'Cook',
    'andersen'       => 'Andersen',
    'rasband'        => 'Rasband',
    'stevenson'      => 'Stevenson',
    'renlund'        => 'Renlund',
    'gong'           => 'Gong',
    'soares'         => 'Soares',
    'kearon'         => 'Kearon',
    'causse'         => 'Caussé',
    'gilbert'        => 'Gilbert',
];

/**
 * The colour palette for the tie and choir questions.
 *
 * Basic colour names only. Anyone watching should be able to name the colour
 * from across the room and land on the same answer as everybody else; finer
 * shades (navy vs. royal, gold vs. yellow) only move the argument from the
 * scorekeeper to the players.
 */
const FGC_COLORS = [
    'red'    => 'Red',
    'pink'   => 'Pink',
    'orange' => 'Orange',
    'yellow' => 'Yellow',
    'green'  => 'Green',
    'blue'   => 'Blue',
    'purple' => 'Purple',
    'gray'   => 'Gray',
    'brown'  => 'Brown',
    'black'  => 'Black',
    'white'  => 'White',
];

const FGC_SESSIONS = [
    ['sat_am', 'Saturday Morning',   'Sat AM'],
    ['sat_pm', 'Saturday Afternoon', 'Sat PM'],
    ['sun_am', 'Sunday Morning',     'Sun AM'],
    ['sun_pm', 'Sunday Afternoon',   'Sun PM'],
];

/**
 * The Saturday and Sunday of a given conference.
 *
 * Conference is the weekend whose *Sunday* is the first Sunday of the month —
 * not the first Saturday. In October 2023 that meant Sept 30 / Oct 1, which a
 * first-Saturday rule gets wrong.
 */
function conference_weekend(int $year, int $month): array
{
    $sunday = strtotime('first sunday of ' . date('F', (int)mktime(0, 0, 0, $month, 1, $year)) . ' ' . $year);
    return [date('Y-m-d', (int)strtotime('-1 day', (int)$sunday)), date('Y-m-d', (int)$sunday)];
}

/**
 * The next conference after a date, as [slug, name, saturday, sunday].
 * Used to prefill the "start the next conference" form.
 */
function next_conference_after(?string $after = null): array
{
    $after ??= date('Y-m-d');
    $startYear = (int)date('Y', (int)strtotime($after));

    for ($year = $startYear; $year <= $startYear + 3; $year++) {
        foreach ([4, 10] as $month) {
            [$sat, $sun] = conference_weekend($year, $month);
            if ($sat > $after) {
                $label = $month === 4 ? "April $year" : "October $year";
                return [
                    'slug'     => strtolower(str_replace(' ', '-', $label)),
                    'name'     => $label . ' General Conference',
                    'saturday' => $sat,
                    'sunday'   => $sun,
                ];
            }
        }
    }
    return ['slug' => '', 'name' => '', 'saturday' => '', 'sunday' => ''];
}

require_once __DIR__ . '/questions.php';

function get_speakers(int $eventId): array
{
    return q('SELECT * FROM speakers WHERE event_id = ? ORDER BY sort_order, id', [$eventId]);
}

/** Turn a typed name into a stable key: "Caussé" -> "causse". */
function speaker_slug(string $name): string
{
    $s = strtr($name, [
        'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e', 'á' => 'a', 'à' => 'a',
        'â' => 'a', 'ä' => 'a', 'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i',
        'ó' => 'o', 'ò' => 'o', 'ô' => 'o', 'ö' => 'o', 'ú' => 'u', 'ù' => 'u',
        'û' => 'u', 'ü' => 'u', 'ñ' => 'n', 'ç' => 'c',
    ]);
    $s = mb_strtolower($s, 'UTF-8');
    $s = preg_replace('/[^a-z0-9]+/', '_', $s) ?? $s;
    return trim((string)$s, '_');
}

/**
 * Give an event a speaker list if it has none: inherit the previous
 * conference's, so the names only need typing when they actually change.
 */
function fgc_ensure_speakers(int $eventId): array
{
    if ($rows = get_speakers($eventId)) {
        return $rows;
    }

    $prev = q1(
        'SELECT s.event_id FROM speakers s
           JOIN events e ON e.id = s.event_id
          WHERE e.id <> ?
          ORDER BY COALESCE(e.starts_at, DATE(e.lock_at), DATE(e.created_at)) DESC, e.id DESC
          LIMIT 1',
        [$eventId]
    );

    $source = [];
    if ($prev) {
        foreach (get_speakers((int)$prev['event_id']) as $r) {
            $source[] = [$r['slug'], $r['name']];
        }
    } else {
        foreach (FGC_APOSTLES as $s => $n) {
            $source[] = [$s, $n];
        }
    }

    $i = 0;
    foreach ($source as [$s, $n]) {
        exec_sql(
            'INSERT INTO speakers (event_id, slug, name, sort_order) VALUES (?,?,?,?)
             ON DUPLICATE KEY UPDATE name=VALUES(name), sort_order=VALUES(sort_order)',
            [$eventId, $s, $n, $i++]
        );
    }
    return get_speakers($eventId);
}

function set_question_options(int $questionId, array $options): void
{
    exec_sql('DELETE FROM question_options WHERE question_id = ?', [$questionId]);
    foreach ($options as [$value, $label, $ord]) {
        exec_sql(
            'INSERT INTO question_options (question_id, value, label, sort_order) VALUES (?,?,?,?)',
            [$questionId, $value, $label, $ord]
        );
    }
}

/** Drop picks that no longer match any option — e.g. after a speaker is removed. */
function fgc_prune_answers(int $eventId): int
{
    $dropped = 0;
    foreach (get_questions($eventId, true) as $qq) {
        if ($qq['type'] !== 'pick_one' || !$qq['options']) {
            continue;
        }
        $valid = array_column($qq['options'], 'value');
        $in = implode(',', array_fill(0, count($valid), '?'));
        $dropped += exec_sql(
            "DELETE FROM answers WHERE question_id = ? AND value NOT IN ($in)",
            array_merge([(int)$qq['id']], $valid)
        );
    }
    return $dropped;
}

/** Put the questions back in sheet order after the speaker list changes. */
function fgc_renumber(int $eventId): void
{
    $pos = 0;
    foreach (array_keys(section_meta()) as $section) {
        foreach (q('SELECT id FROM questions WHERE event_id=? AND section=? ORDER BY sort_order, id',
                   [$eventId, $section]) as $r) {
            exec_sql('UPDATE questions SET sort_order = ? WHERE id = ?', [$pos++, (int)$r['id']]);
        }
    }
}

/**
 * Rebuild everything derived from the speaker list: one grid question per
 * speaker, and the speaker options on the conducting and first-speaker
 * questions. Point values already set by hand are kept.
 *
 * Returns ['removed' => grid rows dropped, 'dropped' => picks invalidated].
 */
function fgc_sync_speakers(int $eventId): array
{
    $pdo = db();
    $owns = !$pdo->inTransaction();
    if ($owns) {
        $pdo->beginTransaction();
    }

    $speakers = fgc_ensure_speakers($eventId);

    $sessionOptions = [];
    foreach (q('SELECT * FROM sessions WHERE event_id=? ORDER BY sort_order', [$eventId]) as $i => $s) {
        $sessionOptions[] = [$s['code'], $s['short_name'], $i];
    }

    $speakerOptions = [];
    $wanted = [];
    foreach ($speakers as $i => $s) {
        $speakerOptions[] = [$s['slug'], $s['name'], $i];
        $wanted['apostle_' . $s['slug']] = true;
    }

    foreach ($speakers as $i => $s) {
        $qkey = 'apostle_' . $s['slug'];
        $existing = q1('SELECT points FROM questions WHERE event_id=? AND qkey=?', [$eventId, $qkey]);
        exec_sql(
            'INSERT INTO questions (event_id, qkey, section, type, prompt, points, sort_order, active)
             VALUES (?,?,"apostles","pick_one",?,?,?,1)
             ON DUPLICATE KEY UPDATE prompt=VALUES(prompt), points=VALUES(points),
                                     sort_order=VALUES(sort_order), active=1',
            [$eventId, $qkey, $s['name'], $existing ? (int)$existing['points'] : 1, $i]
        );
        $qid = (int)q1('SELECT id FROM questions WHERE event_id=? AND qkey=?', [$eventId, $qkey])['id'];
        set_question_options($qid, $sessionOptions);
    }

    $removed = 0;
    foreach (q('SELECT id, qkey FROM questions WHERE event_id=? AND section="apostles"', [$eventId]) as $row) {
        if (!isset($wanted[$row['qkey']])) {
            exec_sql('DELETE FROM questions WHERE id = ?', [(int)$row['id']]);
            $removed++;
        }
    }

    $firstOptions = $speakerOptions;
    $firstOptions[] = ['other', 'Someone else (Seventy, auxiliary leader, etc.)', 99];
    foreach (q('SELECT id, section FROM questions WHERE event_id=? AND section IN ("conducting","first_speaker")',
               [$eventId]) as $row) {
        set_question_options(
            (int)$row['id'],
            $row['section'] === 'first_speaker' ? $firstOptions : $speakerOptions
        );
    }

    $dropped = fgc_prune_answers($eventId);
    fgc_renumber($eventId);

    if ($owns) {
        $pdo->commit();
    }
    return ['removed' => $removed, 'dropped' => $dropped];
}

function fgc_seed(string $slug = 'october-2026', string $name = 'October 2026 General Conference'): int
{
    $pdo = db();
    $pdo->beginTransaction();

    exec_sql(
        'INSERT INTO events (slug, name, status) VALUES (?, ?, "draft")
         ON DUPLICATE KEY UPDATE name = VALUES(name)',
        [$slug, $name]
    );
    $eventId = (int)q1('SELECT id FROM events WHERE slug = ?', [$slug])['id'];

    // ---- sessions ----
    $sessionIds = [];
    foreach (FGC_SESSIONS as $i => [$code, $full, $short]) {
        exec_sql(
            'INSERT INTO sessions (event_id, code, name, short_name, sort_order) VALUES (?,?,?,?,?)
             ON DUPLICATE KEY UPDATE name=VALUES(name), short_name=VALUES(short_name), sort_order=VALUES(sort_order)',
            [$eventId, $code, $full, $short, $i]
        );
        $sessionIds[$code] = (int)q1(
            'SELECT id FROM sessions WHERE event_id=? AND code=?', [$eventId, $code]
        )['id'];
    }

    $sessionOptions = [];
    foreach (FGC_SESSIONS as $i => [$code, $full, $short]) {
        $sessionOptions[] = [$code, $short, $i];
    }

    // The speaker list belongs to the conference, not to this file — a new
    // conference inherits the previous one's until somebody edits it.
    $speakers = fgc_ensure_speakers($eventId);

    $apostleOptions = [];
    foreach ($speakers as $i => $s) {
        $apostleOptions[] = [$s['slug'], $s['name'], $i];
    }

    $order = 0;
    $defs = [];

    // ---- 1. Apostle grid: one row per speaker, one session each ----
    foreach ($speakers as $s) {
        $defs[] = [
            'qkey'    => 'apostle_' . $s['slug'],
            'section' => 'apostles',
            'type'    => 'pick_one',
            'prompt'  => $s['name'],
            'points'  => 1,
            'options' => $sessionOptions,
        ];
    }

    // ---- 2. Who conducts each session (4 pts) ----
    foreach (FGC_SESSIONS as [$code, $full, $short]) {
        $defs[] = [
            'qkey'       => 'conduct_' . $code,
            'section'    => 'conducting',
            'type'       => 'pick_one',
            'prompt'     => $full,
            'points'     => 1,
            'session'    => $code,
            'options'    => $apostleOptions,
        ];
    }

    // ---- 3. First speaker of each session (8 pts) ----
    $firstSpeakerOptions = $apostleOptions;
    $firstSpeakerOptions[] = ['other', 'Someone else (Seventy, auxiliary leader, etc.)', 99];
    foreach (FGC_SESSIONS as [$code, $full, $short]) {
        $defs[] = [
            'qkey'    => 'first_speaker_' . $code,
            'section' => 'first_speaker',
            'type'    => 'pick_one',
            'prompt'  => $full,
            'help_text' => 'The first person to give a talk, not the one conducting.',
            'points'  => 2,
            'session' => $code,
            'options' => $firstSpeakerOptions,
        ];
    }

    // ---- 4. Choir per session (4 pts) ----
    foreach (FGC_SESSIONS as [$code, $full, $short]) {
        $defs[] = [
            'qkey'    => 'choir_' . $code,
            'section' => 'choir',
            'type'    => 'pick_one',
            'prompt'  => $full,
            'points'  => 1,
            'session' => $code,
            'options' => [
                ['tabernacle', 'Tabernacle Choir', 0],
                ['other',      'Other Choir',      1],
            ],
        ];
    }

    // ---- 5. Colors (12 pts) ----
    // A fixed palette, so a colour answer is either right or wrong and nobody
    // has to arbitrate whether "navy" and "dark blue" are the same thing.
    $colorOptions = [];
    $i = 0;
    foreach (FGC_COLORS as $value => $label) {
        $colorOptions[] = [$value, $label, $i++];
    }

    $colorQuestions = [
        ['tie_oaks',           'What color tie will President Oaks wear when he speaks?'],
        ['tie_eyring',         'What color tie will President Eyring wear when he speaks?'],
        ['tie_christofferson', 'What color tie will President Christofferson wear when he speaks?'],
        ['choir_dress',        'What color will the women in the Tabernacle Choir wear on Sunday morning?'],
    ];
    foreach ($colorQuestions as [$key, $prompt]) {
        $defs[] = [
            'qkey'    => $key,
            'section' => 'colors',
            'type'    => 'pick_one',
            'points'  => 3,
            'prompt'  => $prompt,
            'options' => $colorOptions,
        ];
    }

    // ---- 6. Over/unders (24 pts) ----
    // Every line is measured against the last three conferences and ends in a
    // half, so a tie is impossible; see docs/word-counts.md.
    // 'pattern' is what the word count actually counts, so the rule on the
    // sheet and the rule the harvester applies can never drift apart. The
    // people-counting ones carry no pattern — they are entered by hand.
    $words = [
        ['wc_jesus_christ', 'the phrase "Jesus Christ"', 287.5, '/\bJesus\s+Christ\b/iu',
         'Includes "The Church of Jesus Christ of Latter-day Saints."'],
        ['wc_temple', 'the word "temple" or "temples"', 44.5, '/\btemples?\b/iu',
         'Singular and plural both count.'],
        ['wc_covenant', 'the word "covenant" or "covenants"', 87.5, '/\bcovenants?\b/iu',
         'Both count, including "Doctrine and Covenants."'],
        ['wc_book_of_mormon', 'the phrase "Book of Mormon"', 32.5, '/\bBook\s+of\s+Mormon\b/iu',
         'Counted as a phrase.'],
    ];
    foreach ($words as [$key, $what, $line, $pattern, $note]) {
        $defs[] = [
            'qkey'      => $key,
            'section'   => 'words',
            'type'      => 'over_under',
            'points'    => 3,
            'prompt'    => 'How many times will ' . $what . ' be said?',
            'help_text' => $note . ' Counted from the talks on the Church website.',
            'config'    => ['line' => $line, 'pattern' => $pattern],
        ];
    }
    $defs[] = [
        'qkey'      => 'quoted_oaks',
        'section'   => 'words',
        'type'      => 'over_under',
        'points'    => 3,
        'prompt'    => 'How many times will President Oaks be quoted by another speaker?',
        'help_text' => 'A speaker quoting or citing President Oaks by name.',
        'config'    => ['line' => 8.5],   // half-point: no ties here either
    ];

    // Counting people rather than words. No pattern — the transcripts don't
    // say who prayed or where anybody was born, so these are entered by hand.
    $defs[] = [
        'qkey' => 'women_pray', 'section' => 'words', 'type' => 'over_under', 'points' => 3,
        'prompt' => 'How many women will give prayers?',
        'help_text' => 'Two in each of the last three conferences.',
        'config' => ['line' => 2.5],
    ];
    $defs[] = [
        'qkey' => 'women_speak', 'section' => 'words', 'type' => 'over_under', 'points' => 3,
        'prompt' => 'How many women will speak?',
        'help_text' => 'Three in each of the last three conferences.',
        'config' => ['line' => 3.5],
    ];
    $defs[] = [
        'qkey' => 'speakers_outside_us', 'section' => 'words', 'type' => 'over_under', 'points' => 3,
        'prompt' => 'How many speakers will be from outside the United States?',
        'help_text' => 'Going by where they were born — Church leader biographies list it.',
        'config' => ['line' => 10.5],
    ];

    // ---- 7. Participation (12 pts) ----
    // Worth more than any single guess on the sheet: watching is the point of
    // the game, and it's the one thing everybody can control. 'points' is the
    // most obtainable, so it has to stay per × max.
    $defs[] = [
        'qkey'      => 'sessions_watched',
        'section'   => 'watched',
        'type'      => 'watched',
        'points'    => 12,
        'prompt'    => 'How many sessions did you watch?',
        'help_text' => 'Three points per session watched. Filled in when we score the sheets.',
        'config'    => ['per' => 3, 'max' => 4],
    ];

    // ---- write ----
    foreach ($defs as $d) {
        $sessionId = isset($d['session']) ? $sessionIds[$d['session']] : null;
        exec_sql(
            'INSERT INTO questions
               (event_id, qkey, section, type, prompt, help_text, points, session_id, config, sort_order, active)
             VALUES (?,?,?,?,?,?,?,?,?,?,1)
             ON DUPLICATE KEY UPDATE
               section=VALUES(section), type=VALUES(type), prompt=VALUES(prompt),
               help_text=VALUES(help_text), points=VALUES(points), session_id=VALUES(session_id),
               config=VALUES(config), sort_order=VALUES(sort_order), active=1',
            [
                $eventId, $d['qkey'], $d['section'], $d['type'], $d['prompt'],
                $d['help_text'] ?? null, $d['points'], $sessionId,
                isset($d['config']) ? json_encode($d['config']) : null,
                $order++,
            ]
        );

        $qid = (int)q1('SELECT id FROM questions WHERE event_id=? AND qkey=?', [$eventId, $d['qkey']])['id'];

        if (!empty($d['options'])) {
            exec_sql('DELETE FROM question_options WHERE question_id = ?', [$qid]);
            foreach ($d['options'] as [$val, $lab, $ord]) {
                exec_sql(
                    'INSERT INTO question_options (question_id, value, label, sort_order) VALUES (?,?,?,?)',
                    [$qid, $val, $lab, $ord]
                );
            }
        }
    }

    $pdo->commit();
    return $eventId;
}
