<?php
require_once __DIR__ . '/_head.php';
require_once APP_ROOT . '/lib/seed.php';
[$admin, $event] = admin_guard();
$eventId = (int)$event['id'];

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    // Rows carry their slug so renaming somebody keeps their picks; a blank
    // slug is a new speaker, and "remove" drops them and their grid row.
    $rows  = [];
    $slugs = [];
    foreach (($_POST['speaker'] ?? []) as $i => $row) {
        $name = trim((string)($row['name'] ?? ''));
        if ($name === '' || !empty($row['remove'])) {
            continue;
        }
        $slug = trim((string)($row['slug'] ?? '')) ?: speaker_slug($name);
        if ($slug === '') {
            $error = 'Could not make a key from "' . $name . '" — use some letters or numbers.';
            break;
        }
        if (isset($slugs[$slug])) {
            $error = 'Two speakers end up with the same key (' . $slug . '). Make the names more distinct.';
            break;
        }
        $slugs[$slug] = true;
        $rows[] = ['slug' => $slug, 'name' => mb_substr($name, 0, 120)];
    }

    if ($error === null && !$rows) {
        $error = 'Keep at least one speaker.';
    }

    if ($error === null) {
        $pdo = db();
        $pdo->beginTransaction();

        $keep = array_column($rows, 'slug');
        $in   = implode(',', array_fill(0, count($keep), '?'));
        exec_sql(
            "DELETE FROM speakers WHERE event_id = ? AND slug NOT IN ($in)",
            array_merge([$eventId], $keep)
        );
        foreach ($rows as $i => $r) {
            exec_sql(
                'INSERT INTO speakers (event_id, slug, name, sort_order) VALUES (?,?,?,?)
                 ON DUPLICATE KEY UPDATE name=VALUES(name), sort_order=VALUES(sort_order)',
                [$eventId, $r['slug'], $r['name'], $i]
            );
        }

        $sync = fgc_sync_speakers($eventId);
        $pdo->commit();

        recompute_event_scores($eventId);

        $msg = count($rows) . ' speakers saved.';
        if ($sync['removed']) {
            $msg .= ' ' . $sync['removed'] . ' removed from the grid.';
        }
        if ($sync['dropped']) {
            $msg .= ' ' . $sync['dropped'] . ' pick' . ($sync['dropped'] === 1 ? '' : 's')
                  . ' no longer valid and ' . ($sync['dropped'] === 1 ? 'was' : 'were') . ' cleared.';
        }
        flash($msg);
        redirect('speakers.php');
    }
}

$speakers = fgc_ensure_speakers($eventId);
$picksIn  = (int)q1(
    'SELECT COUNT(*) c FROM answers a
       JOIN players p ON p.id = a.player_id
      WHERE p.event_id = ?',
    [$eventId]
)['c'];

admin_chrome('Speakers', 'speakers.php', $event);
?>
<?php if ($error): ?><div class="error"><?= e($error) ?></div><?php endif; ?>

<section class="hero compact">
  <h1>Speakers</h1>
  <p class="lede">
    These are the names in the &ldquo;who speaks when&rdquo; grid, and the choices
    for who conducts and who speaks first. Listed in the order they appear on the
    sheet.
  </p>
</section>

<?php if ($picksIn > 0): ?>
  <div class="notice notice-block">
    <?= $picksIn ?> pick<?= $picksIn === 1 ? ' has' : 's have' ?> already been made for this
    conference. Renaming somebody keeps their picks; removing them deletes that row
    of the grid and every pick on it.
  </div>
<?php endif; ?>

<form method="post">
<?= csrf_field() ?>
<div class="card">
  <table class="speakers">
    <thead>
      <tr><th class="sp-num">#</th><th>Name</th><th class="sp-rm">Remove</th></tr>
    </thead>
    <tbody>
    <?php foreach ($speakers as $i => $s): ?>
      <tr>
        <td class="sp-num"><?= $i + 1 ?></td>
        <td>
          <input type="hidden" name="speaker[<?= $i ?>][slug]" value="<?= e($s['slug']) ?>">
          <input type="text" name="speaker[<?= $i ?>][name]" maxlength="120"
                 value="<?= e($s['name']) ?>" aria-label="Speaker <?= $i + 1 ?>">
        </td>
        <td class="sp-rm">
          <input type="checkbox" name="speaker[<?= $i ?>][remove]" value="1"
                 aria-label="Remove <?= e($s['name']) ?>">
        </td>
      </tr>
    <?php endforeach; ?>
    <?php for ($n = 0; $n < 3; $n++): $i = count($speakers) + $n; ?>
      <tr class="sp-new">
        <td class="sp-num">+</td>
        <td>
          <input type="hidden" name="speaker[<?= $i ?>][slug]" value="">
          <input type="text" name="speaker[<?= $i ?>][name]" maxlength="120"
                 placeholder="Add a speaker" aria-label="New speaker">
        </td>
        <td class="sp-rm"></td>
      </tr>
    <?php endfor; ?>
    </tbody>
  </table>
  <p class="help">
    Order follows the list. To move somebody, retype the names into the order you want.
  </p>
</div>

<div class="submit-bar">
  <span class="submit-count">Saving rebuilds the grid and rescores everyone</span>
  <button type="submit" class="btn btn-primary">Save speakers</button>
</div>
</form>
<?php page_foot();
