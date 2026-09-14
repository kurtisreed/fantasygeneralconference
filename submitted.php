<?php
declare(strict_types=1);
require_once __DIR__ . '/lib/bootstrap.php';
require_once APP_ROOT . '/lib/questions.php';

$event = get_event();
$playerId = (int)($_SESSION['player_id'] ?? 0);
$player = $playerId ? q1('SELECT * FROM players WHERE id = ?', [$playerId]) : null;
if (!$event || !$player) {
    redirect('index.php');
}

$questions = get_questions((int)$event['id']);
$answers = get_answers($playerId);
$answered = 0;
foreach ($questions as $qq) {
    if ($qq['type'] !== 'watched' && !empty($answers[(int)$qq['id']])) {
        $answered++;
    }
}
$askable = count(array_filter($questions, static fn($q) => $q['type'] !== 'watched'));
$open = entries_open($event);

page_head('Saved');
?>
<section class="hero compact">
  <h1>You&rsquo;re in.</h1>
  <p class="lede">
    <?= $answered ?> of <?= $askable ?> questions answered.
  </p>
</section>

<div class="card center">
  <p class="muted">Your entry code</p>
  <p class="code code-big"><?= e($player['entry_code']) ?></p>
  <p class="muted">
    Screenshot this. You&rsquo;ll need it to open your sheet
    <?= $open ? 'again before picks lock.' : 'later.' ?>
  </p>
</div>

<div class="actions center">
  <?php if ($open): ?><a class="btn" href="play.php">Change my picks</a><?php endif; ?>
  <a class="btn btn-primary" href="leaderboard.php">Standings</a>
</div>
<?php
page_foot();
