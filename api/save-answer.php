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
$q_id     = (int)($input['question_id']      ?? 0);
$selected = (int)($input['selected_answer']  ?? -1);
$correct  = (int)($input['is_correct']       ?? 0);
$time     = (int)($input['time_taken']       ?? 0);

if (!$pid || !$q_id) {
    echo json_encode(['success' => false, 'message' => 'Dados incompletos']); exit;
}

// Security: Prevent multiple answers for the same question by the same participant
$existing = dbRow("SELECT id FROM answers WHERE participant_id = ? AND question_id = ?", [$pid, $q_id]);
if ($existing) {
    echo json_encode(['success' => false, 'message' => 'Resposta já registrada']); exit;
}

// 1. Insert individual answer
dbExec("
    INSERT INTO answers (participant_id, question_id, selected_answer, is_correct, time_taken)
    VALUES (?,?,?,?,?)
", [$pid, $q_id, $selected, $correct, $time]);

// 2. Update participant live stats
// Fetch current totals for this participant
$stats = dbRow("
    SELECT COUNT(*) as total_q, SUM(is_correct) as total_c, AVG(time_taken) as avg_t
    FROM answers
    WHERE participant_id = ?
", [$pid]);

dbExec("
    UPDATE participants
    SET score = ?, total_questions = ?, avg_time = ?, percentage = ROUND((? * 1.0 / ?) * 100, 1), last_activity = datetime('now','localtime')
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
    'avg_t'   => (float)$stats['avg_t']
]);
