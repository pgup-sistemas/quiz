<?php
require_once __DIR__ . '/db.php';

define('USER_SESS', 'pageup_user');

function userSessionStart(): void {
    if (session_status() === PHP_SESSION_NONE) session_start();
}

function isUserLoggedIn(): bool {
    userSessionStart();
    return !empty($_SESSION[USER_SESS]['id']);
}

function currentUser(): ?array {
    userSessionStart();
    return $_SESSION[USER_SESS] ?? null;
}

function userRegister(string $name, string $email, string $pass, string $sector = ''): true|string {
    $email = strtolower(trim($email));
    if (dbRow("SELECT id FROM users WHERE email = ?", [$email])) {
        return 'Este e-mail já está cadastrado.';
    }
    $hash = password_hash($pass, PASSWORD_DEFAULT);
    dbExec("INSERT INTO users (name, email, password_hash, sector) VALUES (?,?,?,?)",
        [trim($name), $email, $hash, trim($sector)]);
    return true;
}

function userLogin(string $email, string $pass): bool {
    $email = strtolower(trim($email));
    $user  = dbRow("SELECT * FROM users WHERE email = ? AND active = 1", [$email]);
    if (!$user || !password_verify($pass, $user['password_hash'])) return false;
    userSessionStart();
    $_SESSION[USER_SESS] = [
        'id'     => $user['id'],
        'name'   => $user['name'],
        'email'  => $user['email'],
        'sector' => $user['sector'],
    ];
    dbExec("UPDATE users SET last_login = datetime('now','localtime') WHERE id = ?", [$user['id']]);
    return true;
}

function userLogout(): void {
    userSessionStart();
    unset($_SESSION[USER_SESS]);
}

function generateResetToken(string $email): string|false {
    $email = strtolower(trim($email));
    $user  = dbRow("SELECT id FROM users WHERE email = ? AND active = 1", [$email]);
    if (!$user) return false;
    $token   = bin2hex(random_bytes(32));
    $expires = date('Y-m-d H:i:s', time() + 3600);
    dbExec("UPDATE users SET reset_token = ?, reset_expires = ? WHERE id = ?",
        [$token, $expires, $user['id']]);
    return $token;
}

function validateResetToken(string $token): ?array {
    return dbRow(
        "SELECT * FROM users WHERE reset_token = ? AND reset_expires > datetime('now','localtime')",
        [$token]
    ) ?: null;
}

function resetPassword(string $token, string $newPass): bool {
    $user = validateResetToken($token);
    if (!$user) return false;
    $hash = password_hash($newPass, PASSWORD_DEFAULT);
    dbExec("UPDATE users SET password_hash = ?, reset_token = NULL, reset_expires = NULL WHERE id = ?",
        [$hash, $user['id']]);
    return true;
}

function userUpdateProfile(int $id, string $name, string $sector): void {
    dbExec("UPDATE users SET name = ?, sector = ? WHERE id = ?", [trim($name), trim($sector), $id]);
    userSessionStart();
    if (!empty($_SESSION[USER_SESS]['id']) && $_SESSION[USER_SESS]['id'] == $id) {
        $_SESSION[USER_SESS]['name']   = trim($name);
        $_SESSION[USER_SESS]['sector'] = trim($sector);
    }
}

function userChangePassword(int $id, string $currentPass, string $newPass): bool {
    $user = dbRow("SELECT password_hash FROM users WHERE id = ?", [$id]);
    if (!$user || !password_verify($currentPass, $user['password_hash'])) return false;
    dbExec("UPDATE users SET password_hash = ? WHERE id = ?",
        [password_hash($newPass, PASSWORD_DEFAULT), $id]);
    return true;
}
