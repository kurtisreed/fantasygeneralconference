<?php
require_once __DIR__ . '/_head.php';
[$admin, $event] = admin_page('Event', 'event.php');
$eventId = (int)$event['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    if (post('action') === 'rescore') {
        $n = recompute_event_scores($eventId);
        flash("Rescored everyone ($n scoring rows).");
    } else {
        $status = (string)post('status');
        if (!in_array($status, ['draft', 'open', 'locked', 'final'], true)) {
            $status = 'draft';
        }
        $lock = (string)post('lock_at');
        exec_sql(
            'UPDATE events SET name = ?, status = ?, lock_at = ? WHERE id = ?',
            [(string)post('name'), $status, $lock !== '' ? str_replace('T', ' ', $lock) . ':00' : null, $eventId]
        );
        flash('Event updated.');
    }
    redirect('event.php');
}

$lockValue = $event['lock_at'] ? date('Y-m-d\TH:i', strtotime((string)$event['lock_at'])) : '';
$statuses = [
    'draft'  => 'Draft — nobody can start a sheet yet',
    'open'   => 'Open — players can create and edit picks',
    'locked' => 'Locked — picks frozen, standings visible',
    'final'  => 'Final — everything done',
];
?>
<section class="hero compact">
  <h1>Event settings</h1>
</section>

<div class="card">
  <form method="post" class="stack">
    <?= csrf_field() ?>
    <label for="name">Name</label>
    <input type="text" id="name" name="name" value="<?= e($event['name']) ?>" maxlength="160" required>

    <label for="status">Status</label>
    <select id="status" name="status">
      <?php foreach ($statuses as $v => $label): ?>
        <option value="<?= e($v) ?>" <?= $event['status'] === $v ? 'selected' : '' ?>><?= e($label) ?></option>
      <?php endforeach; ?>
    </select>

    <label for="lock_at">Picks lock at <span class="muted">(Mountain Time — optional)</span></label>
    <input type="datetime-local" id="lock_at" name="lock_at" value="<?= e($lockValue) ?>">
    <p class="help">Set this to Saturday morning and picks close on their own, even if you&rsquo;re not at a computer.</p>

    <button type="submit" class="btn btn-primary">Save</button>
  </form>
</div>

<div class="card">
  <h2>Share this link</h2>
  <p class="code code-wrap"><?= e((isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . dirname($_SERVER['PHP_SELF'] ?? '', 2) . '/') ?></p>
</div>

<div class="card">
  <h2>Recompute</h2>
  <p class="muted">Scores recompute automatically when you save results, rulings or sessions watched. This is here for when you want to be sure.</p>
  <form method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="rescore">
    <button type="submit" class="btn">Rescore everyone</button>
  </form>
</div>
<?php page_foot();
