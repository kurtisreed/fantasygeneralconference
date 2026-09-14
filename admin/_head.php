<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/lib/bootstrap.php';
require_once APP_ROOT . '/lib/questions.php';
require_once APP_ROOT . '/lib/scoring.php';
require_once APP_ROOT . '/lib/auth.php';

function admin_page(string $title, string $current = ''): array
{
    $admin = require_admin();
    $event = get_event();
    if (!$event) {
        exit('No event set up yet. Run tools/install.php.');
    }
    page_head('Admin · ' . $title, '../');
    $nav = [
        'index.php'      => 'Dashboard',
        'results.php'    => 'Results',
        'adjudicate.php' => 'Text answers',
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
    return [$admin, $event];
}
