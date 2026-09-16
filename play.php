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
$watchedMax = watched_max($questions);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    if (post('action') === 'watched') {
        // Self-reported, so it works any time — including after picks lock,
        // which is when the sessions actually happen.
        $validCodes = array_column($sessions, 'code');
        $checked = array_values(array_intersect((array)($_POST['watched'] ?? []), $validCodes));
        $n = min($watchedMax, count($checked));
        exec_sql(
            'UPDATE players SET sessions_watched = ?, watched_sessions = ? WHERE id = ?',
            [$n, $checked ? implode(',', $checked) : null, $playerId]
        );
        recompute_event_scores($eventId);

        // The card auto-saves via fetch on every checkbox toggle — no page
        // reload, so no flash message and nothing to redirect to.
        if (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'fetch') {
            http_response_code(204);
            exit;
        }

        flash('Sessions watched updated.');
        redirect('play.php');
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

<div class="card section" id="sec-watched-report">
  <header class="section-head">
    <h2>Sessions watched</h2>
    <span class="pill"><?= watched_per($questions) ?> pts each</span>
  </header>
  <p class="muted">Return here to check off each session as you watch it!</p>
  <form method="post" class="stack" id="watched-form">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="watched">
    <?php $watchedCodes = array_filter(explode(',', (string)($player['watched_sessions'] ?? ''))); ?>
    <div class="choices">
      <?php foreach ($sessions as $s): ?>
        <label class="chip">
          <input type="checkbox" name="watched[]" value="<?= e($s['code']) ?>"
                 <?= in_array($s['code'], $watchedCodes, true) ? 'checked' : '' ?>>
          <span><?= e($s['short_name']) ?></span>
        </label>
      <?php endforeach; ?>
    </div>
    <p class="help" id="watched-status" aria-live="polite">&nbsp;</p>
  </form>
</div>

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

<script>
(function () {
  'use strict';
  var form = document.getElementById('watched-form');
  var status = document.getElementById('watched-status');
  if (!form || !status) return;

  form.querySelectorAll('input[type="checkbox"]').forEach(function (box) {
    box.addEventListener('change', function () {
      status.textContent = 'Saving…';
      fetch(location.href, {
        method: 'POST',
        body: new FormData(form),
        headers: { 'X-Requested-With': 'fetch' },
        credentials: 'same-origin'
      }).then(function (r) {
        status.textContent = r.ok ? 'Saved.' : 'Could not save — try again.';
      }).catch(function () {
        status.textContent = 'Could not save — try again.';
      });
    });
  });
})();
</script>
<script src="<?= e(asset_url('assets/app.js')) ?>"></script>
<?php
page_foot();
