<?php
require_once __DIR__ . '/../includes/user-auth.php';
userLogout();
header('Location: ../index.php');
exit;
