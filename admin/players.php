<?php
require_once __DIR__ . '/_head.php';
[$admin, $event] = admin_page('Players', 'players.php');
$eventId = (int)$event['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    if (post('action') === 'delete') {
        exec_sql('DELETE FROM players WHERE id = ? AND event_id = ?', [(int)post('player_id'), $eventId]);
        flash('Entry deleted.');
    } else {
        foreach (($_POST['watched'] ?? []) as $pid => $n) {
            exec_sql(
                'UPDATE players SET sessions_watched = ? WHERE id = ? AND event_id = ?',
                [max(0, min(4, (int)$n)), (int)$pid, $eventId]
            );
        }
        recompute_event_scores($eventId);
        flash('Sessions watched saved and scores recomputed.');
    }
    redirect('players.php');
}

$players = q(
    'SELECT p.*, COALESCE(SUM(s.points),0) AS total,
            (SELECT COUNT(*) FROM answers a WHERE a.player_id = p.id) AS answered
       FROM players p LEFT JOIN scores s ON s.player_id = p.id
      WHERE p.event_id = ?
      GROUP BY p.id
      ORDER BY p.display_name',
    [$eventId]
);
?>
<section class="hero compact">
  <h1>Players</h1>
  <p class="lede">Enter sessions watched here when you score the sheets together.</p>
</section>

<form method="post">
<?= csrf_field() ?>
<div class="card">
  <table class="players">
    <thead>
      <tr><th>Name</th><th>Code</th><th>Answered</th><th>Watched</th><th>Points</th><th></th></tr>
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
<?php endif;
page_foot();
