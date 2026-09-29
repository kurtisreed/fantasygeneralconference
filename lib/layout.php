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
    $org   = current_org();
    $accent = preg_match('/^#[0-9a-f]{6}$/i', (string)$org['accent_color']) ? $org['accent_color'] : null;
    [$markSmall, $markBig] = wordmark_parts($org['name']);
    $flash = flash();
    ?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title) ?> · <?= e($org['name']) ?></title>
<link rel="stylesheet" href="<?= e(asset_url('assets/style.css', $assetPrefix)) ?>">
<link rel="icon" href="<?= e($assetPrefix) ?>favicon.ico" sizes="any">
<link rel="icon" type="image/png" sizes="192x192" href="<?= e(asset_url('assets/img/icon-192.png', $assetPrefix)) ?>">
<link rel="apple-touch-icon" href="<?= e(asset_url('assets/img/icon-180.png', $assetPrefix)) ?>">
<meta name="theme-color" content="<?= e($accent ?? '#5d7342') ?>">
<?php if ($accent): ?><style>:root,:root:not([data-theme="light"]){--accent:<?= e($accent) ?>;--accent-ink:<?= e(accent_ink($accent)) ?>}</style>
<?php endif; ?>
</head>
<body>
<header class="site-header">
  <a class="wordmark" href="<?= e($assetPrefix) ?>index.php">
<?php if (!empty($org['logo_path'])): ?>
    <img class="wordmark-logo" src="<?= e($assetPrefix . $org['logo_path']) ?>" alt="">
<?php endif; ?>
<?php if ($markSmall !== ''): ?>    <span class="wordmark-small"><?= e($markSmall) ?></span>
<?php endif; ?>    <span class="wordmark-big"><?= e($markBig) ?></span>
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
