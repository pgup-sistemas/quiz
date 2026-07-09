<?php
if (session_name() !== 'SUPER_ADMIN_SESS') {
    session_name('SUPER_ADMIN_SESS');
    session_start();
}
require_once __DIR__ . '/../includes/superadmin-auth.php';
requireSuperAdmin();
require_once __DIR__ . '/../includes/db.php';

// Encerrar impersonation
if (isset($_GET['stop'])) {
    // Destruir sessão admin temporária
    session_write_close();
    session_name('pageup_admin');
    session_start();
    session_destroy();

    // Voltar para superadmin
    session_write_close();
    session_name('SUPER_ADMIN_SESS');
    session_start();
    header('Location: companies.php');
    exit;
}

$companyId = (int)($_GET['company_id'] ?? 0);
if (!$companyId) { header('Location: companies.php'); exit; }

$company = dbRow("SELECT * FROM companies WHERE id=?", [$companyId]);
if (!$company) { header('Location: companies.php'); exit; }

// Busca o primeiro admin da empresa
$admin = dbRow("SELECT * FROM admins WHERE company_id=? LIMIT 1", [$companyId]);
if (!$admin) {
    session_write_close();
    session_name('SUPER_ADMIN_SESS');
    session_start();
    header('Location: companies.php?_msg=' . urlencode('Esta empresa não possui admin cadastrado.'));
    exit;
}

logAudit('impersonate', $companyId, json_encode(['admin_id' => $admin['id'], 'admin_email' => $admin['username']]));

// Inicia sessão admin com os dados da empresa
session_write_close();
session_name('pageup_admin');
session_start();
$_SESSION['pageup_admin'] = [
    'id'         => (int)$admin['id'],
    'name'       => $admin['name'],
    'username'   => $admin['username'],
    'company_id' => $companyId,
];
// Marca impersonation para exibir banner
$_SESSION['impersonating_company_id']       = $companyId;
$_SESSION['impersonating_company_name']     = $company['name'];
$_SESSION['impersonating_super_admin_id']   = superAdminId();

header('Location: ../admin/index.php');
exit;
