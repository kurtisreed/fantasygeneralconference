<?php
declare(strict_types=1);
require_once __DIR__ . '/lib/bootstrap.php';
require_once APP_ROOT . '/lib/questions.php';
require_once APP_ROOT . '/lib/scoring.php';

// ?event=<slug> shows a past conference; without it, the current one.
$slug  = isset($_GET['event']) ? trim((string)$_GET['event']) : null;
$event = get_event($slug !== '' ? $slug : null);
if (!$event) {
    exit('No conference found.');
}
$eventId    = (int)$event['id'];
$isArchive  = $slug !== null && $slug !== '';
$manyEvents = event_count() > 1;
$questions = get_questions($eventId);
$byId = [];
foreach ($questions as $qq) {
    $byId[(int)$qq['id']] = $qq;
}
$results = get_results($eventId);
$possible = total_points_possible($questions);
$board = leaderboard($eventId);

$scoredPoints = 0;
foreach ($questions as $qq) {
    if ($qq['type'] === 'watched' || isset($results[(int)$qq['id']])) {
        $scoredPoints += (int)$qq['points'];
    }
}

$detailId = query_int('player');
$detail = null;
if ($detailId) {
    $detail = q1('SELECT * FROM players WHERE id = ? AND event_id = ?', [$detailId, $eventId]);
}

// Keep the conference on every link from this page, or "close" would jump back
// to the current standings from a past one.
$self = 'leaderboard.php' . ($isArchive ? '?event=' . rawurlencode((string)$slug) : '');
$selfQ = 'leaderboard.php?' . ($isArchive ? 'event=' . rawurlencode((string)$slug) . '&' : '');

$pct = $possible > 0 ? (int)round($scoredPoints / $possible * 100) : 0;

page_head('Standings');
?>
<figure class="hero-art slim">
  <img src="<?= e(asset_url('assets/img/shepherd.webp')) ?>" alt="" width="1344" height="756">
</figure>

<section class="hero hero-titled compact">
  <p class="eyebrow">Standings</p>
  <h1><?= e($event['name']) ?></h1>

  <div class="scored">
    <div class="scored-bar" role="img"
         aria-label="<?= $pct ?>% of the sheet scored">
      <span style="width: <?= $pct ?>%"></span>
    </div>
    <p class="scored-note">
      <strong><?= $scoredPoints ?></strong> of <?= $possible ?> points scored
      <?= $event['status'] === 'final' ? 'in total' : 'so far' ?>
    </p>
  </div>

  <?php if ($manyEvents): ?>
    <p class="center"><a class="link" href="conferences.php">All conferences &rarr;</a></p>
  <?php endif; ?>
</section>

<?php if (!$board): ?>
  <div class="card center">
    <p class="muted">No sheets turned in yet.</p>
    <a class="btn btn-primary" href="index.php">Make your picks</a>
  </div>
<?php else: ?>
  <?php
  $rank = 0; $lastTotal = null; $shown = 0;
  // No medals until somebody has actually scored — the participation point for
  // sessions watched makes the sheet look "scored" before conference starts.
  $anyPoints = (int)$board[0]['total'] > 0;
  ?>
  <ol class="board">
    <?php foreach ($board as $row):
        $shown++;
        if ($lastTotal === null || (int)$row['total'] !== (int)$lastTotal) {
            $rank = $shown;
        }
        $lastTotal = (int)$row['total'];
        // Only dress the top three once there is something to be on top of.
        $medal = ($anyPoints && $rank <= 3 && (int)$row['total'] > 0)
            ? ' medal medal-' . $rank : ''; ?>
      <li class="board-row<?= $rank === 1 ? ' leader' : '' ?><?= $medal ?>">
        <span class="rank"><?= $rank ?></span>
        <a class="board-name" href="<?= e($selfQ) ?>player=<?= (int)$row['id'] ?>"><?= e($row['display_name']) ?></a>
        <span class="board-pts"><?= (int)$row['total'] ?></span>
      </li>
    <?php endforeach; ?>
  </ol>
<?php endif; ?>

<?php if ($detail):
    $answers = get_answers((int)$detail['id']);
    $sections = group_by_section($questions); ?>
  <section class="card">
    <header class="section-head">
      <h2><?= e($detail['display_name']) ?></h2>
      <a class="link" href="<?= e($self) ?>">close</a>
    </header>
    <?php foreach ($sections as $key => $qs): ?>
      <h3 class="detail-head"><?= e(section_info($key)['title']) ?></h3>
      <table class="detail">
        <?php foreach ($qs as $qq):
            $qid = (int)$qq['id'];
            [$pts, $status] = score_answer(
                $qq, $answers[$qid] ?? null, $results[$qid] ?? null,
                (int)$detail['sessions_watched']
            );
            $guess = $qq['type'] === 'watched'
                ? (int)$detail['sessions_watched'] . ' session' . ($detail['sessions_watched'] == 1 ? '' : 's')
                : display_value($qq, $answers[$qid] ?? null);
            $truth = $qq['type'] === 'watched'
                ? '—'
                : display_result($qq, $results[$qid] ?? null); ?>
          <tr class="st-<?= e($status) ?>">
            <td class="d-prompt"><?= e($qq['prompt']) ?></td>
            <td class="d-guess"><?= e($guess) ?></td>
            <td class="d-truth"><?= e($truth) ?></td>
            <td class="d-pts"><?= $status === 'pending' ? '·' : ($pts > 0 ? '+' . $pts : '0') ?></td>
          </tr>
        <?php endforeach; ?>
      </table>
    <?php endforeach; ?>
  </section>
<?php endif; ?>

<p class="center"><a class="link" href="index.php">&larr; Back</a></p>
<?php
page_foot();
