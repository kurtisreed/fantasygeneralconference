<?php
require_once __DIR__ . '/_head.php';
require_once APP_ROOT . '/lib/sheet.php';
[$admin, $event] = admin_guard();
$eventId = (int)$event['id'];

$questions = get_questions($eventId);
$byKey = [];
foreach ($questions as $qq) {
    $byKey[$qq['qkey']] = $qq;
}

/**
 * Check one sheet's answers against this conference's questions. Answers are
 * keyed by qkey (not question id) so a file made against one database still
 * lines up with another.
 */
function import_check(array $answers, array $byKey): array
{
    $posted = [];
    $problems = [];
    foreach ($answers as $qkey => $value) {
        $qq = $byKey[$qkey] ?? null;
        $value = (string)$value;
        if (!$qq || $qq['type'] === 'watched') {
            $problems[] = "unknown question \"$qkey\"";
            continue;
        }
        $ok = $qq['type'] === 'over_under'
            ? in_array($value, ['over', 'under'], true)
            : in_array($value, array_column($qq['options'], 'value'), true);
        if (!$ok) {
            $problems[] = "\"$value\" isn't a choice for $qkey";
            continue;
        }
        $posted[(int)$qq['id']] = $value;
    }
    return [$posted, $problems];
}

$error = null;
$sheets = $_SESSION['import_sheets'] ?? null;
if (($_SESSION['import_event'] ?? null) !== $eventId) {
    $sheets = null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = post('action');

    if ($action === 'upload') {
        $raw = '';
        if (!empty($_FILES['file']['tmp_name']) && is_uploaded_file($_FILES['file']['tmp_name'])) {
            $raw = (string)file_get_contents($_FILES['file']['tmp_name']);
        }
        $data = json_decode($raw, true);
        if (!is_array($data) || !is_array($data['sheets'] ?? null)) {
            $error = 'That file isn\'t a sheet import file.';
        } elseif (($data['event'] ?? '') !== $event['slug']) {
            $error = 'That file is for "' . ($data['event'] ?? '?') . '", but you\'re working on ' . $event['name'] . '.';
        } else {
            $sheets = [];
            foreach ($data['sheets'] as $s) {
                $sheets[] = [
                    'name'    => trim((string)($s['name'] ?? '')),
                    'answers' => is_array($s['answers'] ?? null) ? $s['answers'] : [],
                    'notes'   => array_map('strval', (array)($s['notes'] ?? [])),
                    'sheet'   => (int)($s['sheet'] ?? 0),
                ];
            }
            $_SESSION['import_sheets'] = $sheets;
            $_SESSION['import_event'] = $eventId;
            redirect('import.php');
        }
    } elseif ($action === 'cancel') {
        unset($_SESSION['import_sheets'], $_SESSION['import_event']);
        redirect('enter.php');
    } elseif ($action === 'import' && $sheets) {
        $names = $_POST['name'] ?? [];
        $picked = $_POST['take'] ?? [];
        $made = [];
        $skipped = [];

        $pdo = db();
        $pdo->beginTransaction();
        foreach ($sheets as $i => $s) {
            if (empty($picked[$i])) {
                continue;
            }
            $name = trim((string)($names[$i] ?? $s['name']));
            if (mb_strlen($name) < 2) {
                $skipped[] = 'sheet ' . $s['sheet'] . ' (no name)';
                continue;
            }
            if (q1('SELECT id FROM players WHERE event_id = ? AND display_name = ?', [$eventId, $name])) {
                $skipped[] = $name . ' (name already taken)';
                continue;
            }
            [$posted] = import_check($s['answers'], $byKey);
            exec_sql(
                'INSERT INTO players (event_id, display_name, entry_code) VALUES (?,?,?)',
                [$eventId, mb_substr($name, 0, 80), make_entry_code($eventId)]
            );
            save_sheet((int)$pdo->lastInsertId(), $questions, $posted);
            $made[] = $name;
        }
        $pdo->commit();
        recompute_event_scores($eventId);

        unset($_SESSION['import_sheets'], $_SESSION['import_event']);
        $msg = count($made) . ' sheet' . (count($made) === 1 ? '' : 's') . ' imported.';
        if ($skipped) {
            $msg .= ' Skipped: ' . implode(', ', $skipped) . '.';
        }
        flash($msg);
        redirect('enter.php');
    }
}

$taken = array_column(
    q('SELECT display_name FROM players WHERE event_id = ?', [$eventId]),
    'display_name'
);
$askable = count(array_filter($questions, fn($qq) => $qq['type'] !== 'watched'));

admin_chrome('Import sheets', 'enter.php', $event);
?>
<?php if ($error): ?><div class="error"><?= e($error) ?></div><?php endif; ?>

<section class="hero compact">
  <h1>Import paper sheets</h1>
  <p class="lede">
    Load a file of already-transcribed paper sheets instead of typing each one in.
    You'll see every sheet before anything is saved.
  </p>
</section>

<?php if (!$sheets): ?>
  <div class="card">
    <form method="post" enctype="multipart/form-data" class="stack">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="upload">
      <label for="file">Sheet file (.json)</label>
      <input type="file" id="file" name="file" accept=".json,application/json" required>
      <button type="submit" class="btn btn-primary">Preview</button>
    </form>
  </div>
<?php else: ?>
  <form method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="import">
    <div class="card">
      <h2><?= count($sheets) ?> sheets in this file</h2>
      <p class="muted">
        Fix any names first — they can't be changed after import. Blank answers
        were left blank or marked twice on paper; fill those in afterwards from
        <a class="link" href="enter.php">Paper sheets</a> if you can tell what was meant.
        Sessions watched isn't on the paper, so it starts at 0.
      </p>
      <div class="table-scroll">
      <table class="players import-table">
        <thead><tr><th></th><th>Name</th><th>Answered</th><th>Notes</th></tr></thead>
        <tbody>
        <?php foreach ($sheets as $i => $s):
            [$posted, $problems] = import_check($s['answers'], $byKey);
            $clash = in_array($s['name'], $taken, true); ?>
          <tr>
            <td><input type="checkbox" name="take[<?= $i ?>]" value="1" <?= $clash ? '' : 'checked' ?>
                       aria-label="Import sheet <?= (int)$s['sheet'] ?>"></td>
            <td><input type="text" name="name[<?= $i ?>]" value="<?= e($s['name']) ?>" maxlength="80"></td>
            <td><?= count($posted) ?> / <?= $askable ?></td>
            <td class="import-notes">
              <?php if ($clash): ?><strong>Already a sheet under this name — rename it or leave it unchecked.</strong><br><?php endif; ?>
              <?php foreach (array_merge($problems, $s['notes']) as $n): ?><?= e($n) ?><br><?php endforeach; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      </div>
    </div>
    <div class="submit-bar">
      <button type="submit" class="btn btn-primary">Import checked sheets</button>
    </div>
  </form>
  <form method="post" class="center">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="cancel">
    <button type="submit" class="btn btn-sm">Cancel import</button>
  </form>
<?php endif; ?>

<p class="center"><a class="link" href="enter.php">&larr; Paper sheets</a></p>
<?php
page_foot();
