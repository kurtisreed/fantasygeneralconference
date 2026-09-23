<?php
require_once __DIR__ . '/_head.php';
require_once APP_ROOT . '/lib/sheet.php';
[$admin, $event] = admin_guard();
$eventId = (int)$event['id'];

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    if (post('action') === 'start') {
        // Either an existing sheet or a brand new name off a paper sheet.
        $existing = (int)post('player_id');
        if ($existing) {
            $p = q1('SELECT id FROM players WHERE id = ? AND event_id = ?', [$existing, $eventId]);
            if ($p) {
                redirect('enter.php?player=' . (int)$p['id']);
            }
            $error = 'That sheet is gone.';
        } else {
            $name = (string)post('display_name');
            if (mb_strlen($name) < 2) {
                $error = 'Enter the name from the paper sheet.';
            } elseif (q1('SELECT id FROM players WHERE event_id=? AND display_name=?', [$eventId, $name])) {
                $error = 'There is already a sheet under that name — pick it from the list instead.';
            } else {
                exec_sql(
                    'INSERT INTO players (event_id, display_name, entry_code) VALUES (?,?,?)',
                    [$eventId, $name, make_entry_code($eventId)]
                );
                redirect('enter.php?player=' . (int)db()->lastInsertId());
            }
        }
    } elseif (post('action') === 'save') {
        $pid = (int)post('player_id');
        $player = q1('SELECT * FROM players WHERE id = ? AND event_id = ?', [$pid, $eventId]);
        if (!$player) {
            $error = 'That sheet is gone.';
        } else {
            $questions = get_questions($eventId);
            $kept = save_sheet($pid, $questions, $_POST['q'] ?? []);

            $watched = post('sessions_watched');
            if ($watched !== null && $watched !== '') {
                exec_sql(
                    'UPDATE players SET sessions_watched = ? WHERE id = ?',
                    [max(0, min(4, (int)$watched)), $pid]
                );
            }
            recompute_event_scores($eventId);
            flash($player['display_name'] . "'s sheet saved — $kept answers recorded.");
            redirect('enter.php');
        }
    }
}

$pid    = query_int('player');
$player = $pid ? q1('SELECT * FROM players WHERE id = ? AND event_id = ?', [$pid, $eventId]) : null;

$players = q(
    'SELECT p.*, (SELECT COUNT(*) FROM answers a WHERE a.player_id = p.id) AS answered
       FROM players p WHERE p.event_id = ? ORDER BY p.display_name',
    [$eventId]
);

admin_chrome('Paper sheets', 'enter.php', $event);
?>
<?php if ($error): ?><div class="error"><?= e($error) ?></div><?php endif; ?>

<?php if (!$player): ?>
  <section class="hero compact">
    <h1>Paper sheets</h1>
    <p class="lede">
      For anyone who filled in a printed sheet. Type their picks in here and they
      land in the standings exactly like a phone entry. Works after picks lock.
    </p>
  </section>

  <div class="card">
    <h2>New paper sheet</h2>
    <form method="post" class="stack">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="start">
      <label for="display_name">Name on the sheet</label>
      <input type="text" id="display_name" name="display_name" maxlength="80"
             placeholder="e.g. Ethan S." required>
      <button type="submit" class="btn btn-primary">Start entering</button>
    </form>
  </div>

  <?php if ($players): ?>
    <div class="card">
      <h2>Or open an existing sheet</h2>
      <p class="muted">To correct a sheet you already typed in, or to fill in one somebody started.</p>
      <table class="players">
        <thead><tr><th>Name</th><th>Answered</th><th>Watched</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($players as $p): ?>
          <tr>
            <td><?= e($p['display_name']) ?></td>
            <td><?= (int)$p['answered'] ?></td>
            <td><?= (int)$p['sessions_watched'] ?></td>
            <td><a class="link" href="enter.php?player=<?= (int)$p['id'] ?>">open</a></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>

  <p class="center"><a class="link" href="print.php">Print blank sheets &rarr;</a></p>

<?php else:
    $questions = get_questions($eventId);
    $answers   = get_answers((int)$player['id']); ?>

  <section class="hero compact">
    <p class="eyebrow"><?= e($event['name']) ?> &middot; paper sheet</p>
    <h1><?= e($player['display_name']) ?></h1>
    <p class="lede">
      <?= total_points_possible($questions) ?> points possible.
      Code <strong class="code"><?= e($player['entry_code']) ?></strong> if they want to
      pick it up on a phone later.
    </p>
  </section>

  <form method="post" id="sheet">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save">
    <input type="hidden" name="player_id" value="<?= (int)$player['id'] ?>">

    <?php render_sheet_sections($questions, $answers, true, (int)$player['sessions_watched']); ?>

    <div class="card">
      <h2>Sessions watched</h2>
      <p class="muted"><?= watched_per($questions) ?> points each. Fill this in when you score the sheets together.</p>
      <input type="number" name="sessions_watched" min="0" max="4" class="mini"
             value="<?= (int)$player['sessions_watched'] ?>">
    </div>

    <div class="submit-bar">
      <span class="submit-count"><span id="answered">0</span> of <span id="total">0</span> answered</span>
      <button type="submit" class="btn btn-primary">Save this sheet</button>
    </div>
  </form>

  <p class="center"><a class="link" href="enter.php">&larr; All paper sheets</a></p>
  <script src="<?= e(asset_url('assets/app.js', '../')) ?>"></script>
<?php endif;
page_foot();
