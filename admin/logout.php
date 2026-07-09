<?php
require_once __DIR__ . '/../includes/auth.php';
adminLogout();
redirect('login.php');
