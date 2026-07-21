<?php
require_once __DIR__ . '/db.php';

if (!defined('SUPER_ADMIN_SESS')) define('SUPER_ADMIN_SESS', 'SUPER_ADMIN_SESS');

function superAdminLogin(string $username, string $password): bool {
    $row = dbRow("SELECT * FROM super_admins WHERE username = ? AND active = 1", [$username]);
    if (!$row || !password_verify($password, $row['password_hash'])) return false;

    if (session_name() !== SUPER_ADMIN_SESS) {
        if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
        session_name(SUPER_ADMIN_SESS);
        session_start();
    }

    $_SESSION[SUPER_ADMIN_SESS] = [
        'id'   => (int)$row['id'],
        'name' => $row['name'],
        'user' => $row['username'],
    ];

    logAudit('login', 0);
    return true;
}

function requireSuperAdmin(): void {
    if (session_name() !== SUPER_ADMIN_SESS) {
        if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
        session_name(SUPER_ADMIN_SESS);
        session_start();
    }
    if (empty($_SESSION[SUPER_ADMIN_SESS]['id'])) {
        header('Location: ' . rtrim(dirname($_SERVER['SCRIPT_NAME']), '/') . '/login.php');
        exit;
    }
}

function superAdminId(): int {
    return (int)($_SESSION[SUPER_ADMIN_SESS]['id'] ?? 0);
}

function superAdminName(): string {
    return $_SESSION[SUPER_ADMIN_SESS]['name'] ?? '';
}

function isSuperAdminLoggedIn(): bool {
    return !empty($_SESSION[SUPER_ADMIN_SESS]['id']);
}

/**
 * Registra uma ação no audit_log.
 */
function logAudit(string $action, int $companyId, string $detail = ''): void {
    $actorId = superAdminId();
    $ip      = $_SERVER['REMOTE_ADDR'] ?? '';
    dbExec(
        "INSERT INTO audit_log (actor_type, actor_id, action, target_company_id, ip, detail) VALUES (?,?,?,?,?,?)",
        ['super_admin', $actorId, $action, $companyId ?: null, $ip, $detail]
    );
}
