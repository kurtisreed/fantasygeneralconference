<?php
/**
 * The printable sheet. Built for the browser's own Print → Save as PDF rather
 * than a PDF library: no dependency to install on shared hosting, and the
 * typography and page breaks are easier to control in CSS.
 */
require_once __DIR__ . '/_head.php';
[$admin, $event] = admin_guard();
$eventId = (int)$event['id'];

$questions = get_questions($eventId);
$sections  = group_by_section($questions);
$possible  = total_points_possible($questions);
$copies    = max(1, min(30, query_int('copies', 1)));
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Print · <?= e($event['name']) ?></title>
<link rel="stylesheet" href="<?= e(asset_url('assets/print.css', '../')) ?>">
</head>
<body>

<div class="toolbar no-print">
  <div>
    <strong><?= e($event['name']) ?></strong> &mdash; printable sheet, <?= $possible ?> points
  </div>
  <form method="get" class="toolbar-form">
    <label for="copies">Copies</label>
    <input type="number" id="copies" name="copies" min="1" max="30" value="<?= $copies ?>">
    <button type="submit" class="btn">Update</button>
    <button type="button" class="btn btn-primary" onclick="window.print()">Print / Save as PDF</button>
    <a class="btn" href="index.php">Back to admin</a>
  </form>
  <p class="toolbar-hint">
    In the print dialog: <strong>Save as PDF</strong>, margins <strong>Default</strong>,
    <strong>Background graphics on</strong> so the boxes print, and
    <strong>Headers and footers off</strong> &mdash; those add the date and page title
    to every page and push the sheet onto a third page.
  </p>
</div>

<?php for ($copy = 0; $copy < $copies; $copy++): ?>
<article class="sheet">

  <header class="sheet-head">
    <div>
      <p class="sheet-kicker">Fantasy General Conference</p>
      <h1><?= e($event['name']) ?></h1>
    </div>
    <div class="sheet-score">
      <span class="sheet-score-label">Total</span>
      <span class="sheet-score-box"></span>
      <span class="sheet-score-of">of <?= $possible ?></span>
    </div>
  </header>

  <div class="sheet-name">
    <span class="fill-label">Name</span><span class="fill-line"></span>
  </div>

  <p class="sheet-intro">
    Fill in <strong>one box per row</strong>. Hand it back when you&rsquo;re done and
    the scorekeeper will type it in.
  </p>

  <?php
  // Two pages, split deliberately: the grid, conducting and first speaker on
  // page one, the rest on page two. Left to the browser it spilled onto three.
  $gridQs = $sections['apostles'] ?? [];
  $rest   = array_filter(
      $sections,
      static fn($k) => $k !== 'apostles' && $k !== 'watched',
      ARRAY_FILTER_USE_KEY
  );
  ?>

  <?php if ($gridQs): ?>
    <section class="p-section">
      <h2><?= e(section_info('apostles')['title']) ?>
        <span class="p-pts"><?= total_points_possible($gridQs) ?> pts</span>
      </h2>
      <table class="p-grid">
        <thead>
          <tr>
            <th class="p-grid-name"></th>
            <?php foreach ($gridQs[0]['options'] as $o): ?>
              <th><?= e($o['label']) ?></th>
            <?php endforeach; ?>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($gridQs as $qq): ?>
          <tr>
            <th class="p-grid-name"><?= e($qq['prompt']) ?></th>
            <?php foreach ($qq['options'] as $o): ?>
              <td><span class="box"></span></td>
            <?php endforeach; ?>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </section>
  <?php endif; ?>

  <?php foreach ($rest as $key => $qs): ?>
    <section class="p-section <?= $key === 'first_speaker' ? 'page-break' : '' ?>">
      <h2><?= e(section_info($key)['title']) ?>
        <span class="p-pts"><?= total_points_possible($qs) ?> pts</span>
      </h2>
      <?php foreach ($qs as $qq): ?>
        <div class="p-q">
          <p class="p-prompt">
            <?= e($qq['prompt']) ?>
            <span class="p-pts"><?= (int)$qq['points'] ?> pt<?= $qq['points'] == 1 ? '' : 's' ?></span>
          </p>

          <?php if ($qq['type'] === 'over_under'): ?>
            <div class="p-choices">
              <span class="p-choice"><span class="box"></span>Under <?= e((string)($qq['config']['line'] ?? '?')) ?></span>
              <span class="p-choice"><span class="box"></span>Over <?= e((string)($qq['config']['line'] ?? '?')) ?></span>
            </div>

          <?php elseif ($qq['type'] === 'number'): ?>
            <div class="p-choices"><span class="fill-line short"></span></div>

          <?php elseif ($qq['type'] === 'pick_one'): ?>
            <div class="p-choices <?= count($qq['options']) > 6 ? 'p-choices-tight' : '' ?>">
              <?php foreach ($qq['options'] as $o): ?>
                <span class="p-choice"><span class="box"></span><?= e($o['label']) ?></span>
              <?php endforeach; ?>
            </div>

          <?php else: ?>
            <div class="p-choices"><span class="fill-line"></span></div>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </section>
  <?php endforeach; ?>

  <footer class="sheet-foot">
    <div class="sheet-watched">
      <span class="fill-label">Sessions watched (1 pt each)</span><span class="fill-line short"></span>
    </div>
    <p class="sheet-slug"><?= e($event['slug']) ?></p>
  </footer>

</article>
<?php endfor; ?>

</body>
</html>
