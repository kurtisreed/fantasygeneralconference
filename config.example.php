<?php
// Copy to config.php and fill in. config.php is gitignored.
return [
    'db' => [
        'host'   => '127.0.0.1',
        'port'   => 3306,
        'name'   => 'fantasygc',
        'user'   => 'root',
        'pass'   => '',
        'socket' => '/Applications/XAMPP/xamppfiles/var/mysql/mysql.sock', // null on shared hosting
    ],

    // One-time token required to run tools/install.php in a browser.
    // Change this to something random before uploading.
    'setup_token' => 'change-me-before-you-upload',

    // Shown in the page header.
    'site_name' => 'Fantasy General Conference',

    // Each group is served at <slug>.<base_domain>; the bare domain serves the
    // default group (the slug below). Leave base_domain out for one-group use.
    'base_domain'   => 'localhost',
    'default_group' => 'default',

    // Set true on shared hosting so cookies are HTTPS-only.
    'secure_cookies' => false,

    // Display PHP errors (local only).
    'debug' => true,
];
