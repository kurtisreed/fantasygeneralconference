<?php
require_once __DIR__ . '/_head.php';
[$admin, $event] = admin_guard();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $picked = get_event_by_id((int)post('event_id'));
    if ($picked) {
        $_SESSION['admin_event_id'] = (int)$picked['id'];
        flash('Now working on ' . $picked['name'] . '.');
    }
    redirect('index.php');
}

$events  = get_events();
$current = (int)$event['id'];
$newest  = get_event();

admin_chrome('Conferences', '');
?>
<section class="hero compact">
  <h1>Conferences</h1>
  <p class="lede">
    Pick the one you want to work on. Everything else in the admin &mdash;
    results, players, questions &mdash; then applies to it.
  </p>
</section>

<?php foreach ($events as $ev):
    $isCurrent = (int)$ev['id'] === $current;
    $isNewest  = $newest && (int)$newest['id'] === (int)$ev['id'];
    $questions = (int)$ev['question_count'];
    $results   = (int)$ev['result_count']; ?>
  <div class="card conf <?= $isCurrent ? 'conf-on' : '' ?>">
    <header class="section-head">
      <h2>
        <?= e($ev['name']) ?>
        <?php if ($isNewest): ?><span class="pill pill-now">current</span><?php endif; ?>
      </h2>
      <span class="status status-<?= e($ev['status']) ?>"><?= e($ev['status']) ?></span>
    </header>

    <p class="muted conf-meta">
      <?= (int)$ev['player_count'] ?> sheet<?= (int)$ev['player_count'] === 1 ? '' : 's' ?>
      &middot; <?= $results ?>/<?= $questions ?> results entered
      <?php if (!empty($ev['lock_at'])): ?>
        &middot; picks lock<?= strtotime((string)$ev['lock_at']) < time() ? 'ed' : 's' ?>
        <?= e(date('M j, Y \a\t g:i a', strtotime((string)$ev['lock_at']))) ?>
      <?php endif; ?>
    </p>

    <div class="actions conf-actions">
      <?php if ($isCurrent): ?>
        <a class="btn btn-primary" href="index.php">Open dashboard</a>
      <?php else: ?>
        <form method="post" class="inline">
          <?= csrf_field() ?>
          <input type="hidden" name="event_id" value="<?= (int)$ev['id'] ?>">
          <button type="submit" class="btn">Work on this one</button>
        </form>
      <?php endif; ?>
      <?php if ((int)$ev['player_count'] > 0): ?>
        <a class="btn" href="../leaderboard.php?event=<?= e($ev['slug']) ?>">Standings</a>
      <?php endif; ?>
    </div>
  </div>
<?php endforeach; ?>

<div class="card">
  <h2>Starting the next one</h2>
  <p class="muted">
    Run this from the project folder. The newest conference is the one the site
    shows to players; everything before it stays readable here and on the public
    standings page.
  </p>
  <pre class="snippet">php tools/new_conference.php april-2027 "April 2027 General Conference" --lock="2027-04-03 10:00"</pre>
</div>
<?php page_foot();
