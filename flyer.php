<?php
declare(strict_types=1);
require_once __DIR__ . '/lib/bootstrap.php';

// A standalone "how to play" flyer, meant to be linked, texted, emailed, or
// printed — not just a page inside the app's own nav. It intentionally skips
// page_head()/page_foot() (same reasoning as admin/print.php): the flyer is
// its own complete document, not another section of the site chrome.
$siteUrl  = site_url('');
$siteHost = preg_replace('#^https?://#', '', rtrim($siteUrl, '/'));
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Fantasy General Conference</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,500;0,9..144,600;0,9..144,700;1,9..144,500&display=swap">
<style>
  :root {
    --bg: #f4eee1;
    --card: #fffdf8;
    --ink: #2b2820;
    --ink-soft: #6f6553;
    --line: #e4dac4;
    --accent: #5d7342;
    --accent-ink: #ffffff;
    --gold: #a8792c;
    --shadow: 0 1px 2px rgba(60, 48, 26, .07), 0 10px 28px rgba(60, 48, 26, .09);
    color-scheme: light;
  }

  @media (prefers-color-scheme: dark) {
    :root:not([data-theme="light"]) {
      --bg: #17150f;
      --card: #221f18;
      --ink: #f1ece0;
      --ink-soft: #a89e88;
      --line: #37322a;
      --accent: #9ab377;
      --accent-ink: #1a1f12;
      --gold: #d8b25f;
      --shadow: 0 1px 2px rgba(0, 0, 0, .3), 0 10px 28px rgba(0, 0, 0, .35);
      color-scheme: dark;
    }
  }

  :root[data-theme="dark"] {
    --bg: #17150f;
    --card: #221f18;
    --ink: #f1ece0;
    --ink-soft: #a89e88;
    --line: #37322a;
    --accent: #9ab377;
    --accent-ink: #1a1f12;
    --gold: #d8b25f;
    --shadow: 0 1px 2px rgba(0, 0, 0, .3), 0 10px 28px rgba(0, 0, 0, .35);
    color-scheme: dark;
  }

  * { box-sizing: border-box; }

  body {
    margin: 0;
    background: var(--bg);
    color: var(--ink);
    font: 16px/1.6 ui-sans-serif, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
  }

  .page {
    max-width: 760px;
    margin: 0 auto;
    padding: 32px 20px 56px;
  }

  h1, h2, h3 {
    font-family: "Fraunces", ui-serif, Georgia, serif;
    font-weight: 600;
    line-height: 1.15;
    text-wrap: balance;
    margin: 0;
  }

  /* ---------- hero ---------- */
  .hero {
    margin: 0 0 8px;
    border-radius: 14px;
    overflow: hidden;
    box-shadow: var(--shadow);
  }
  .hero img {
    display: block;
    width: 100%;
    height: auto;
    /* 20% shorter than the painting's native 1344x756 — cropped mostly out
       of the sky above and the empty grass below, so the shepherd's staff
       and the sheep at the edges both stay comfortably in frame. */
    aspect-ratio: 1344 / 604.8;
    object-fit: cover;
    object-position: 50% 60%;
  }
  .credit {
    margin: 0 0 18px;
    font-size: 12px;
    color: var(--ink-soft);
    text-align: right;
    font-style: italic;
  }

  /* ---------- intro ---------- */
  .intro { text-align: center; margin-bottom: 26px; }
  h1 {
    font-size: clamp(30px, 7vw, 42px);
    font-style: italic;
    font-weight: 700;
  }
  .tagline {
    margin: 14px auto 0;
    max-width: 52ch;
    font-size: 17px;
    color: var(--ink-soft);
  }

  /* ---------- sections ---------- */
  .block { margin-bottom: 28px; }
  .block h2 {
    font-size: 23px;
    display: table;
    margin: 0 auto 12px;
    padding-bottom: 7px;
    border-bottom: 3px solid var(--gold);
  }
  .block p { margin: 0 0 10px; }
  .block p:last-child { margin-bottom: 0; }
  .muted { color: var(--ink-soft); }
  strong { font-weight: 700; }

  /* ---------- two ways to play ---------- */
  .way-grid {
    display: flex;
    flex-wrap: wrap;
    gap: 14px;
    margin-top: 4px;
  }
  .way {
    flex: 1 1 220px;
    background: var(--card);
    border: 1px solid var(--line);
    border-radius: 12px;
    padding: 18px 18px 20px;
    box-shadow: var(--shadow);
  }
  .way h3 {
    font-size: 18px;
    margin-bottom: 8px;
  }
  .way p {
    margin: 0;
    font-size: 14.5px;
    color: var(--ink-soft);
  }
  .way p strong { color: var(--ink); }
  .note {
    margin: 14px 0 0;
    font-size: 13.5px;
    color: var(--ink-soft);
    text-align: center;
  }

  a { color: var(--accent); font-weight: 700; text-decoration: none; }
  a:hover { text-decoration: underline; }

  /* ---------- closing ---------- */
  .closing {
    text-align: center;
    margin-top: 30px;
    padding-top: 20px;
    border-top: 1px solid var(--line);
  }
  .closing .site {
    margin: 0;
    font-family: "Fraunces", ui-serif, Georgia, serif;
    font-size: 26px;
    font-weight: 600;
  }
  .closing .muted { margin: 8px 0 0; font-size: 14px; }
  .closing .signoff {
    margin: 18px 0 0;
    font-size: 14px;
    font-style: italic;
    color: var(--ink-soft);
  }

  @media (max-width: 420px) {
    .way { flex: 1 1 100%; }
  }

  @media print {
    :root { color-scheme: light; }
    body { background: #fff; }
    .hero, .way { box-shadow: none; }
  }
</style>
</head>
<body>
<div class="page">

  <figure class="hero">
    <img src="<?= e(asset_url('assets/img/shepherd.webp')) ?>"
         alt="&ldquo;Shall Not Want,&rdquo; a painting of the Good Shepherd walking among his flock">
  </figure>
  <p class="credit">&ldquo;Shall Not Want&rdquo; by Yongsung Kim</p>

  <header class="intro">
    <h1>Fantasy General Conference</h1>
    <p class="tagline">Make your predictions, then watch conference to see how you did.</p>
  </header>

  <section class="block">
    <h2>Two ways to play</h2>
    <div class="way-grid">
      <div class="way">
        <h3>On paper</h3>
        <p>Grab a paper sheet, fill in your best guesses, and hand it back before conference starts. Your leader will enter it for you.</p>
      </div>
      <div class="way">
        <h3>Online</h3>
        <p>Go to <strong><?= e($siteHost) ?></strong> and tap <strong>Make new picks</strong>. Type your name, make your guesses, and you&rsquo;re in.</p>
      </div>
    </div>
    <p class="note">Just one sheet per person, paper or online.</p>
  </section>

  <section class="block">
    <h2>While you&rsquo;re watching</h2>
    <p>Every session you actually watch earns points too &mdash; so keep going even after picks lock.</p>
    <div class="way-grid">
      <div class="way">
        <h3>No internet?</h3>
        <p>Just text <strong>Bishop Reed</strong> or <strong>Porter</strong> how many sessions you watched.</p>
      </div>
      <div class="way">
        <h3>Online</h3>
        <p>Check off each session at <strong><?= e($siteHost) ?></strong> as you watch it.</p>
      </div>
    </div>
  </section>

  <section class="block">
    <h2>Check the standings</h2>
    <p>
      Want to remember your picks? Curious who&rsquo;s ahead? Visit <?= e($siteHost) ?>
      any time during conference &mdash; even between sessions &mdash; to watch the
      leaderboard update. If you need your access code, Porter or Bishop Reed can
      get it for you.
    </p>
  </section>

  <footer class="closing">
    <p class="site"><a href="<?= e($siteUrl) ?>"><?= e($siteHost) ?></a></p>
    <p class="muted">Questions? Text Bishop Reed or Porter.</p>
    <p class="signoff">Good luck &mdash; and good listening!</p>
  </footer>

</div>
</body>
</html>
