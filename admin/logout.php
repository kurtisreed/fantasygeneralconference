<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/lib/bootstrap.php';
require_once APP_ROOT . '/lib/auth.php';
admin_logout();
redirect('login.php');
