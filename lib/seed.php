<?php
declare(strict_types=1);

/**
 * Question definitions for the April 2026 sheet.
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

const FGC_SESSIONS = [
    ['sat_am', 'Saturday Morning',   'Sat AM'],
    ['sat_pm', 'Saturday Afternoon', 'Sat PM'],
    ['sun_am', 'Sunday Morning',     'Sun AM'],
    ['sun_pm', 'Sunday Afternoon',   'Sun PM'],
];

function fgc_seed(string $slug = 'april-2026', string $name = 'April 2026 General Conference'): int
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

    $apostleOptions = [];
    $i = 0;
    foreach (FGC_APOSTLES as $v => $l) {
        $apostleOptions[] = [$v, $l, $i++];
    }

    $order = 0;
    $defs = [];

    // ---- 1. Apostle grid: one row per apostle, one session each (15 pts) ----
    foreach (FGC_APOSTLES as $value => $label) {
        $defs[] = [
            'qkey'    => 'apostle_' . $value,
            'section' => 'apostles',
            'type'    => 'pick_one',
            'prompt'  => $label,
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
    $defs[] = [
        'qkey' => 'tie_oaks', 'section' => 'colors', 'type' => 'text', 'points' => 3,
        'prompt' => 'What color tie will President Oaks wear when he speaks?',
    ];
    $defs[] = [
        'qkey' => 'tie_eyring', 'section' => 'colors', 'type' => 'text', 'points' => 3,
        'prompt' => 'What color tie will President Eyring wear when he speaks?',
    ];
    $defs[] = [
        'qkey' => 'tie_christofferson', 'section' => 'colors', 'type' => 'text', 'points' => 3,
        'prompt' => 'What color tie will President Christofferson wear when he speaks?',
    ];
    $defs[] = [
        'qkey' => 'choir_dress', 'section' => 'colors', 'type' => 'text', 'points' => 3,
        'prompt' => 'What color dress will the women in the Tabernacle Choir wear on Sunday morning?',
    ];

    // ---- 6. Counting questions (6 pts) ----
    $defs[] = [
        'qkey' => 'women_pray', 'section' => 'counts', 'type' => 'number', 'points' => 3,
        'prompt' => 'How many women will give prayers?',
        'config' => ['min' => 0, 'max' => 8],
    ];
    $defs[] = [
        'qkey' => 'women_speak', 'section' => 'counts', 'type' => 'number', 'points' => 3,
        'prompt' => 'How many women will speak?',
        'config' => ['min' => 0, 'max' => 12],
    ];
    $defs[] = [
        'qkey' => 'speakers_outside_us', 'section' => 'counts', 'type' => 'number', 'points' => 3,
        'prompt' => 'How many speakers will be from outside the United States?',
        'help_text' => 'Going by where they were born — Church leader biographies list it.',
        'config' => ['min' => 0, 'max' => 20],
    ];

    // ---- 7. Over/under word counts (15 pts) ----
    $words = [
        ['wc_jesus_christ',   'the phrase "Jesus Christ"',  175],
        ['wc_temple',         'the word "temple"',          100],
        ['wc_covenant',       'the word "covenant"',         75],
        ['wc_book_of_mormon', 'the phrase "Book of Mormon"', 50],
    ];
    foreach ($words as [$key, $what, $line]) {
        $defs[] = [
            'qkey'      => $key,
            'section'   => 'words',
            'type'      => 'over_under',
            'points'    => 3,
            'prompt'    => 'How many times will ' . $what . ' be said?',
            'help_text' => 'Counted across all talks using the published transcripts.',
            'config'    => ['line' => $line],
        ];
    }
    $defs[] = [
        'qkey'      => 'quoted_oaks',
        'section'   => 'words',
        'type'      => 'over_under',
        'points'    => 3,
        'prompt'    => 'How many times will President Oaks be quoted by another speaker?',
        'help_text' => 'A speaker quoting or citing President Oaks by name.',
        'config'    => ['line' => 8],
    ];

    // ---- 8. Participation (4 pts) ----
    $defs[] = [
        'qkey'      => 'sessions_watched',
        'section'   => 'watched',
        'type'      => 'watched',
        'points'    => 4,
        'prompt'    => 'How many sessions did you watch?',
        'help_text' => 'One point per session watched. Filled in when we score the sheets.',
        'config'    => ['per' => 1, 'max' => 4],
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
