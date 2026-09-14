<?php
declare(strict_types=1);
require_once __DIR__ . '/lib/bootstrap.php';
require_once APP_ROOT . '/lib/questions.php';
require_once APP_ROOT . '/lib/scoring.php';

// A conference still in draft with nobody in it isn't news to players — it's
// the scorekeeper setting up the next one. Keep it off the public list.
$events = array_values(array_filter(
    get_events(),
    static fn(array $e) => $e['status'] !== 'draft' || (int)$e['player_count'] > 0
));
if (!$events) {
    exit('No conferences set up yet.');
}
$live = get_event();
$newest = $live ? (int)$live['id'] : (int)$events[0]['id'];

page_head('Past conferences');
?>
<section class="hero compact">
  <h1>Every conference</h1>
  <p class="lede">Standings from this conference and the ones before it.</p>
</section>

<?php foreach ($events as $ev):
    $board   = event_has_standings($ev) ? leaderboard((int)$ev['id']) : [];
    $winner  = $board[0] ?? null;
    $isNow   = (int)$ev['id'] === $newest;
    // Only call somebody the winner once the scoring is actually done.
    $decided = $ev['status'] === 'final' && $winner && (int)$winner['total'] > 0; ?>

  <div class="card conf">
    <header class="section-head">
      <h2>
        <?= e($ev['name']) ?>
        <?php if ($isNow): ?><span class="pill pill-now">this one</span><?php endif; ?>
      </h2>
    </header>

    <?php if (!$board): ?>
      <p class="muted conf-meta">
        <?= $isNow ? 'No sheets in yet — be the first.' : 'Nobody played this one.' ?>
      </p>
    <?php else: ?>
      <p class="muted conf-meta">
        <?= count($board) ?> player<?= count($board) === 1 ? '' : 's' ?>
        <?php if ($decided): ?>
          &middot; won by <strong><?= e($winner['display_name']) ?></strong>
          with <?= (int)$winner['total'] ?>
        <?php elseif ($ev['status'] === 'draft'): ?>
          &middot; not open yet
        <?php else: ?>
          &middot; still being scored
        <?php endif; ?>
      </p>
      <div class="actions conf-actions">
        <a class="btn <?= $isNow ? 'btn-primary' : '' ?>"
           href="leaderboard.php?event=<?= e($ev['slug']) ?>">See the standings</a>
      </div>
    <?php endif; ?>
  </div>
<?php endforeach; ?>

<p class="center"><a class="link" href="index.php">&larr; Back</a></p>
<?php
page_foot();
