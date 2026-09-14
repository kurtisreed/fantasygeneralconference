<?php
require_once __DIR__ . '/_head.php';
[$admin, $event] = admin_guard();

$eventId = (int)$event['id'];
$questions = get_questions($eventId);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $pdo = db();
    $pdo->beginTransaction();

    $up = $pdo->prepare(
        'INSERT INTO results (question_id, value, numeric_value) VALUES (?,?,?)
         ON DUPLICATE KEY UPDATE value = VALUES(value), numeric_value = VALUES(numeric_value)'
    );
    $del = $pdo->prepare('DELETE FROM results WHERE question_id = ?');

    foreach ($questions as $qq) {
        if ($qq['type'] === 'watched') {
            continue;
        }
        $qid = (int)$qq['id'];
        $raw = $_POST['r'][$qid] ?? '';
        $raw = is_string($raw) ? trim($raw) : '';

        if ($raw === '') {
            $del->execute([$qid]);
            continue;
        }
        if ($qq['type'] === 'number' || $qq['type'] === 'over_under') {
            $up->execute([$qid, null, (float)$raw]);
        } else {
            $up->execute([$qid, mb_substr($raw, 0, 255), null]);
        }
    }
    $pdo->commit();

    $n = recompute_event_scores($eventId);
    flash("Results saved. Scores recomputed ($n scoring rows).");
    redirect('results.php');
}

admin_chrome('Results', 'results.php', $event);

$results = get_results($eventId);
$sections = group_by_section($questions);
$meta = section_meta();
?>
<section class="hero compact">
  <h1>Results</h1>
  <p class="lede">Leave anything you don&rsquo;t know yet blank. Saving recomputes every score.</p>
</section>

<form method="post">
<?= csrf_field() ?>
<?php foreach ($sections as $key => $qs):
    if ($key === 'watched') continue; ?>
  <section class="card">
    <header class="section-head"><h2><?= e($meta[$key]['title']) ?></h2></header>
    <?php foreach ($qs as $qq):
        $qid = (int)$qq['id'];
        $r = $results[$qid] ?? null;
        $cur = $r ? ($r['value'] ?? ($r['numeric_value'] !== null ? (string)(float)$r['numeric_value'] : '')) : '';
        $answeredBy = (int)q1('SELECT COUNT(*) c FROM answers WHERE question_id = ?', [$qid])['c']; ?>
      <div class="q q-admin">
        <label class="q-prompt" for="r<?= $qid ?>">
          <?= e($key === 'apostles' ? 'Which session did ' . $qq['prompt'] . ' speak in?' : $qq['prompt']) ?>
          <span class="q-pts"><?= (int)$qq['points'] ?> pt<?= $qq['points'] == 1 ? '' : 's' ?> · <?= $answeredBy ?> picked</span>
        </label>

        <?php if ($qq['type'] === 'pick_one'): ?>
          <select id="r<?= $qid ?>" name="r[<?= $qid ?>]">
            <option value="">— not yet —</option>
            <?php foreach ($qq['options'] as $o): ?>
              <option value="<?= e($o['value']) ?>" <?= $cur === $o['value'] ? 'selected' : '' ?>><?= e($o['label']) ?></option>
            <?php endforeach; ?>
          </select>

        <?php elseif ($qq['type'] === 'over_under'): ?>
          <div class="inline">
            <input type="number" step="1" min="0" id="r<?= $qid ?>" name="r[<?= $qid ?>]"
                   value="<?= e($cur) ?>" placeholder="actual count">
            <span class="muted">line is <strong><?= e((string)($qq['config']['line'] ?? '?')) ?></strong></span>
          </div>

        <?php elseif ($qq['type'] === 'number'): ?>
          <input type="number" step="1" min="0" id="r<?= $qid ?>" name="r[<?= $qid ?>]" value="<?= e($cur) ?>">

        <?php else: ?>
          <input type="text" id="r<?= $qid ?>" name="r[<?= $qid ?>]" maxlength="120"
                 value="<?= e($cur) ?>" placeholder="the official answer">
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  </section>
<?php endforeach; ?>

<div class="submit-bar">
  <span class="submit-count">Saving rescores everyone</span>
  <button type="submit" class="btn btn-primary">Save &amp; rescore</button>
</div>
</form>
<?php page_foot();
