<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/db.php';

session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'Método inválido']); exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) { $input = $_POST; }

$pid    = (int)($input['participant_id'] ?? ($_SESSION['current_participant_id'] ?? 0));
$quizId = (int)($input['quiz_id']        ?? 0);

if (!$pid || !$quizId) {
    echo json_encode(['error' => 'Sessão não encontrada ou dados incompletos']); exit;
}

// Garante que o participant_id pertence à sessão atual
$sessionPid = (int)($_SESSION['current_participant_id'] ?? 0);
if (!$sessionPid || $pid !== $sessionPid) {
    echo json_encode(['error' => 'Sessão inválida']); exit;
}

// Busca e valida o participante: deve pertencer ao quiz informado
$p = dbRow("SELECT * FROM participants WHERE id = ? AND quiz_id = ?", [$pid, $quizId]);
if (!$p) {
    echo json_encode(['error' => 'Participante não encontrado']); exit;
}
$companyId = (int)$p['company_id'];

// Já finalizado — retorna os dados gravados sem recalcular
if (!empty($p['completed_at'])) {
    $qPct = (int)(dbRow("SELECT pass_percentage FROM quizzes WHERE id = ? AND company_id = ?", [$quizId, $companyId])['pass_percentage'] ?? 0);
    echo json_encode([
        'ok'              => true,
        'participant_id'  => $pid,
        'verify_code'     => $p['verify_code'] ?? strtoupper(substr(md5($pid . 'pageup'), 0, 8)),
        'score'           => (int)$p['score'],
        'total'           => (int)$p['total_questions'],
        'percentage'      => (float)$p['percentage'],
        'passed'          => (bool)$p['passed'],
        'pass_percentage' => $qPct,
    ]);
    exit;
}

// Busca o quiz — valida que pertence ao mesmo tenant do participante
$quiz = dbRow("SELECT pass_percentage FROM quizzes WHERE id = ? AND company_id = ?", [$quizId, $companyId]);
if (!$quiz) {
    echo json_encode(['error' => 'Quiz não encontrado']); exit;
}

// Recalcula resultado final a partir das respostas gravadas
$stats = dbRow("
    SELECT COUNT(*) AS total_q, SUM(is_correct) AS total_c, AVG(time_taken) AS avg_t
    FROM answers
    WHERE participant_id = ?
", [$pid]);

$total   = (int)$stats['total_q'];
$correct = (int)$stats['total_c'];
$avgTime = (float)$stats['avg_t'];
$pct     = $total > 0 ? round(($correct / $total) * 100, 1) : 0;
$passed  = $pct >= (int)$quiz['pass_percentage'] ? 1 : 0;

// Finaliza o registro com código de verificação criptograficamente seguro
$vCode = strtoupper(bin2hex(random_bytes(4)));
dbExec("
    UPDATE participants
    SET score = ?, total_questions = ?, percentage = ?, passed = ?,
        avg_time = ?, completed_at = NOW(), verify_code = ?
    WHERE id = ? AND company_id = ?
", [$correct, $total, $pct, $passed, $avgTime, $vCode, $pid, $companyId]);

// Limpa sessão de participante ativo
unset($_SESSION['current_participant_id']);
$_SESSION['last_quiz_submit'] = time();

echo json_encode([
    'ok'              => true,
    'participant_id'  => $pid,
    'verify_code'     => $vCode,
    'score'           => $correct,
    'total'           => $total,
    'percentage'      => $pct,
    'passed'          => (bool)$passed,
    'pass_percentage' => (int)$quiz['pass_percentage'],
]);
