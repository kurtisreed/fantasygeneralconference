<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/lib/bootstrap.php';
require_once APP_ROOT . '/lib/auth.php';

if (admin_user()) {
    redirect('index.php');
}
$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    if (admin_login((string)post('username'), (string)($_POST['password'] ?? ''))) {
        redirect('index.php');
    }
    $error = 'Wrong username or password.';
    usleep(400000);
}

page_head('Admin sign in', '../');
?>
<section class="hero compact"><h1>Scorekeeper</h1></section>
<?php if ($error): ?><div class="error"><?= e($error) ?></div><?php endif; ?>
<div class="card">
  <form method="post" class="stack">
    <?= csrf_field() ?>
    <label for="username">Username</label>
    <input type="text" id="username" name="username" autocomplete="username" required>
    <label for="password">Password</label>
    <input type="password" id="password" name="password" autocomplete="current-password" required>
    <button type="submit" class="btn btn-primary">Sign in</button>
  </form>
</div>
<?php page_foot();
