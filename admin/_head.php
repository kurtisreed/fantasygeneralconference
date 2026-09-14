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
function admin_guard(): array
{
    $admin = require_admin();
    $event = get_event();
    if (!$event) {
        exit('No event set up yet. Run tools/install.php.');
    }
    return [$admin, $event];
}

/** Emit the page head and admin nav. Call only after POST handling is done. */
function admin_chrome(string $title, string $current = ''): void
{
    page_head('Admin · ' . $title, '../');
    $nav = [
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
}
