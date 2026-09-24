<?php
declare(strict_types=1);
require_once __DIR__ . '/lib/bootstrap.php';
require_once APP_ROOT . '/lib/questions.php';
require_once APP_ROOT . '/lib/sheet.php';
require_once APP_ROOT . '/lib/scoring.php';

$event = get_event();
if (!$event) {
    exit('No event set up yet.');
}
$eventId = (int)$event['id'];
resume_remembered_player($eventId);

$playerId = (int)($_SESSION['player_id'] ?? 0);
$player = $playerId ? q1('SELECT * FROM players WHERE id = ? AND event_id = ?', [$playerId, $eventId]) : null;
if (!$player) {
    redirect('index.php');
}

$open = entries_open($event);
$questions = get_questions($eventId);
$possible = total_points_possible($questions);
$answers = get_answers($playerId);
$sessions = get_sessions($eventId);
$futureCodes = future_session_codes($event, $sessions);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    if (post('action') === 'watched') {
        // Self-reported, so it works any time — including after picks lock,
        // which is when the sessions actually happen.
        handle_watched_post($event, $sessions, $questions, $playerId, 'play.php');
    }

    if (!$open) {
        flash('Picks are locked — nothing was changed.');
        redirect('play.php');
    }

    save_sheet($playerId, $questions, $_POST['q'] ?? []);

    flash('Picks saved.');
    redirect('submitted.php');
}

page_head('Your sheet');
?>
<figure class="hero-art slim">
  <img src="<?= e(asset_url('assets/img/shepherd.webp')) ?>" alt="" width="1344" height="756">
</figure>

<section class="hero compact">
  <p class="eyebrow"><?= e($event['name']) ?></p>
  <h1><?= e($player['display_name']) ?></h1>
  <p class="lede">
    <?= $possible ?> points possible. Your code is
    <strong class="code"><?= e($player['entry_code']) ?></strong> &mdash; write it down.
  </p>
  <?php if (!$open): ?>
    <p class="notice">Picks are locked. You can look, but you can&rsquo;t change anything.</p>
  <?php endif; ?>
</section>

<?php render_watched_card($player, $sessions, $questions, $futureCodes); ?>

<form method="post" id="sheet" <?= $open ? '' : 'class="locked"' ?>>
<?= csrf_field() ?>

<?php render_sheet_sections($questions, $answers, $open, (int)$player['sessions_watched'], true); // hide: covered by the card above ?>

<?php if ($open): ?>
  <div class="submit-bar">
    <span class="submit-count"><span id="answered">0</span> of <span id="total">0</span> answered</span>
    <button type="submit" class="btn btn-primary">Save my picks</button>
  </div>
<?php else: ?>
  <p class="center"><a class="link" href="leaderboard.php">See the standings &rarr;</a></p>
<?php endif; ?>
</form>

<?php render_watched_autosave_script(); ?>
<script src="<?= e(asset_url('assets/app.js')) ?>"></script>
<?php
page_foot();
