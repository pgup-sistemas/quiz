<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/tenant.php';

session_start();
require_once __DIR__ . '/../includes/user-auth.php';
$tenant = resolveTenant();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método inválido']); exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) { $input = $_POST; }

$quizId = (int)($input['quiz_id'] ?? 0);
$name   = trim($input['name']     ?? '');
$email  = trim($input['email']    ?? '');
$sector = trim($input['sector']   ?? '');

if (!$quizId || !$name || !$sector) {
    echo json_encode(['success' => false, 'message' => 'Dados incompletos']); exit;
}

$companyId = $tenant
    ? (int)$tenant['id']
    : (int)(dbRow("SELECT company_id FROM quizzes WHERE id=?", [$quizId])['company_id'] ?? 0);
if (!$companyId) {
    echo json_encode(['success' => false, 'message' => 'Empresa não identificada']); exit;
}

// Vincula ao user do portal se estiver autenticado
$loggedUser = currentUser();
$userId     = $loggedUser ? (int)$loggedUser['id'] : null;

// Quando há usuário logado e o email não foi enviado, usa o e-mail da conta
if ($userId && !$email && !empty($loggedUser['email'])) {
    $email = $loggedUser['email'];
}

dbExec("
    INSERT INTO participants (quiz_id, company_id, user_id, name, email, sector, score, total_questions, percentage, passed, avg_time, started_at, last_activity)
    VALUES (?,?,?,?,?,?,0,0,0,0,0, datetime('now','localtime'), datetime('now','localtime'))
", [$quizId, $companyId, $userId, $name, $email, $sector]);

$pid = dbLastId();
$_SESSION['current_participant_id'] = $pid;

echo json_encode([
    'success'        => true,
    'participant_id' => $pid
]);
