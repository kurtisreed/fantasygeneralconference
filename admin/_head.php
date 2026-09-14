<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/lib/bootstrap.php';
require_once APP_ROOT . '/lib/questions.php';
require_once APP_ROOT . '/lib/scoring.php';
require_once APP_ROOT . '/lib/auth.php';

/**
 * Authenticate and load the event WITHOUT printing anything.
 *
 * Every admin page must call this, handle its POST, and only then call
 * admin_chrome(). Printing before the POST handler makes redirect() a no-op
 * wherever output buffering is off, which silently strands the save.
 */
/**
 * Authenticate and work out which conference is being worked on.
 *
 * The choice lives in the session so the nav links stay plain, and ?event=<id>
 * sets it so a link to a particular conference works. It falls back to the
 * newest conference, which is the right answer almost every time.
 */
function admin_guard(): array
{
    $admin = require_admin();

    if (isset($_GET['event'])) {
        $picked = get_event_by_id((int)$_GET['event']);
        if ($picked) {
            $_SESSION['admin_event_id'] = (int)$picked['id'];
        }
    }

    $event = null;
    if (!empty($_SESSION['admin_event_id'])) {
        $event = get_event_by_id((int)$_SESSION['admin_event_id']);
        if (!$event) {
            unset($_SESSION['admin_event_id']);   // it was deleted underneath us
        }
    }
    $event ??= get_event();

    if (!$event) {
        exit('No conference set up yet. Run tools/install.php.');
    }
    $_SESSION['admin_event_id'] = (int)$event['id'];
    return [$admin, $event];
}

/**
 * Emit the page head and admin nav. Call only after POST handling is done.
 *
 * Pass the event so every page says which conference you are editing — without
 * it, working on a past conference is indistinguishable from working on the
 * current one.
 */
function admin_chrome(string $title, string $current = '', ?array $event = null): void
{
    page_head('Admin · ' . $title, '../');

    $nav = [
        'conferences.php' => 'Conferences',
        'index.php'      => 'Dashboard',
        'results.php'    => 'Results',
        'players.php'    => 'Players',
        'questions.php'  => 'Questions',
        'event.php'      => 'Event',
    ];
    echo '<nav class="adminnav">';
    foreach ($nav as $href => $label) {
        $cls = $href === $current ? ' class="on"' : '';
        echo '<a' . $cls . ' href="' . e($href) . '">' . e($label) . '</a>';
    }
    echo '<a class="right" href="logout.php">Sign out</a>';
    echo '</nav>';

    if ($event !== null) {
        $newest = get_event();
        $isCurrent = $newest && (int)$newest['id'] === (int)$event['id'];
        echo '<div class="workingon' . ($isCurrent ? '' : ' past') . '">';
        echo '<span class="workingon-label">' . ($isCurrent ? 'Working on' : 'Editing a past conference') . '</span>';
        echo '<strong>' . e($event['name']) . '</strong>';
        echo '<a class="right" href="conferences.php">Switch</a>';
        echo '</div>';
    }
}
