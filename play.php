<?php
declare(strict_types=1);
require_once __DIR__ . '/lib/bootstrap.php';
require_once APP_ROOT . '/lib/questions.php';

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
$sections = group_by_section($questions);
$possible = total_points_possible($questions);
$answers = get_answers($playerId);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    if (!$open) {
        flash('Picks are locked — nothing was changed.');
        redirect('play.php');
    }

    $pdo = db();
    $pdo->beginTransaction();
    $st = $pdo->prepare(
        'INSERT INTO answers (player_id, question_id, value) VALUES (?,?,?)
         ON DUPLICATE KEY UPDATE value = VALUES(value)'
    );
    $del = $pdo->prepare('DELETE FROM answers WHERE player_id = ? AND question_id = ?');

    foreach ($questions as $qq) {
        if ($qq['type'] === 'watched') {
            continue; // filled in after conference by the scorekeeper
        }
        $qid = (int)$qq['id'];
        $raw = $_POST['q'][$qid] ?? '';
        $val = is_string($raw) ? trim($raw) : '';

        if ($val === '') {
            $del->execute([$playerId, $qid]);
            continue;
        }
        if ($qq['type'] === 'number') {
            $val = (string)max(0, (int)$val);
        }
        $st->execute([$playerId, $qid, mb_substr($val, 0, 255)]);
    }

    exec_sql('UPDATE players SET submitted_at = NOW() WHERE id = ?', [$playerId]);
    $pdo->commit();

    flash('Picks saved.');
    redirect('submitted.php');
}

$meta = section_meta();
page_head('Your sheet');
?>
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

<form method="post" id="sheet" <?= $open ? '' : 'class="locked"' ?>>
<?= csrf_field() ?>

<?php foreach ($sections as $key => $qs):
    $m = $meta[$key];
    $secPts = total_points_possible($qs); ?>
  <section class="card section" id="sec-<?= e($key) ?>">
    <header class="section-head">
      <h2><?= e($m['title']) ?></h2>
      <span class="pill"><?= $secPts ?> pts</span>
    </header>
    <p class="muted"><?= e($m['blurb']) ?></p>

    <?php if ($key === 'apostles'): ?>
      <div class="grid-head">
        <span></span>
        <?php foreach ($qs[0]['options'] as $o): ?>
          <span class="grid-col"><?= e($o['label']) ?></span>
        <?php endforeach; ?>
        <span></span><?php // matches the clear button's column ?>
      </div>
      <?php foreach ($qs as $qq):
          $qid = (int)$qq['id'];
          $cur = $answers[$qid] ?? ''; ?>
        <div class="grid-row">
          <span class="grid-name"><?= e($qq['prompt']) ?></span>
          <div class="grid-choices" role="radiogroup" aria-label="<?= e($qq['prompt']) ?>">
            <?php foreach ($qq['options'] as $o): ?>
              <label class="chip">
                <input type="radio" name="q[<?= $qid ?>]" value="<?= e($o['value']) ?>"
                       <?= $cur === $o['value'] ? 'checked' : '' ?> <?= $open ? '' : 'disabled' ?>>
                <span><?= e($o['label']) ?></span>
              </label>
            <?php endforeach; ?>
            <button type="button" class="clear-row" data-q="<?= $qid ?>"
                    <?= $open ? '' : 'disabled' ?> title="Clear this row">&times;</button>
          </div>
        </div>
      <?php endforeach; ?>

    <?php else: ?>
      <?php foreach ($qs as $qq):
          $qid = (int)$qq['id'];
          $cur = $answers[$qid] ?? '';
          $isWatched = $qq['type'] === 'watched'; ?>
        <div class="q">
          <label class="q-prompt" for="q<?= $qid ?>">
            <?= e($qq['prompt']) ?>
            <span class="q-pts"><?= (int)$qq['points'] ?> pt<?= $qq['points'] == 1 ? '' : 's' ?></span>
          </label>
          <?php if (!empty($qq['help_text'])): ?>
            <p class="help"><?= e($qq['help_text']) ?></p>
          <?php endif; ?>

          <?php if ($isWatched): ?>
            <p class="help locked-note">
              Currently recorded: <strong><?= (int)$player['sessions_watched'] ?></strong>.
              The scorekeeper fills this in when we score the sheets.
            </p>

          <?php elseif ($qq['type'] === 'pick_one'): ?>
            <?php if (count($qq['options']) > 6): // long name lists scroll forever as pills ?>
              <select id="q<?= $qid ?>" name="q[<?= $qid ?>]" <?= $open ? '' : 'disabled' ?>>
                <option value="">— pick one —</option>
                <?php foreach ($qq['options'] as $o): ?>
                  <option value="<?= e($o['value']) ?>" <?= $cur === $o['value'] ? 'selected' : '' ?>><?= e($o['label']) ?></option>
                <?php endforeach; ?>
              </select>
            <?php else: ?>
              <div class="choices <?= count($qq['options']) > 3 ? 'choices-wide' : '' ?>">
                <?php foreach ($qq['options'] as $o): ?>
                  <label class="chip">
                    <input type="radio" name="q[<?= $qid ?>]" value="<?= e($o['value']) ?>"
                           <?= $cur === $o['value'] ? 'checked' : '' ?> <?= $open ? '' : 'disabled' ?>>
                    <span><?= e($o['label']) ?></span>
                  </label>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>

          <?php elseif ($qq['type'] === 'over_under'): ?>
            <div class="choices ou">
              <?php foreach ([['under', 'Under'], ['over', 'Over']] as [$v, $lab]): ?>
                <label class="chip chip-ou">
                  <input type="radio" name="q[<?= $qid ?>]" value="<?= $v ?>"
                         <?= $cur === $v ? 'checked' : '' ?> <?= $open ? '' : 'disabled' ?>>
                  <span><?= $lab ?> <strong><?= e((string)($qq['config']['line'] ?? '?')) ?></strong></span>
                </label>
              <?php endforeach; ?>
            </div>

          <?php elseif ($qq['type'] === 'number'): ?>
            <input type="number" id="q<?= $qid ?>" name="q[<?= $qid ?>]"
                   min="<?= (int)($qq['config']['min'] ?? 0) ?>"
                   max="<?= (int)($qq['config']['max'] ?? 99) ?>"
                   inputmode="numeric" value="<?= e($cur) ?>" <?= $open ? '' : 'disabled' ?>>

          <?php else: ?>
            <input type="text" id="q<?= $qid ?>" name="q[<?= $qid ?>]" maxlength="60"
                   placeholder="e.g. navy blue" value="<?= e($cur) ?>" <?= $open ? '' : 'disabled' ?>>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </section>
<?php endforeach; ?>

<?php if ($open): ?>
  <div class="submit-bar">
    <span class="submit-count"><span id="answered">0</span> of <span id="total">0</span> answered</span>
    <button type="submit" class="btn btn-primary">Save my picks</button>
  </div>
<?php else: ?>
  <p class="center"><a class="link" href="leaderboard.php">See the standings &rarr;</a></p>
<?php endif; ?>
</form>

<script src="<?= e(asset_url('assets/app.js')) ?>"></script>
<?php
page_foot();
