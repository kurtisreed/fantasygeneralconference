<?php
require_once __DIR__ . '/_head.php';
[$admin, $event] = admin_page('Text answers', 'adjudicate.php');

$eventId = (int)$event['id'];
$questions = array_filter(get_questions($eventId), static fn($q) => $q['type'] === 'text');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $pdo = db();
    $pdo->beginTransaction();
    $up = $pdo->prepare(
        'INSERT INTO text_rulings (question_id, normalized, accepted) VALUES (?,?,?)
         ON DUPLICATE KEY UPDATE accepted = VALUES(accepted)'
    );
    foreach (($_POST['rule'] ?? []) as $qid => $variants) {
        foreach ((array)$variants as $normB64 => $accept) {
            $norm = base64_decode((string)$normB64, true);
            if ($norm === false) {
                continue;
            }
            $up->execute([(int)$qid, $norm, $accept === '1' ? 1 : 0]);
        }
    }
    $pdo->commit();
    $n = recompute_event_scores($eventId);
    flash("Rulings saved. Scores recomputed ($n scoring rows).");
    redirect('adjudicate.php');
}

$results = get_results($eventId);
?>
<section class="hero compact">
  <h1>Text answers</h1>
  <p class="lede">
    Every distinct spelling players typed, grouped. Mark the ones that count.
    Rule once per spelling &mdash; everyone who typed it gets the same call.
  </p>
</section>

<form method="post">
<?= csrf_field() ?>
<?php foreach ($questions as $qq):
    $qid = (int)$qq['id'];
    $rows = q(
        'SELECT a.value, p.display_name FROM answers a
           JOIN players p ON p.id = a.player_id
          WHERE a.question_id = ? ORDER BY a.value',
        [$qid]
    );

    $groups = [];
    foreach ($rows as $r) {
        $n = normalize_text($r['value']);
        $groups[$n]['display'] = $groups[$n]['display'] ?? $r['value'];
        $groups[$n]['who'][] = $r['display_name'];
    }
    ksort($groups);

    $ruled = [];
    foreach (q('SELECT normalized, accepted FROM text_rulings WHERE question_id = ?', [$qid]) as $r) {
        $ruled[$r['normalized']] = (int)$r['accepted'];
    }
    $official = $results[$qid]['value'] ?? ''; ?>

  <section class="card">
    <header class="section-head">
      <h2><?= e($qq['prompt']) ?></h2>
      <span class="pill"><?= (int)$qq['points'] ?> pts</span>
    </header>
    <p class="muted">
      Official answer: <strong><?= $official !== '' ? e($official) : 'not entered yet' ?></strong>
      &mdash; set it on <a href="results.php">Results</a>.
    </p>

    <?php if (!$groups): ?>
      <p class="muted">Nobody answered this one.</p>
    <?php else: ?>
      <table class="rule-table">
        <?php foreach ($groups as $norm => $g):
            $b64 = base64_encode($norm);
            $state = $ruled[$norm] ?? null;
            if ($state === null && $official !== '') {
                $state = $norm === normalize_text($official) ? 1 : 0;
            } ?>
          <tr>
            <td class="rule-val">
              <span class="variant"><?= e($g['display']) ?></span>
              <span class="who"><?= e(implode(', ', $g['who'])) ?></span>
            </td>
            <td class="rule-buttons">
              <label class="chip chip-yes">
                <input type="radio" name="rule[<?= $qid ?>][<?= e($b64) ?>]" value="1" <?= $state === 1 ? 'checked' : '' ?>>
                <span>Counts</span>
              </label>
              <label class="chip chip-no">
                <input type="radio" name="rule[<?= $qid ?>][<?= e($b64) ?>]" value="0" <?= $state === 0 ? 'checked' : '' ?>>
                <span>No</span>
              </label>
            </td>
          </tr>
        <?php endforeach; ?>
      </table>
    <?php endif; ?>
  </section>
<?php endforeach; ?>

<div class="submit-bar">
  <span class="submit-count">Saving rescores everyone</span>
  <button type="submit" class="btn btn-primary">Save rulings</button>
</div>
</form>
<?php page_foot();
