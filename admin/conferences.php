<?php
require_once __DIR__ . '/_head.php';
require_once APP_ROOT . '/lib/seed.php';
[$admin, $event] = admin_guard();

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    if (post('action') === 'create') {
        $name = (string)post('name');
        $slug = strtolower(trim((string)post('slug')));
        $date = (string)post('starts_at');
        $time = (string)post('lock_time') ?: '10:00';

        if (mb_strlen($name) < 3) {
            $error = 'Give the conference a name.';
        } elseif (!preg_match('/^[a-z0-9-]{3,64}$/', $slug)) {
            $error = 'The short name can only use lowercase letters, numbers and dashes.';
        } elseif (q1('SELECT id FROM events WHERE slug = ?', [$slug])) {
            $error = 'There is already a conference with that short name.';
        } elseif (!$date || !strtotime($date)) {
            $error = 'Pick the Saturday the conference starts.';
        } elseif (!preg_match('/^\d{1,2}:\d{2}$/', $time)) {
            $error = 'That lock time does not look right.';
        } else {
            $lockAt = date('Y-m-d H:i:s', (int)strtotime("$date $time"));
            $newId  = fgc_seed($slug, $name);
            exec_sql(
                'UPDATE events SET starts_at = ?, lock_at = ?, status = ? WHERE id = ?',
                [date('Y-m-d', (int)strtotime($date)), $lockAt, post('open_now') ? 'open' : 'draft', $newId]
            );
            $_SESSION['admin_event_id'] = $newId;
            flash("$name created. Check the over/under lines on Questions before you open it.");
            redirect('index.php');
        }
    } else {
        $picked = get_event_by_id((int)post('event_id'));
        if ($picked) {
            $_SESSION['admin_event_id'] = (int)$picked['id'];
            flash('Now working on ' . $picked['name'] . '.');
        }
        redirect('index.php');
    }
}

$events  = get_events();
$current = (int)$event['id'];
$newest  = get_event();

// Prefill with the next conference after the most recent one we already have.
$latestDate = $events[0]['starts_at'] ?? date('Y-m-d');
$suggest    = next_conference_after($latestDate);

admin_chrome('Conferences', '');
?>
<?php if ($error): ?><div class="error"><?= e($error) ?></div><?php endif; ?>
<section class="hero compact">
  <h1>Conferences</h1>
  <p class="lede">
    Pick the one you want to work on. Everything else in the admin &mdash;
    results, players, questions &mdash; then applies to it.
  </p>
</section>

<?php foreach ($events as $ev):
    $isCurrent = (int)$ev['id'] === $current;                       // the one you're editing
    $isLive    = $newest && (int)$newest['id'] === (int)$ev['id'];  // the one players see
    $questions = (int)$ev['question_count'];
    $results   = (int)$ev['result_count']; ?>
  <div class="card conf <?= $isCurrent ? 'conf-on' : '' ?>">
    <header class="section-head">
      <h2>
        <?= e($ev['name']) ?>
        <?php if ($isLive): ?><span class="pill pill-now">players see this</span><?php endif; ?>
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
  <h2>Start the next conference</h2>
  <p class="muted">
    Prefilled with <strong><?= e($suggest['name']) ?></strong>, which falls on
    <?= e($suggest['saturday'] ? date('l, F j, Y', (int)strtotime($suggest['saturday'])) : '—') ?>.
    Change anything that isn&rsquo;t right. It starts in draft, so nobody can
    pick until you open it.
  </p>

  <form method="post" class="stack" id="newconf">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="create">

    <label for="c_name">Name</label>
    <input type="text" id="c_name" name="name" maxlength="160" required
           value="<?= e($suggest['name']) ?>">

    <label for="c_slug">Short name <span class="muted">(used in links)</span></label>
    <input type="text" id="c_slug" name="slug" maxlength="64" required
           pattern="[a-z0-9\-]{3,64}" value="<?= e($suggest['slug']) ?>">

    <div class="field-row">
      <div>
        <label for="c_date">Saturday of conference</label>
        <input type="date" id="c_date" name="starts_at" required
               value="<?= e($suggest['saturday']) ?>">
      </div>
      <div>
        <label for="c_time">Picks lock at <span class="muted">(Mountain)</span></label>
        <input type="time" id="c_time" name="lock_time" value="10:00" required>
      </div>
    </div>

    <label class="checkline">
      <input type="checkbox" name="open_now" value="1">
      <span>Open it for picks right away</span>
    </label>

    <button type="submit" class="btn btn-primary">Create conference</button>
  </form>
</div>

<script>
// Keep the short name in step with the name until somebody edits it by hand.
(function () {
  var name = document.getElementById('c_name');
  var slug = document.getElementById('c_slug');
  if (!name || !slug) return;
  var touched = false;
  slug.addEventListener('input', function () { touched = true; });
  name.addEventListener('input', function () {
    if (touched) return;
    slug.value = name.value.toLowerCase()
      .replace(/general conference/g, '')
      .replace(/[^a-z0-9]+/g, '-')
      .replace(/^-+|-+$/g, '')
      .slice(0, 64);
  });
})();
</script>
<?php page_foot();
