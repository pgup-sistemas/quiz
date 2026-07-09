<?php
if (session_name() !== 'SUPER_ADMIN_SESS') {
    session_name('SUPER_ADMIN_SESS');
    session_start();
}
$_SESSION = [];
session_destroy();
header('Location: login.php');
exit;
