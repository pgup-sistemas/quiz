<?php
session_name('pageup_admin');
session_start();
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/efi.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../admin/billing.php'); exit;
}

$companyId = adminCompanyId();
$subId     = (int)($_POST['sub_id'] ?? 0);

$sub = dbRow("SELECT * FROM subscriptions WHERE id=? AND company_id=?", [$subId, $companyId]);
if (!$sub) {
    header('Location: ../admin/billing.php?error=not_found'); exit;
}

try {
    if ($sub['efi_subscription_id']) {
        efiCancelSubscription($sub['efi_subscription_id']);
    }
    dbExec("UPDATE subscriptions SET status='cancelled', updated_at=datetime('now','localtime') WHERE id=?", [$subId]);
    dbExec("INSERT INTO audit_log (actor_type, actor_id, action, target_company_id, detail) VALUES (?,?,?,?,?)",
           ['admin', adminId(), 'subscription_cancelled', $companyId, json_encode(['sub_id'=>$subId])]);

    header('Location: ../admin/billing.php?cancelled=1'); exit;
} catch (Throwable $e) {
    header('Location: ../admin/billing.php?error=' . urlencode($e->getMessage())); exit;
}
