<?php
require_once __DIR__ . '/env.php';
loadEnvFile(__DIR__ . '/../.env');
loadEnvFile(__DIR__ . '/../.env.production');

define('APP_DEBUG',  false);
define('DB_HOST', getenv('DB_HOST') ?: '127.0.0.1');
define('DB_NAME', getenv('DB_NAME') ?: 'pagequiz');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');
define('BASE_URL',   'https://quiz.pageup.net.br');
define('SITE_NAME',  'PageQuiz');
define('SITE_BRAND', 'PageUp Sistemas');
define('PRIMARY',    '#008bcd');
define('ADMIN_SESS',       'pageup_admin');
define('SUPER_ADMIN_SESS', 'SUPER_ADMIN_SESS');

// Credenciais do admin padrão — lidas de variável de ambiente para não versionar senha
// Em prod: defina PAGEQUIZ_ADMIN_USER e PAGEQUIZ_ADMIN_PASS no ambiente do servidor
define('DEFAULT_ADMIN_USER', getenv('PAGEQUIZ_ADMIN_USER') ?: 'admin');
define('DEFAULT_ADMIN_PASS', getenv('PAGEQUIZ_ADMIN_PASS') ?: 'changeme_on_first_login');

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
