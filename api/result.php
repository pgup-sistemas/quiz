<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/db.php';

session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'Método inválido']); exit;
}

/**
 * REFACTOR: Result API now handles quiz finalizing.
 * Answers are previously recorded per question via save-answer.php.
 */

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) { $input = $_POST; }

$pid    = (int)($input['participant_id'] ?? ($_SESSION['current_participant_id'] ?? 0));
$quizId = (int)($input['quiz_id']          ?? 0);

if (!$pid || !$quizId) {
    echo json_encode(['error' => 'Sessão não encontrada ou dados incompletos']); exit;
}

// 1. Fetch Participant data — validates quiz ownership to prevent IDOR
$p = dbRow("SELECT * FROM participants WHERE id = ? AND quiz_id = ?", [$pid, $quizId]);
if (!$p) {
    echo json_encode(['error' => 'Participante não encontrado']); exit;
}

// Check if already completed to avoid multiple submissions
if (!empty($p['completed_at'])) {
    // Already finalized, just return data
    $qPct = (int)dbRow("SELECT pass_percentage FROM quizzes WHERE id = ?", [$quizId])['pass_percentage'];
    echo json_encode([
        'ok'             => true,
        'participant_id' => $pid,
        'verify_code'    => strtoupper(substr(md5($pid . 'pageup'), 0, 8)),
        'score'          => (int)$p['score'],
        'total'          => (int)$p['total_questions'],
        'percentage'     => (float)$p['percentage'],
        'passed'         => (bool)$p['passed'],
        'pass_percentage' => $qPct,
    ]);
    exit;
}

// 2. Fetch Quiz pass percentage
$quiz = dbRow("SELECT pass_percentage FROM quizzes WHERE id = ?", [$quizId]);
if (!$quiz) {
    echo json_encode(['error' => 'Quiz não encontrado']); exit;
}

// 3. Final calculation of results from the answers table to ensure accuracy
$stats = dbRow("
    SELECT COUNT(*) as total_q, SUM(is_correct) as total_c, AVG(time_taken) as avg_t
    FROM answers
    WHERE participant_id = ?
", [$pid]);

$total   = (int)$stats['total_q'];
$correct = (int)$stats['total_c'];
$avgTime = (float)$stats['avg_t'];
$pct     = $total > 0 ? round(($correct / $total) * 100, 1) : 0;
$passed  = $pct >= (int)$quiz['pass_percentage'] ? 1 : 0;

// 4. Update and finalize the participant record
// Secure: cryptographically random, non-guessable verification code
$vCode = strtoupper(bin2hex(random_bytes(4)));
dbExec("
    UPDATE participants
    SET score = ?, total_questions = ?, percentage = ?, passed = ?, avg_time = ?, completed_at = datetime('now','localtime'), verify_code = ?
    WHERE id = ?
", [$correct, $total, $pct, $passed, $avgTime, $vCode, $pid]);

// 5. Clear session
unset($_SESSION['current_participant_id']);
$_SESSION['last_quiz_submit'] = time();

echo json_encode([
    'ok'             => true,
    'participant_id' => $pid,
    'verify_code'    => $vCode,  // return the same code that was persisted
    'score'          => $correct,
    'total'          => $total,
    'percentage'     => $pct,
    'passed'         => (bool)$passed,
    'pass_percentage' => (int)$quiz['pass_percentage'],
]);
