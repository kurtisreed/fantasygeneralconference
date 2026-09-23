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

// "Not you?" — same idea as admin/logout.php: drop the session and let the
// next player use this device without an incognito window.
if (isset($_GET['switch'])) {
    unset($_SESSION['player_id']);
    session_regenerate_id(true);
    redirect('index.php');
}

// A shared link (?code=XXXXXX) does the same thing as typing the code into
// "Already started?" below — the code is already the credential either way,
// this just skips the typing for anyone tapping a link from admin's share
// button. Falls through to the ordinary error banner if the code is bad.
if (isset($_GET['code']) && trim((string)$_GET['code']) !== '') {
    $code = trim((string)$_GET['code']);
    $player = q1('SELECT * FROM players WHERE event_id = ? AND entry_code = ?', [$eventId, $code]);
    if ($player) {
        $_SESSION['player_id'] = (int)$player['id'];
        redirect('play.php');
    }
    $error = 'No entry found with that code.';
}

// The session cookie is already what authorizes editing a sheet on play.php —
// the entry code only exists to start that same session on a new device. A
// returning player on the same phone shouldn't have to dig up the code at
// all, so offer the shortcut straight from the session if we have one.
$continuePlayer = null;
if (!empty($_SESSION['player_id'])) {
    $continuePlayer = q1(
        'SELECT * FROM players WHERE id = ? AND event_id = ?',
        [(int)$_SESSION['player_id'], $eventId]
    );
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    if (post('action') === 'resume') {
        $code = (string)post('entry_code');
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
            $code = make_entry_code($eventId);
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

<?php if ($continuePlayer): ?>
  <section class="card continue-card">
    <div class="continue-text">
      <p class="continue-who">
        Welcome back, <strong><?= e($continuePlayer['display_name']) ?></strong>.
        <a class="link continue-switch" href="?switch=1">Not you?</a>
      </p>
      <p class="muted continue-note">Click below to change your picks and check off which sessions you watched.</p>
    </div>
    <a class="btn btn-primary" href="play.php">Continue to my sheet &rarr;</a>
  </section>
<?php endif; ?>

<div class="trio">

  <?php if ($open && !$continuePlayer): ?>
  <section class="card choice">
    <h2>Make new picks!</h2>
    <p class="muted">Make your picks before General Conference starts. Only one sheet per person.</p>
    <form method="post">
      <?= csrf_field() ?>
      <label for="display_name">Your name</label>
      <input type="text" id="display_name" name="display_name" maxlength="80"
             autocomplete="name" placeholder="e.g. Ethan S." required>
      <button type="submit" class="btn btn-primary">Start picking</button>
    </form>
  </section>
  <?php endif; ?>

  <?php if (!$continuePlayer): ?>
  <section class="card choice">
    <h2>Already started?</h2>
    <p class="muted">Enter your 4-digit code to edit your picks. If you don't remember the code, text Bishop Reed or Porter, and they can get it for you.</p>
    <form method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="resume">
      <label for="entry_code">Entry code</label>
      <input type="text" id="entry_code" name="entry_code" maxlength="4"
             class="code-input" inputmode="numeric" pattern="[0-9]*" autocomplete="off" required>
      <button type="submit" class="btn">Open my sheet</button>
    </form>
  </section>
  <?php endif; ?>

  <section class="card choice standings-teaser">
    <h2>Standings!</h2>

    <?php if (!$board): ?>
      <p class="muted">Nobody has turned in a sheet yet &mdash; be the first!</p>

    <?php elseif ($leader === null): ?>
      <p class="muted">
        <?= count($board) ?> sheet<?= count($board) === 1 ? '' : 's' ?> in.
        Scoring starts when conference does.
      </p>

    <?php else: ?>
      <ol class="board board-mini">
        <?php
        $rank = 0; $lastTotal = null; $shown = 0;
        foreach (array_slice($board, 0, 3) as $row):
            $shown++;
            if ($lastTotal === null || (int)$row['total'] !== (int)$lastTotal) {
                $rank = $shown;
            }
            $lastTotal = (int)$row['total']; ?>
          <li class="board-row<?= $rank === 1 ? ' leader' : '' ?> medal medal-<?= $rank ?>">
            <span class="rank"><?= $rank ?></span>
            <span class="board-name"><?= e($row['display_name']) ?></span>
            <span class="board-pts"><?= (int)$row['total'] ?></span>
          </li>
        <?php endforeach; ?>
      </ol>
      <p class="muted"><?= count($board) ?> playing.</p>
    <?php endif; ?>

    <a class="btn btn-primary choice-go" href="leaderboard.php">See the full standings</a>
  </section>

</div>

<?php if (event_count() > 1): ?>
  <p class="center"><a class="link" href="conferences.php">Past conferences &rarr;</a></p>
<?php endif; ?>
<?php
page_foot();
