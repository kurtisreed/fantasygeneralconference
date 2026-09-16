<?php
declare(strict_types=1);

require_once __DIR__ . '/questions.php';

/**
 * Score one answer against the recorded result.
 *
 * Returns [points, status] where status is one of:
 *   correct | wrong | push | pending | blank
 */
function score_answer(array $question, ?string $answer, ?array $result, int $watched = 0): array
{
    $pts = (int)$question['points'];

    // Sessions watched is self-reported, not guessed — it has no "result" row.
    if ($question['type'] === 'watched') {
        $per = (int)($question['config']['per'] ?? 1);
        $max = (int)($question['config']['max'] ?? 4);
        $n = max(0, min($watched, $max));
        return [$n * $per, $n > 0 ? 'correct' : 'blank'];
    }

    if ($answer === null || $answer === '') {
        return [0, 'blank'];
    }
    if ($result === null) {
        return [0, 'pending'];
    }

    switch ($question['type']) {
        case 'pick_one':
            if (($result['value'] ?? null) === null || $result['value'] === '') {
                return [0, 'pending'];
            }
            // A speaker can talk in more than one session, so the apostles
            // section's result may list several accepted sessions — any one
            // of them earns full points, same as a single-answer question.
            $accepted = explode(',', $result['value']);
            return in_array($answer, $accepted, true) ? [$pts, 'correct'] : [0, 'wrong'];

        case 'number':
            if ($result['numeric_value'] === null) {
                return [0, 'pending'];
            }
            $actual = (float)$result['numeric_value'];
            $guess  = (float)$answer;
            $tol    = (float)($question['config']['tolerance'] ?? 0);
            if (abs($guess - $actual) <= $tol) {
                return [$pts, 'correct'];
            }
            return [0, 'wrong'];

        case 'over_under':
            if ($result['numeric_value'] === null) {
                return [0, 'pending'];
            }
            $actual = (float)$result['numeric_value'];
            $line   = (float)($question['config']['line'] ?? 0);
            if ($actual === $line) {
                return [0, 'push'];   // exact tie: nobody scores
            }
            $winner = $actual > $line ? 'over' : 'under';
            return $answer === $winner ? [$pts, 'correct'] : [0, 'wrong'];

        case 'text':
            // Kept for any free-text question added later. Matches on the
            // normalized text, so there is nothing for anyone to adjudicate.
            if (($result['value'] ?? '') === '') {
                return [0, 'pending'];
            }
            return normalize_text($answer) === normalize_text((string)$result['value'])
                ? [$pts, 'correct']
                : [0, 'wrong'];
    }

    return [0, 'pending'];
}

/** Recompute and store every score for an event. Returns rows written. */
function recompute_event_scores(int $eventId): int
{
    $questions = get_questions($eventId);
    $results   = get_results($eventId);
    $players   = q('SELECT id, sessions_watched FROM players WHERE event_id = ?', [$eventId]);

    $pdo = db();
    $pdo->beginTransaction();

    exec_sql(
        'DELETE s FROM scores s JOIN players p ON p.id = s.player_id WHERE p.event_id = ?',
        [$eventId]
    );

    $ins = $pdo->prepare('INSERT INTO scores (player_id, question_id, points) VALUES (?,?,?)');
    $written = 0;

    foreach ($players as $p) {
        $pid = (int)$p['id'];
        $answers = get_answers($pid);
        foreach ($questions as $qq) {
            $qid = (int)$qq['id'];
            [$pts] = score_answer(
                $qq,
                $answers[$qid] ?? null,
                $results[$qid] ?? null,
                (int)$p['sessions_watched']
            );
            if ($pts !== 0) {
                $ins->execute([$pid, $qid, $pts]);
                $written++;
            }
        }
    }

    $pdo->commit();
    return $written;
}

/** Standings, highest first. */
function leaderboard(int $eventId): array
{
    return q(
        'SELECT p.id, p.display_name, p.sessions_watched, p.submitted_at,
                COALESCE(SUM(s.points), 0) AS total
           FROM players p
           LEFT JOIN scores s ON s.player_id = p.id
          WHERE p.event_id = ? AND p.submitted_at IS NOT NULL
          GROUP BY p.id, p.display_name, p.sessions_watched, p.submitted_at
          ORDER BY total DESC, p.display_name ASC',
        [$eventId]
    );
}

/** Per-section point totals for one player. */
function player_section_totals(int $playerId): array
{
    $out = [];
    $rows = q(
        'SELECT q.section, SUM(s.points) AS pts
           FROM scores s JOIN questions q ON q.id = s.question_id
          WHERE s.player_id = ?
          GROUP BY q.section',
        [$playerId]
    );
    foreach ($rows as $r) {
        $out[$r['section']] = (int)$r['pts'];
    }
    return $out;
}
