<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/db.php';

session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método inválido']); exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) { $input = $_POST; }

$pid      = (int)($input['participant_id']  ?? 0);
$q_id     = (int)($input['question_id']     ?? 0);
$selected = (int)($input['selected_answer'] ?? -1);
$correct  = (int)($input['is_correct']      ?? 0);
$time     = (int)($input['time_taken']      ?? 0);

if (!$pid || !$q_id) {
    echo json_encode(['success' => false, 'message' => 'Dados incompletos']); exit;
}

// Garante que o participant_id pertence à sessão atual
$sessionPid = (int)($_SESSION['current_participant_id'] ?? 0);
if (!$sessionPid || $pid !== $sessionPid) {
    echo json_encode(['success' => false, 'message' => 'Sessão inválida']); exit;
}

// Carrega o participant para derivar company_id (também confirma que existe)
$participant = dbRow("SELECT id, quiz_id, company_id FROM participants WHERE id = ?", [$pid]);
if (!$participant) {
    echo json_encode(['success' => false, 'message' => 'Participante não encontrado']); exit;
}
$companyId = (int)$participant['company_id'];

// Confirma que a questão pertence ao quiz deste participante
$question = dbRow("SELECT id FROM questions WHERE id = ? AND quiz_id = ?", [$q_id, $participant['quiz_id']]);
if (!$question) {
    echo json_encode(['success' => false, 'message' => 'Questão não pertence a este quiz']); exit;
}

// Impede duplo envio para a mesma questão
$existing = dbRow("SELECT id FROM answers WHERE participant_id = ? AND question_id = ?", [$pid, $q_id]);
if ($existing) {
    echo json_encode(['success' => false, 'message' => 'Resposta já registrada']); exit;
}

// Grava a resposta com company_id do tenant
dbExec("
    INSERT INTO answers (participant_id, company_id, question_id, selected_answer, is_correct, time_taken)
    VALUES (?,?,?,?,?,?)
", [$pid, $companyId, $q_id, $selected, $correct, $time]);

// Atualiza estatísticas do participante
$stats = dbRow("
    SELECT COUNT(*) AS total_q, SUM(is_correct) AS total_c, AVG(time_taken) AS avg_t
    FROM answers
    WHERE participant_id = ?
", [$pid]);

dbExec("
    UPDATE participants
    SET score           = ?,
        total_questions = ?,
        avg_time        = ?,
        percentage      = ROUND((? * 1.0 / ?) * 100, 1),
        last_activity   = datetime('now','localtime')
    WHERE id = ?
", [
    (int)$stats['total_c'],
    (int)$stats['total_q'],
    (float)$stats['avg_t'],
    (int)$stats['total_c'],
    (int)$stats['total_q'],
    $pid
]);

echo json_encode([
    'success' => true,
    'score'   => (int)$stats['total_c'],
    'total'   => (int)$stats['total_q'],
    'avg_t'   => (float)$stats['avg_t'],
]);
