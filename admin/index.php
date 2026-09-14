<?php
require_once __DIR__ . '/_head.php';
[$admin, $event] = admin_guard();

$eventId = (int)$event['id'];
$questions = get_questions($eventId);
$results = get_results($eventId);

$askable = array_filter($questions, static fn($q) => $q['type'] !== 'watched');
$entered = 0;
foreach ($askable as $qq) {
    if (isset($results[(int)$qq['id']])) {
        $entered++;
    }
}
$players = (int)q1('SELECT COUNT(*) c FROM players WHERE event_id=? AND submitted_at IS NOT NULL', [$eventId])['c'];
// Count in PHP so this matches normalize_text() exactly — SQL's LOWER(TRIM())
// normalizes less than we do and would over-report.
$ruled = [];
foreach (q('SELECT tr.question_id, tr.normalized FROM text_rulings tr
             JOIN questions q ON q.id = tr.question_id WHERE q.event_id = ?', [$eventId]) as $r) {
    $ruled[(int)$r['question_id']][$r['normalized']] = true;
}
$pending = 0;
foreach ($questions as $qq) {
    if ($qq['type'] !== 'text') {
        continue;
    }
    $qid = (int)$qq['id'];
    foreach (q('SELECT value FROM answers WHERE question_id = ?', [$qid]) as $a) {
        if (!isset($ruled[$qid][normalize_text($a['value'])])) {
            $pending++;
            break;
        }
    }
}
admin_chrome('Dashboard', 'index.php');
?>
<section class="hero compact">
  <p class="eyebrow"><?= e($event['name']) ?> · <span class="status status-<?= e($event['status']) ?>"><?= e($event['status']) ?></span></p>
  <h1>Scorekeeper</h1>
</section>

<div class="stats">
  <div class="stat"><span class="stat-n"><?= $players ?></span><span class="stat-l">sheets in</span></div>
  <div class="stat"><span class="stat-n"><?= $entered ?>/<?= count($askable) ?></span><span class="stat-l">results entered</span></div>
  <div class="stat"><span class="stat-n"><?= total_points_possible($questions) ?></span><span class="stat-l">points possible</span></div>
</div>

<div class="card">
  <h2>Run of show</h2>
  <ol class="runbook">
    <li><strong>Before conference</strong> — set the over/under lines on <a href="questions.php">Questions</a>, then flip the event to <em>open</em> on <a href="event.php">Event</a> and share the link.</li>
    <li><strong>Saturday morning</strong> — flip to <em>locked</em> (or set a lock time and let it happen on its own).</li>
    <li><strong>After each session</strong> — fill in what you know on <a href="results.php">Results</a>. The standings update the moment you save.</li>
    <li><strong>Colors and other typed answers</strong> — rule on the spellings under <a href="adjudicate.php">Text answers</a>.</li>
    <li><strong>When you score sheets together</strong> — enter sessions watched on <a href="players.php">Players</a>.</li>
  </ol>
</div>

<div class="actions">
  <a class="btn btn-primary" href="results.php">Enter results</a>
  <?php if ($pending): ?><a class="btn" href="adjudicate.php"><?= $pending ?> text question<?= $pending == 1 ? '' : 's' ?> to rule on</a><?php endif; ?>
  <a class="btn" href="../leaderboard.php">View standings</a>
</div>
<?php page_foot();
