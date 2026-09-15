<?php
/**
 * Count the over/under words without a shell.
 *
 * Shared hosting won't keep a script alive long enough to fetch thirty-five
 * talks, so this does a few per request and reloads itself until they're all
 * on disk, then counts them. Refreshing or closing the page loses nothing —
 * the cache is the progress.
 */
require_once __DIR__ . '/_head.php';
require_once APP_ROOT . '/lib/harvest.php';
[$admin, $event] = admin_guard();
$eventId = (int)$event['id'];

const BATCH      = 6;    // talks per request
const MAX_ROUNDS = 40;   // stop a runaway reload loop

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $conf = (string)post('conf');

    if (post('action') === 'save') {
        $patterns = harvest_patterns($eventId);
        $slugs    = harvest_slugs($conf);
        $counts   = harvest_count($conf, $slugs, $patterns);
        $n        = harvest_write_results($patterns, $counts['totals']);
        recompute_event_scores($eventId);
        flash("$n over/under results saved from $counts[talks] talks. Everyone rescored.");
        redirect('results.php');
    }

    if (post('action') === 'clear') {
        $n = harvest_clear_cache($conf);
        flash("Cleared $n cached files for $conf. Fetch again to start over.");
        redirect('harvest.php?conf=' . rawurlencode($conf));
    }
}

$conf  = isset($_GET['conf']) ? trim((string)$_GET['conf']) : harvest_conf_for_event($event);
$run   = isset($_GET['run']);
$round = query_int('round');

if (!preg_match('#^20\d\d/(0[41]|10)$#', $conf)) {
    $error = 'That is not a conference I recognise. Use the year and month, like 2026/10.';
    $conf  = harvest_conf_for_event($event);
    $run   = false;
}

$patterns = harvest_patterns($eventId);
$slugs    = [];
$step     = null;
$progress = ['have' => 0, 'total' => 0];

if (!$patterns) {
    $error = 'No over/under questions on this sheet carry a counting pattern, so there is nothing to count.';
} elseif ($run || harvest_is_cached($conf, "/general-conference/$conf")) {
    @set_time_limit(60);
    $slugs = harvest_slugs($conf);

    if (!$slugs) {
        $error = "Couldn't read the talk list for $conf. The Church site may not have posted it yet.";
    } elseif ($run) {
        if ($round >= MAX_ROUNDS) {
            $error = 'Gave up after ' . MAX_ROUNDS . ' rounds. Something is refusing to download — try Start over.';
        } else {
            $step = harvest_step($conf, $slugs, BATCH);
        }
    }
    $progress = harvest_progress($conf, $slugs);
}

$done    = $slugs && $progress['have'] >= $progress['total'];
$more    = $run && !$done && $error === null;
$nextUrl = 'harvest.php?conf=' . rawurlencode($conf) . '&run=1&round=' . ($round + 1);

admin_chrome('Word counts', 'harvest.php', $event);

// Reload to fetch the next batch. Kept out of the <head> so the progress
// above it paints first.
if ($more) {
    echo '<meta http-equiv="refresh" content="1;url=' . e($nextUrl) . '">';
}
?>
<?php if ($error): ?><div class="error"><?= e($error) ?></div><?php endif; ?>

<section class="hero compact">
  <h1>Word counts</h1>
  <p class="lede">
    Fetches every talk from the Church website and counts the over/under words.
    Talks post within a day or two of each session.
  </p>
</section>

<div class="card">
  <h2>Which conference?</h2>
  <form method="get" class="inline">
    <input type="text" name="conf" value="<?= e($conf) ?>" size="9"
           pattern="20\d\d/(04|10)" aria-label="Conference, like 2026/10" required>
    <input type="hidden" name="run" value="1">
    <button type="submit" class="btn btn-primary">
      <?= $progress['have'] > 0 ? 'Continue fetching' : 'Fetch the talks' ?>
    </button>
    <span class="muted">Year and month, like <code>2026/10</code>.</span>
  </form>
</div>

<?php if ($progress['total'] > 0): ?>
  <?php $pct = (int)round($progress['have'] / max(1, $progress['total']) * 100); ?>
  <div class="card">
    <h2><?= $done ? 'All talks downloaded' : 'Downloading talks' ?></h2>
    <div class="scored">
      <div class="scored-bar"><span style="width: <?= $pct ?>%"></span></div>
      <p class="scored-note">
        <strong><?= $progress['have'] ?></strong> of <?= $progress['total'] ?> talks
        <?php if ($more): ?>
          &middot; fetching <?= BATCH ?> at a time, this page reloads itself&hellip;
        <?php endif; ?>
      </p>
    </div>
    <?php if ($step && $step['failed']): ?>
      <p class="help">Couldn&rsquo;t download: <?= e(implode(', ', $step['failed'])) ?>. They&rsquo;ll be retried.</p>
    <?php endif; ?>
    <?php if ($more): ?>
      <p class="center"><a class="link" href="<?= e($nextUrl) ?>">Continue now</a></p>
    <?php endif; ?>
  </div>
<?php endif; ?>

<?php if ($done):
    $counts = harvest_count($conf, $slugs, $patterns); ?>
  <div class="card">
    <header class="section-head">
      <h2>Counts for <?= e($conf) ?></h2>
      <span class="pill"><?= number_format($counts['words']) ?> spoken words</span>
    </header>
    <p class="muted">
      Counted across <?= $counts['talks'] ?> talks, excluding footnotes, titles and bylines.
    </p>
    <table class="players">
      <thead><tr><th>Question</th><th>Line</th><th>Count</th><th>Result</th></tr></thead>
      <tbody>
      <?php foreach ($patterns as $key => $p):
          $n = $counts['totals'][$key] ?? 0;
          $line = $p['line'];
          $verdict = $line === null ? '—' : ($n > (float)$line ? 'OVER' : ($n < (float)$line ? 'UNDER' : 'push')); ?>
        <tr>
          <td><?= e($p['label']) ?></td>
          <td><?= e((string)($line ?? '—')) ?></td>
          <td class="strong"><?= $n ?></td>
          <td class="strong"><?= $verdict ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <div class="actions">
    <form method="post" onsubmit="return confirm('Save these as the results and rescore everyone?');">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="save">
      <input type="hidden" name="conf" value="<?= e($conf) ?>">
      <button type="submit" class="btn btn-primary">Save as results &amp; rescore</button>
    </form>
    <form method="post" onsubmit="return confirm('Delete the downloaded talks and fetch them again?');">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="clear">
      <input type="hidden" name="conf" value="<?= e($conf) ?>">
      <button type="submit" class="btn">Start over</button>
    </form>
  </div>

  <p class="muted center">
    Saving fills in only the over/under questions. Everything else is entered on
    <a class="link" href="results.php">Results</a>.
  </p>
<?php endif; ?>
<?php page_foot();
