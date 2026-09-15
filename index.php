<?php
declare(strict_types=1);
require_once __DIR__ . '/lib/bootstrap.php';
require_once APP_ROOT . '/lib/questions.php';
require_once APP_ROOT . '/lib/scoring.php';

$event = get_event();
if (!$event) {
    exit('No event set up yet. Run tools/install.php first.');
}
$eventId = (int)$event['id'];
$open = entries_open($event);
$questions = get_questions($eventId);
$possible = total_points_possible($questions);
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    if (post('action') === 'resume') {
        $code = strtoupper((string)post('entry_code'));
        $player = q1('SELECT * FROM players WHERE event_id = ? AND entry_code = ?', [$eventId, $code]);
        if (!$player) {
            $error = 'No entry found with that code.';
        } else {
            $_SESSION['player_id'] = (int)$player['id'];
            redirect('play.php');
        }
    } else {
        $name = (string)post('display_name');
        if (mb_strlen($name) < 2) {
            $error = 'Please enter your name.';
        } elseif (!$open) {
            $error = 'Entries are closed.';
        } elseif (q1('SELECT id FROM players WHERE event_id=? AND display_name=?', [$eventId, $name])) {
            $error = 'Someone already used that name — add a last initial.';
        } else {
            $code = make_entry_code();
            exec_sql(
                'INSERT INTO players (event_id, display_name, entry_code) VALUES (?,?,?)',
                [$eventId, $name, $code]
            );
            $_SESSION['player_id'] = (int)db()->lastInsertId();
            redirect('play.php');
        }
    }
}

// The standings card carries a line of real news so it reads as a peer of the
// two forms rather than a link in card's clothing.
$board  = leaderboard($eventId);
$leader = ($board && (int)$board[0]['total'] > 0) ? $board[0] : null;

page_head('Play');
?>
<figure class="hero-art">
  <img src="<?= e(asset_url('assets/img/shepherd.webp')) ?>" alt="" width="1344" height="756">
</figure>
<p class="art-credit">&ldquo;Shall Not Want&rdquo; by Yongsung Kim</p>

<section class="hero hero-titled">
  <h1><?= e($event['name']) ?></h1>
  <p class="lede">
    <?= $possible ?> points on the table. Guess who speaks when, who conducts,
    what color tie President Oaks wears, and how many times somebody says
    &ldquo;covenant.&rdquo;
  </p>
  <?php if (!$open): ?>
    <p class="notice">
      <?= $event['status'] === 'draft'
            ? 'Entries are not open yet. Check back soon.'
            : 'Entries have closed — conference has started.' ?>
    </p>
  <?php endif; ?>
  <?php if (!empty($event['lock_at']) && $open): ?>
    <p class="notice">Picks lock <?= e(date('l, F j \a\t g:i a', strtotime((string)$event['lock_at']))) ?>.</p>
  <?php endif; ?>
</section>

<?php if ($error): ?><div class="error"><?= e($error) ?></div><?php endif; ?>

<div class="trio">

  <?php if ($open): ?>
  <section class="card choice">
    <h2>Start a new sheet</h2>
    <p class="muted">Make your picks before Saturday morning.</p>
    <form method="post">
      <?= csrf_field() ?>
      <label for="display_name">Your name</label>
      <input type="text" id="display_name" name="display_name" maxlength="80"
             autocomplete="name" placeholder="e.g. Ethan S." required>
      <button type="submit" class="btn btn-primary">Start picking</button>
    </form>
  </section>
  <?php endif; ?>

  <section class="card choice">
    <h2>Already started?</h2>
    <p class="muted">Enter the 6-character code from your sheet.</p>
    <form method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="resume">
      <label for="entry_code">Entry code</label>
      <input type="text" id="entry_code" name="entry_code" maxlength="6"
             class="code-input" autocapitalize="characters" autocomplete="off" required>
      <button type="submit" class="btn">Open my sheet</button>
    </form>
  </section>

  <section class="card choice">
    <h2>See the standings</h2>
    <p class="muted">
      <?php if (!$board): ?>
        Nobody has turned in a sheet yet.
      <?php elseif ($leader !== null): ?>
        <?= count($board) ?> playing.
        <strong><?= e($leader['display_name']) ?></strong> leads with <?= (int)$leader['total'] ?>.
      <?php else: ?>
        <?= count($board) ?> sheet<?= count($board) === 1 ? '' : 's' ?> in.
        Scoring starts when conference does.
      <?php endif; ?>
    </p>
    <a class="btn choice-go" href="leaderboard.php">See the standings</a>
  </section>

</div>

<?php if (event_count() > 1): ?>
  <p class="center"><a class="link" href="conferences.php">Past conferences &rarr;</a></p>
<?php endif; ?>
<?php
page_foot();
