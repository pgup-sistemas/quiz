<?php
require_once __DIR__ . '/config.php';

function sessionStart(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_name(ADMIN_SESS);
        session_start();
    }
}

function isLoggedIn(): bool {
    sessionStart();
    return !empty($_SESSION['admin_id']);
}

function requireLogin(): void {
    if (!isLoggedIn()) {
        header('Location: ' . adminUrl('login.php'));
        exit;
    }
}

function adminLogin(string $username, string $password): bool {
    require_once __DIR__ . '/db.php';
    $admin = dbRow("SELECT * FROM admins WHERE username = ?", [$username]);
    if ($admin && password_verify($password, $admin['password_hash'])) {
        sessionStart();
        session_regenerate_id(true); // Prevent session fixation
        $_SESSION['admin_id']   = $admin['id'];
        $_SESSION['admin_name'] = $admin['name'];
        $_SESSION['admin_user'] = $admin['username'];
        return true;
    }
    return false;
}

function adminLogout(): void {
    sessionStart();
    session_destroy();
}

function adminName(): string {
    sessionStart();
    return $_SESSION['admin_name'] ?? 'Admin';
}

function adminUrl(string $page = 'index.php'): string {
    $base = rtrim(str_replace('\\', '/', dirname(dirname($_SERVER['SCRIPT_NAME']))), '/');
    return $base . '/admin/' . $page;
}

function siteUrl(string $page = ''): string {
    $base = rtrim(str_replace('\\', '/', dirname(dirname($_SERVER['SCRIPT_NAME']))), '/');
    return $base . ($page ? '/' . ltrim($page, '/') : '');
}

function absoluteUrl(string $page = ''): string {
    if (defined('BASE_URL') && BASE_URL) {
        return rtrim(BASE_URL, '/') . ($page ? '/' . ltrim($page, '/') : '');
    }
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || ($_SERVER['SERVER_PORT'] ?? 80) == 443) ? "https://" : "http://";
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return $protocol . $host . siteUrl($page);
}

function redirect(string $url): void {
    header('Location: ' . $url);
    exit;
}

function e(mixed $v): string {
    return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function flash(string $msg, string $type = 'success'): void {
    sessionStart();
    $_SESSION['flash'] = ['msg' => $msg, 'type' => $type];
}

function getFlash(): ?array {
    sessionStart();
    $f = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $f;
}
