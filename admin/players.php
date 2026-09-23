<?php
require_once __DIR__ . '/_head.php';
[$admin, $event] = admin_guard();
$eventId = (int)$event['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    if (post('action') === 'delete') {
        exec_sql('DELETE FROM players WHERE id = ? AND event_id = ?', [(int)post('player_id'), $eventId]);
        flash('Entry deleted.');
    } else {
        // Every row posts back on every save, whether or not the admin
        // touched it — only a real change should clear a player's own
        // self-reported detail, or reopening this page would silently wipe
        // everyone else's checkboxes.
        $current = [];
        foreach (q('SELECT id, sessions_watched FROM players WHERE event_id = ?', [$eventId]) as $p) {
            $current[(int)$p['id']] = (int)$p['sessions_watched'];
        }
        foreach (($_POST['watched'] ?? []) as $pid => $n) {
            $pid = (int)$pid;
            $n = max(0, min(4, (int)$n));
            if (($current[$pid] ?? null) === $n) {
                continue;
            }
            exec_sql(
                'UPDATE players SET sessions_watched = ?, watched_sessions = NULL WHERE id = ? AND event_id = ?',
                [$n, $pid, $eventId]
            );
        }
        recompute_event_scores($eventId);
        flash('Sessions watched saved and scores recomputed.');
    }
    redirect('players.php');
}

admin_chrome('Players', 'players.php', $event);

$players = q(
    'SELECT p.*, COALESCE(SUM(s.points),0) AS total,
            (SELECT COUNT(*) FROM answers a WHERE a.player_id = p.id) AS answered
       FROM players p LEFT JOIN scores s ON s.player_id = p.id
      WHERE p.event_id = ?
      GROUP BY p.id
      ORDER BY p.display_name',
    [$eventId]
);
$linkBase = site_url('index.php', '../') . '?code=';
?>
<section class="hero compact">
  <h1>Players</h1>
  <p class="lede">
    Players can self-report sessions watched on their own sheet. Use this to
    fix a number, or to enter it yourself for anyone who didn't.
  </p>
</section>

<form method="post">
<?= csrf_field() ?>
<div class="card">
  <table class="players">
    <thead>
      <tr><th>Name</th><th>Code</th><th>Answered</th><th>Watched</th><th>Points</th><th></th><th></th></tr>
    </thead>
    <tbody>
    <?php foreach ($players as $p): ?>
      <tr>
        <td><?= e($p['display_name']) ?><?= $p['submitted_at'] ? '' : ' <span class="muted">(draft)</span>' ?></td>
        <td class="code"><?= e($p['entry_code']) ?></td>
        <td><?= (int)$p['answered'] ?></td>
        <td>
          <input type="number" min="0" max="4" name="watched[<?= (int)$p['id'] ?>]"
                 value="<?= (int)$p['sessions_watched'] ?>" class="mini">
        </td>
        <td class="strong"><?= (int)$p['total'] ?></td>
        <td><a class="link" href="../leaderboard.php?player=<?= (int)$p['id'] ?>">sheet</a></td>
        <td>
          <button type="button" class="btn btn-sm share-link-btn"
                  data-url="<?= e($linkBase . $p['entry_code']) ?>"
                  data-name="<?= e($p['display_name']) ?>">Share link</button>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php if (!$players): ?><p class="muted">Nobody has started a sheet yet.</p><?php endif; ?>
</div>

<?php if ($players): ?>
<div class="submit-bar">
  <span class="submit-count">Saving rescores everyone</span>
  <button type="submit" class="btn btn-primary">Save sessions watched</button>
</div>
<?php endif; ?>
</form>

<?php if ($players): ?>
<div class="card danger-zone">
  <h2>Remove an entry</h2>
  <form method="post" class="inline" onsubmit="return confirm('Delete this entry and all its picks? This cannot be undone.');">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="delete">
    <select name="player_id">
      <?php foreach ($players as $p): ?>
        <option value="<?= (int)$p['id'] ?>"><?= e($p['display_name']) ?></option>
      <?php endforeach; ?>
    </select>
    <button type="submit" class="btn btn-danger">Delete</button>
  </form>
</div>
<?php endif; ?>

<script>
(function () {
  'use strict';
  document.querySelectorAll('.share-link-btn').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var url = btn.dataset.url;
      if (navigator.share) {
        navigator.share({ title: 'Fantasy General Conference', text: btn.dataset.name + '’s sheet', url: url })
          .catch(function () {});
        return;
      }
      if (navigator.clipboard) {
        navigator.clipboard.writeText(url).then(function () {
          var was = btn.textContent;
          btn.textContent = 'Copied!';
          setTimeout(function () { btn.textContent = was; }, 1500);
        });
      }
    });
  });
})();
</script>
<?php
page_foot();
