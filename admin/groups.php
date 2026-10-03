<?php
require_once __DIR__ . '/_head.php';
[$admin, $event] = admin_guard(true);

// Groups are managed by super-admins only; a group's own admins never see this.
if (!is_super_admin($admin)) {
    redirect('index.php');
}

$error = null;
$editId = query_int('edit');
$editing = $editId ? q1('SELECT * FROM orgs WHERE id = ?', [$editId]) : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $id     = (int)post('org_id');
    $slug   = strtolower(trim((string)post('slug')));
    $name   = trim((string)post('name'));
    $leader = trim((string)post('leader_name'));
    $contact = trim((string)post('contact_line'));
    $note   = trim((string)post('announcement'));
    $accent = strtolower(trim((string)post('accent_color')));
    $useAccent = post('use_accent') !== null;
    $user   = trim((string)post('username'));
    $pass   = (string)($_POST['password'] ?? '');
    $current = $id ? q1('SELECT * FROM orgs WHERE id = ?', [$id]) : null;

    if (mb_strlen($name) < 3) {
        $error = 'Give the group a name.';
    } elseif (!$current && !preg_match('/^[a-z0-9-]{2,40}$/', $slug)) {
        $error = 'The address name can only use lowercase letters, numbers and dashes (2-40).';
    } elseif (!$current && (q1('SELECT id FROM orgs WHERE slug = ?', [$slug]) || $slug === 'www')) {
        $error = 'That address name is already taken.';
    } elseif ($useAccent && !preg_match('/^#[0-9a-f]{6}$/', $accent)) {
        $error = 'That color does not look right.';
    } elseif (!$current && !preg_match('/^[A-Za-z0-9_.-]{3,64}$/', $user)) {
        $error = 'First admin username must be 3-64 characters: letters, numbers, dots, dashes or underscores.';
    } elseif (!$current && strlen($pass) < 8) {
        $error = 'First admin password must be at least 8 characters.';
    } elseif (!$current && q1('SELECT id FROM admins WHERE username = ?', [$user])) {
        $error = 'That admin username is already taken.';
    } else {
        $logo = $current['logo_path'] ?? null;
        $file = $_FILES['logo'] ?? null;
        if ($file && $file['error'] === UPLOAD_ERR_OK) {
            $info = @getimagesize($file['tmp_name']);
            $ext  = [IMAGETYPE_PNG => 'png', IMAGETYPE_JPEG => 'jpg', IMAGETYPE_WEBP => 'webp'][$info[2] ?? 0] ?? null;
            if (!$ext || $file['size'] > 1024 * 1024) {
                $error = 'The logo must be a PNG, JPG or WebP under 1 MB.';
            } else {
                $dir = APP_ROOT . '/assets/img/orgs';
                is_dir($dir) || mkdir($dir, 0775, true);
                $slugForFile = $current['slug'] ?? $slug;
                $logo = 'assets/img/orgs/' . $slugForFile . '-' . time() . '.' . $ext;
                move_uploaded_file($file['tmp_name'], APP_ROOT . '/' . $logo);
            }
        }
        if (!$error) {
            $accentVal = $useAccent ? $accent : null;
            if ($current) {
                exec_sql(
                    'UPDATE orgs SET name=?, leader_name=?, contact_line=?, announcement=?, accent_color=?, logo_path=? WHERE id=?',
                    [$name, mb_substr($leader, 0, 80), mb_substr($contact, 0, 160), mb_substr($note, 0, 500) ?: null, $accentVal, $logo, $id]
                );
                flash($name . ' saved.');
            } else {
                exec_sql(
                    'INSERT INTO orgs (slug, name, leader_name, contact_line, accent_color, logo_path) VALUES (?,?,?,?,?,?)',
                    [$slug, $name, mb_substr($leader, 0, 80), mb_substr($contact, 0, 160), $accentVal, $logo]
                );
                exec_sql(
                    'INSERT INTO admins (username, password_hash, org_id) VALUES (?,?,?)',
                    [$user, password_hash($pass, PASSWORD_DEFAULT), (int)db()->lastInsertId()]
                );
                flash($name . ' created. Now add ' . $slug . '.' . ($GLOBALS['CONFIG']['base_domain'] ?? 'your domain') . ' as a subdomain in cPanel.');
            }
            redirect('groups.php');
        }
        $editing = $current;
    }
}

