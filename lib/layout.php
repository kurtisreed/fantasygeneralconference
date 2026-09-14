<?php
declare(strict_types=1);

/** Append the file's mtime so a restyle is never stuck behind a cached copy. */
function asset_url(string $path, string $prefix = ''): string
{
    $full = APP_ROOT . '/' . ltrim($path, '/');
    $v = is_file($full) ? (string)filemtime($full) : '1';
    return $prefix . $path . '?v=' . $v;
}

function page_head(string $title, string $assetPrefix = ''): void
{
    global $CONFIG;
    $flash = flash();
    ?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title) ?> · <?= e($CONFIG['site_name']) ?></title>
<link rel="stylesheet" href="<?= e(asset_url('assets/style.css', $assetPrefix)) ?>">
</head>
<body>
<header class="site-header">
  <a class="wordmark" href="<?= e($assetPrefix) ?>index.php">
    <span class="wordmark-small">Fantasy</span>
    <span class="wordmark-big">General Conference</span>
  </a>
</header>
<main class="wrap">
<?php if ($flash): ?>
  <div class="flash"><?= e($flash) ?></div>
<?php endif;
}

function page_foot(): void
{
    ?>
</main>
<footer class="site-footer">
  <p>Guess well. Watch anyway.</p>
</footer>
<script>
// Chromium browsers — Brave in particular — will restore this page from the
// back/forward cache even though it is sent no-store, which can show standings
// from before the last session was scored. Reload whenever that happens.
addEventListener('pageshow', function (e) {
  if (e.persisted) { location.reload(); }
});
</script>
<?php
}
