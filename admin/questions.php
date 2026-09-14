<?php
require_once __DIR__ . '/_head.php';
require_once APP_ROOT . '/lib/seed.php';
[$admin, $event] = admin_page('Questions', 'questions.php');
$eventId = (int)$event['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    if (post('action') === 'reseed') {
        fgc_seed($event['slug'], $event['name']);
        recompute_event_scores($eventId);
        flash('Sheet reset to the built-in April 2026 defaults. Player picks were not touched.');
        redirect('questions.php');
    }

    $pdo = db();
    $pdo->beginTransaction();
    foreach (get_questions($eventId, true) as $qq) {
        $qid = (int)$qq['id'];
        $pts = isset($_POST['points'][$qid]) ? max(0, (int)$_POST['points'][$qid]) : (int)$qq['points'];
        $cfg = $qq['config'];
        if ($qq['type'] === 'over_under' && isset($_POST['line'][$qid]) && $_POST['line'][$qid] !== '') {
            $cfg['line'] = (float)$_POST['line'][$qid];
        }
        $active = isset($_POST['active'][$qid]) ? 1 : 0;
        exec_sql(
            'UPDATE questions SET points = ?, config = ?, active = ? WHERE id = ? AND event_id = ?',
            [$pts, json_encode($cfg), $active, $qid, $eventId]
        );
    }
    $pdo->commit();
    recompute_event_scores($eventId);
    flash('Questions updated and scores recomputed.');
    redirect('questions.php');
}

$questions = get_questions($eventId, true);
$sections = group_by_section($questions);
$meta = section_meta();
$locked = $event['status'] !== 'draft' && $event['status'] !== 'open';
?>
<section class="hero compact">
  <h1>Questions</h1>
  <p class="lede">
    Tune point values and over/under lines. Total right now:
    <strong><?= total_points_possible(array_filter($questions, static fn($q) => (int)$q['active'] === 1)) ?> points</strong>.
  </p>
  <?php if ($locked): ?>
    <p class="notice">Picks are already locked. Changing points now will reshuffle the standings.</p>
  <?php endif; ?>
</section>

<div class="card">
  <h2>Setting the over/under lines</h2>
  <p class="muted">
    Best way to pick a line: run a word count over the previous conference&rsquo;s talks
    on the Church&rsquo;s site, then set the line a little above or below that number so
    the guess is genuinely 50/50. The defaults below are placeholders &mdash; change them
    before you open the sheet.
  </p>
</div>

<form method="post">
<?= csrf_field() ?>
<?php foreach ($sections as $key => $qs): ?>
  <section class="card">
    <header class="section-head">
      <h2><?= e($meta[$key]['title']) ?></h2>
      <span class="pill"><?= total_points_possible(array_filter($qs, static fn($q) => (int)$q['active'] === 1)) ?> pts</span>
    </header>
    <table class="qtable">
      <thead><tr><th>Question</th><th>Line</th><th>Points</th><th>On</th></tr></thead>
      <tbody>
      <?php foreach ($qs as $qq): $qid = (int)$qq['id']; ?>
        <tr>
          <td><?= e($qq['prompt']) ?></td>
          <td>
            <?php if ($qq['type'] === 'over_under'): ?>
              <input type="number" step="1" min="0" class="mini" name="line[<?= $qid ?>]"
                     value="<?= e((string)($qq['config']['line'] ?? '')) ?>">
            <?php else: ?><span class="muted">—</span><?php endif; ?>
          </td>
          <td><input type="number" min="0" max="50" class="mini" name="points[<?= $qid ?>]" value="<?= (int)$qq['points'] ?>"></td>
          <td><input type="checkbox" name="active[<?= $qid ?>]" value="1" <?= (int)$qq['active'] ? 'checked' : '' ?>></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </section>
<?php endforeach; ?>

<div class="submit-bar">
  <span class="submit-count">Saving rescores everyone</span>
  <button type="submit" class="btn btn-primary">Save questions</button>
</div>
</form>

<div class="card danger-zone">
  <h2>Reset the sheet</h2>
  <p class="muted">Restores every prompt, point value and line to the built-in April 2026 defaults. Player picks and results are kept.</p>
  <form method="post" onsubmit="return confirm('Reset all questions to defaults?');">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="reseed">
    <button type="submit" class="btn btn-danger">Reset to defaults</button>
  </form>
</div>
<?php page_foot();
