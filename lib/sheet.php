<?php
declare(strict_types=1);

require_once __DIR__ . '/questions.php';

/**
 * The body of the pick sheet: every section, with the current answers filled in.
 *
 * Shared by the player's own sheet and the admin's paper-entry screen so the
 * two can't drift apart. $editable is false for a locked player view.
 *
 * $watched is the session count shown on the participation question. It's
 * never editable inline here — the player has their own self-report card
 * (see play.php) and the admin has a separate field (see admin/enter.php) —
 * this is just a readout, worded differently depending on who's looking.
 */
function render_sheet_sections(array $questions, array $answers, bool $editable, int $watched = 0, bool $selfReported = false): void
{
    $sections = group_by_section($questions);
    $dis      = $editable ? '' : 'disabled';

    foreach ($sections as $key => $qs):
        $m = section_info($key); ?>
      <section class="card section" id="sec-<?= e($key) ?>">
        <header class="section-head">
          <h2><?= e($m['title']) ?></h2>
          <span class="pill"><?= total_points_possible($qs) ?> pts</span>
        </header>
        <p class="muted"><?= e($m['blurb']) ?></p>

        <?php if ($key === 'apostles'): ?>
          <div class="grid-head">
            <span></span>
            <?php foreach ($qs[0]['options'] as $o): ?>
              <span class="grid-col"><?= e($o['label']) ?></span>
            <?php endforeach; ?>
            <span></span><?php // matches the clear button's column ?>
          </div>
          <?php foreach ($qs as $qq):
              $qid = (int)$qq['id'];
              $cur = $answers[$qid] ?? ''; ?>
            <div class="grid-row">
              <span class="grid-name"><?= e($qq['prompt']) ?></span>
              <div class="grid-choices" role="radiogroup" aria-label="<?= e($qq['prompt']) ?>">
                <?php foreach ($qq['options'] as $o): ?>
                  <label class="chip">
                    <input type="radio" name="q[<?= $qid ?>]" value="<?= e($o['value']) ?>"
                           <?= $cur === $o['value'] ? 'checked' : '' ?> <?= $dis ?>>
                    <span><?= e($o['label']) ?></span>
                  </label>
                <?php endforeach; ?>
                <button type="button" class="clear-row" data-q="<?= $qid ?>"
                        <?= $dis ?> title="Clear this row">&times;</button>
              </div>
            </div>
          <?php endforeach; ?>

        <?php else: ?>
          <?php foreach ($qs as $qq):
              $qid = (int)$qq['id'];
              $cur = $answers[$qid] ?? ''; ?>
            <div class="q">
              <label class="q-prompt" for="q<?= $qid ?>">
                <?= e($qq['prompt']) ?>
                <span class="q-pts"><?= (int)$qq['points'] ?> pt<?= $qq['points'] == 1 ? '' : 's' ?></span>
              </label>
              <?php if (!empty($qq['help_text'])): ?>
                <p class="help"><?= e($qq['help_text']) ?></p>
              <?php endif; ?>

              <?php if ($qq['type'] === 'watched'): ?>
                <p class="help locked-note">
                  Currently recorded: <strong><?= $watched ?></strong>.
                  <?= $selfReported
                      ? 'Update it in the Sessions watched card below.'
                      : 'The scorekeeper fills this in when the sheets are scored.' ?>
                </p>

              <?php elseif ($qq['type'] === 'pick_one'): ?>
                <?php if (count($qq['options']) > 6): // long name lists scroll forever as pills ?>
                  <select id="q<?= $qid ?>" name="q[<?= $qid ?>]" <?= $dis ?>>
                    <option value="">— pick one —</option>
                    <?php foreach ($qq['options'] as $o): ?>
                      <option value="<?= e($o['value']) ?>" <?= $cur === $o['value'] ? 'selected' : '' ?>><?= e($o['label']) ?></option>
                    <?php endforeach; ?>
                  </select>
                <?php else: ?>
                  <div class="choices <?= count($qq['options']) > 3 ? 'choices-wide' : '' ?>">
                    <?php foreach ($qq['options'] as $o): ?>
                      <label class="chip">
                        <input type="radio" name="q[<?= $qid ?>]" value="<?= e($o['value']) ?>"
                               <?= $cur === $o['value'] ? 'checked' : '' ?> <?= $dis ?>>
                        <span><?= e($o['label']) ?></span>
                      </label>
                    <?php endforeach; ?>
                  </div>
                <?php endif; ?>

              <?php elseif ($qq['type'] === 'over_under'): ?>
                <div class="choices ou">
                  <?php foreach ([['under', 'Under'], ['over', 'Over']] as [$v, $lab]): ?>
                    <label class="chip chip-ou">
                      <input type="radio" name="q[<?= $qid ?>]" value="<?= $v ?>"
                             <?= $cur === $v ? 'checked' : '' ?> <?= $dis ?>>
                      <span><?= $lab ?> <strong><?= e((string)($qq['config']['line'] ?? '?')) ?></strong></span>
                    </label>
                  <?php endforeach; ?>
                </div>

              <?php elseif ($qq['type'] === 'number'): ?>
                <input type="number" id="q<?= $qid ?>" name="q[<?= $qid ?>]"
                       min="<?= (int)($qq['config']['min'] ?? 0) ?>"
                       max="<?= (int)($qq['config']['max'] ?? 99) ?>"
                       inputmode="numeric" value="<?= e($cur) ?>" <?= $dis ?>>

              <?php else: ?>
                <input type="text" id="q<?= $qid ?>" name="q[<?= $qid ?>]" maxlength="60"
                       value="<?= e($cur) ?>" <?= $dis ?>>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </section>
    <?php endforeach;
}

/**
 * Save a posted sheet for one player. Returns how many answers were stored.
 *
 * Used by both the player's own submit and the admin entering a paper sheet.
 */
function save_sheet(int $playerId, array $questions, array $posted): int
{
    $pdo = db();
    $owns = !$pdo->inTransaction();
    if ($owns) {
        $pdo->beginTransaction();
    }

    $set = $pdo->prepare(
        'INSERT INTO answers (player_id, question_id, value) VALUES (?,?,?)
         ON DUPLICATE KEY UPDATE value = VALUES(value)'
    );
    $del = $pdo->prepare('DELETE FROM answers WHERE player_id = ? AND question_id = ?');
    $kept = 0;

    foreach ($questions as $qq) {
        if ($qq['type'] === 'watched') {
            continue;  // the scorekeeper records this afterwards
        }
        $qid = (int)$qq['id'];
        $raw = $posted[$qid] ?? '';
        $val = is_string($raw) ? trim($raw) : '';

        if ($val === '') {
            $del->execute([$playerId, $qid]);
            continue;
        }
        if ($qq['type'] === 'number') {
            $val = (string)max(0, (int)$val);
        }
        // Never store a choice that isn't on the list.
        if ($qq['type'] === 'pick_one' && !in_array($val, array_column($qq['options'], 'value'), true)) {
            $del->execute([$playerId, $qid]);
            continue;
        }
        if ($qq['type'] === 'over_under' && !in_array($val, ['over', 'under'], true)) {
            $del->execute([$playerId, $qid]);
            continue;
        }

        $set->execute([$playerId, $qid, mb_substr($val, 0, 255)]);
        $kept++;
    }

    exec_sql('UPDATE players SET submitted_at = COALESCE(submitted_at, NOW()) WHERE id = ?', [$playerId]);

    if ($owns) {
        $pdo->commit();
    }
    return $kept;
}
