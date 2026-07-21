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

// Rate limit por IP (arquivo, independe de cookies/sessão): no máximo 20 novas sessões por minuto
$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$rlDir = sys_get_temp_dir() . '/pagequiz_rl';
if (!is_dir($rlDir)) @mkdir($rlDir, 0700, true);
$rlFile = $rlDir . '/start_' . preg_replace('/[^a-zA-Z0-9.:]/', '_', $ip) . '.json';
$now  = time();
$hits = [];
$fh = @fopen($rlFile, 'c+');
if ($fh && flock($fh, LOCK_EX)) {
    $raw = stream_get_contents($fh);
    $hits = $raw ? array_filter((json_decode($raw, true) ?: []), fn($t) => $t > $now - 60) : [];
    if (count($hits) >= 20) {
        flock($fh, LOCK_UN); fclose($fh);
        http_response_code(429);
        echo json_encode(['success' => false, 'message' => 'Muitas tentativas. Aguarde um instante e tente novamente.']);
        exit;
    }
    $hits[] = $now;
    ftruncate($fh, 0); rewind($fh);
    fwrite($fh, json_encode(array_values($hits)));
    flock($fh, LOCK_UN); fclose($fh);
}

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
    VALUES (?,?,?,?,?,?,0,0,0,0,0, NOW(), NOW())
", [$quizId, $companyId, $userId, $name, $email, $sector]);

$pid = dbLastId();
$_SESSION['current_participant_id'] = $pid;

echo json_encode([
    'success'        => true,
    'participant_id' => $pid
]);
