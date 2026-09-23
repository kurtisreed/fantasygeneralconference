<?php
require_once __DIR__ . '/_head.php';
[$admin, $event] = admin_guard();

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    if (post('action') === 'remove') {
        $id = (int)post('admin_id');
        if ($id === (int)$admin['id']) {
            // Removing yourself would sign you out mid-request with nobody
            // else confirmed able to sign back in and undo it. Simplest safe
            // rule: do it from another account instead.
            $error = "You can't remove your own account while signed in as it — sign in as another admin first.";
        } else {
            $removed = exec_sql('DELETE FROM admins WHERE id = ?', [$id]);
            flash($removed ? 'Admin account removed.' : 'That admin was already gone.');
            redirect('admins.php');
        }
    } else {
        $username = trim((string)post('username'));
        $password = (string)($_POST['password'] ?? '');

        if (!preg_match('/^[A-Za-z0-9_.-]{3,64}$/', $username)) {
            $error = 'Username must be 3-64 characters: letters, numbers, dots, dashes or underscores.';
        } elseif (strlen($password) < 8) {
            $error = 'Password must be at least 8 characters.';
        } elseif (q1('SELECT id FROM admins WHERE username = ?', [$username])) {
            $error = 'That username is already taken.';
        } else {
            exec_sql(
                'INSERT INTO admins (username, password_hash) VALUES (?,?)',
                [$username, password_hash($password, PASSWORD_DEFAULT)]
            );
            flash('Admin account "' . $username . '" created.');
            redirect('admins.php');
        }
    }
}

admin_chrome('Admins', 'admins.php', $event);

$admins = q('SELECT id, username, created_at FROM admins ORDER BY created_at, id');
$others = array_filter($admins, static fn($a) => (int)$a['id'] !== (int)$admin['id']);
?>
<section class="hero compact">
  <h1>Admins</h1>
  <p class="lede">
    Anyone with an account here can enter results, edit players, and everything
    else in this nav &mdash; there's no separate read-only role.
  </p>
</section>

<?php if ($error): ?><div class="error"><?= e($error) ?></div><?php endif; ?>

<div class="card">
  <table class="players">
    <thead><tr><th>Username</th><th>Created</th></tr></thead>
    <tbody>
      <?php foreach ($admins as $a): ?>
        <tr>
          <td>
            <?= e($a['username']) ?>
            <?php if ((int)$a['id'] === (int)$admin['id']): ?>
              <span class="muted">(you)</span>
            <?php endif; ?>
          </td>
          <td><?= e(date('M j, Y', strtotime((string)$a['created_at']))) ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<div class="card">
  <h2>Add an admin</h2>
  <form method="post" class="stack">
    <?= csrf_field() ?>
    <label for="username">Username</label>
    <input type="text" id="username" name="username" maxlength="64" autocomplete="off" required>
    <label for="password">Password</label>
    <input type="password" id="password" name="password" minlength="8" autocomplete="new-password" required>
    <button type="submit" class="btn btn-primary">Add admin</button>
  </form>
</div>

<?php if ($others): ?>
<div class="card danger-zone">
  <h2>Remove an admin</h2>
  <form method="post" class="inline"
        onsubmit="return confirm('Remove this admin account? They will no longer be able to sign in.');">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="remove">
    <select name="admin_id">
      <?php foreach ($others as $a): ?>
        <option value="<?= (int)$a['id'] ?>"><?= e($a['username']) ?></option>
      <?php endforeach; ?>
    </select>
    <button type="submit" class="btn btn-danger">Remove</button>
  </form>
</div>
<?php endif;
page_foot();
