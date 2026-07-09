<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/db.php';

session_start();

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

// Insert participant with started_at
// Note: completed_at remains NULL until result.php is called
dbExec("
    INSERT INTO participants (quiz_id, name, email, sector, score, total_questions, percentage, passed, avg_time, started_at, last_activity)
    VALUES (?,?,?,?,0,0,0,0,0, datetime('now','localtime'), datetime('now','localtime'))
", [$quizId, $name, $email, $sector]);

$pid = dbLastId();
$_SESSION['current_participant_id'] = $pid;

echo json_encode([
    'success'        => true,
    'participant_id' => $pid
]);
