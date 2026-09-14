<?php
declare(strict_types=1);

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
<link rel="stylesheet" href="<?= e($assetPrefix) ?>assets/style.css">
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
<?php
}