$orgs = q(
    'SELECT o.*, (SELECT COUNT(*) FROM events e WHERE e.org_id = o.id) AS events,
                 (SELECT COUNT(*) FROM admins a WHERE a.org_id = o.id) AS admins
       FROM orgs o ORDER BY o.name'
);
admin_chrome('Groups', 'groups.php', $event);
$v = fn(string $k, string $d = '') => e((string)($_POST[$k] ?? $editing[$k] ?? $d));
$accentNow = $editing['accent_color'] ?? null;
?>
<section class="hero compact">
  <h1>Groups</h1>
  <p class="lede">
    Each group runs its own conferences and players, with its own admins, at its
    own address. Only super-admins see this page.
  </p>
</section>

<?php if ($error): ?><div class="error"><?= e($error) ?></div><?php endif; ?>

<div class="card">
  <table class="players">
    <thead><tr><th>Group</th><th>Address</th><th>Conferences</th><th>Admins</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($orgs as $o): ?>
      <tr>
        <td><?= e($o['name']) ?></td>
        <td><a class="link" href="<?= e(org_url($o)) ?>admin/"><?= e(preg_replace('#^https?://|/$#', '', org_url($o))) ?></a></td>
        <td><?= (int)$o['events'] ?></td>
        <td><?= (int)$o['admins'] ?></td>
        <td><a class="link" href="groups.php?edit=<?= (int)$o['id'] ?>">edit</a></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>

<div class="card">
  <h2><?= $editing ? 'Edit ' . e($editing['name']) : 'Add a group' ?></h2>
  <form method="post" enctype="multipart/form-data" class="stack">
    <?= csrf_field() ?>
    <input type="hidden" name="org_id" value="<?= (int)($editing['id'] ?? 0) ?>">

    <label for="g_name">Name <span class="muted">(shown at the top of every page)</span></label>
    <input type="text" id="g_name" name="name" maxlength="120" required
           placeholder="e.g. Fantasy General Conference — Oak Ward" value="<?= $v('name') ?>">

    <?php if (!$editing): ?>
      <label for="g_slug">Address name <span class="muted">(oak-ward &rarr; oak-ward.<?= e($GLOBALS['CONFIG']['base_domain'] ?? 'yourdomain.com') ?>)</span></label>
      <input type="text" id="g_slug" name="slug" maxlength="40" required pattern="[a-z0-9\-]{2,40}"
             value="<?= $v('slug') ?>">
    <?php endif; ?>

    <label for="g_leader">Leader who invites <span class="muted">(flyer: &ldquo;Bishop Reed invites all of the youth&hellip;&rdquo;)</span></label>
    <input type="text" id="g_leader" name="leader_name" maxlength="80" placeholder="Bishop Reed" value="<?= $v('leader_name') ?>">

    <label for="g_contact">Who to text <span class="muted">(&ldquo;text Bishop Reed or Porter&rdquo;)</span></label>
    <input type="text" id="g_contact" name="contact_line" maxlength="160" placeholder="Bishop Reed or Porter" value="<?= $v('contact_line') ?>">

    <?php if ($editing): ?>
      <label for="g_note">Message <span class="muted">(plain text; web addresses become links; shown above the main page and standings for this group only; leave blank for none)</span></label>
      <textarea id="g_note" name="announcement" rows="3" maxlength="500"><?= $v('announcement') ?></textarea>
    <?php endif; ?>

    <label class="checkline">
      <input type="checkbox" name="use_accent" value="1" <?= $accentNow || isset($_POST['use_accent']) ? 'checked' : '' ?>>
      Use a custom accent color
    </label>
    <input type="color" name="accent_color" value="<?= e((string)($_POST['accent_color'] ?? $accentNow ?? '#5d7342')) ?>">

    <label for="g_logo">Logo <span class="muted">(optional, PNG/JPG/WebP under 1 MB)</span></label>
    <?php if (!empty($editing['logo_path'])): ?>
      <img src="../<?= e($editing['logo_path']) ?>" alt="" style="max-height:48px;max-width:160px">
    <?php endif; ?>
    <input type="file" id="g_logo" name="logo" accept="image/png,image/jpeg,image/webp">

    <?php if (!$editing): ?>
      <h3>First admin for this group</h3>
      <label for="g_user">Username</label>
      <input type="text" id="g_user" name="username" maxlength="64" autocomplete="off" required value="<?= $v('username') ?>">
      <label for="g_pass">Password</label>
      <input type="password" id="g_pass" name="password" minlength="8" autocomplete="new-password" required>
    <?php endif; ?>

    <button type="submit" class="btn btn-primary"><?= $editing ? 'Save group' : 'Add group' ?></button>
    <?php if ($editing): ?><a class="btn" href="groups.php">Cancel</a><?php endif; ?>
  </form>
</div>
<?php
page_foot();
