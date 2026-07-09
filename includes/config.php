<?php
define('APP_DEBUG',  false);
define('DB_PATH',    __DIR__ . '/../data/quiz.db');
define('BASE_URL',   'https://quiz.pageup.net.br');
define('SITE_NAME',  'PageQuiz');
define('SITE_BRAND', 'PageUp Sistemas');
define('PRIMARY',    '#008bcd');
define('ADMIN_SESS', 'pageup_admin');

// Default admin credentials (change after first login via DB)
define('DEFAULT_ADMIN_USER', 'admin');
define('DEFAULT_ADMIN_PASS', 'alphaclin2025');

// Quiz settings
define('DEFAULT_TIMER',      30);
define('DEFAULT_PASS_PCT',   70);
define('QUIZ_VERSION',       '1.1.0');

// Error reporting control
if (!APP_DEBUG) {
    error_reporting(0);
    ini_set('display_errors', 0);
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
}
