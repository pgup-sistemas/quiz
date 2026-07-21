<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/tenant.php';

if (session_status() === PHP_SESSION_NONE) session_start();
$tenant = resolveTenant();

// company_id do colaborador logado (gravado no login — não pode ser forjado via URL)
$loggedUserCid = (int)($_SESSION['pageup_user']['company_id'] ?? 0);

$id = (int)($_GET['id'] ?? 0);
if (!$id) { echo json_encode(['error' => 'ID inválido']); exit; }

if ($tenant) {
    $tenantCid = (int)$tenant['id'];
    // Colaborador logado de empresa diferente não pode acessar quiz desta empresa
    if ($loggedUserCid > 0 && $loggedUserCid !== $tenantCid) {
        echo json_encode(['error' => 'Acesso não autorizado']); exit;
    }
    $quiz = dbRow("SELECT * FROM quizzes WHERE id = ? AND active = 1 AND company_id = ?", [$id, $tenantCid]);
} elseif ($loggedUserCid > 0) {
    // Usuário logado sem tenant na URL → filtra pela empresa do usuário
    $quiz = dbRow("SELECT * FROM quizzes WHERE id = ? AND active = 1 AND company_id = ?", [$id, $loggedUserCid]);
} else {
    // Sem tenant e sem usuário logado (acesso local/admin): sem filtro de empresa
    $quiz = dbRow("SELECT * FROM quizzes WHERE id = ? AND active = 1", [$id]);
}
if (!$quiz) { echo json_encode(['error' => 'Quiz não encontrado']); exit; }

$questions = dbRows("
    SELECT id, question_text, category, option_a, option_b, option_c, option_d,
           correct_answer, explanation, sort_order
    FROM questions
    WHERE quiz_id = ?
    ORDER BY sort_order ASC, id ASC
", [$id]);

if (empty($questions)) {
    echo json_encode(['error' => 'Este quiz ainda não possui questões cadastradas.']);
    exit;
}

// Optionally randomize
if ($quiz['randomize']) {
    shuffle($questions);
}

// Correct answers are NOT sent to the client. Validation happens server-side in save-answer.php.
$qs = [];
foreach ($questions as $q) {
    $opts = array_filter([
        $q['option_a'],
        $q['option_b'],
        $q['option_c'],
        $q['option_d'],
    ], fn($o) => trim($o) !== '');
    $qs[] = [
        'id'   => $q['id'],
        'q'    => $q['question_text'],
        'cat'  => $q['category'],
        'opts' => array_values($opts),
        'exp'  => $q['explanation'],
    ];
}

// Slice if max_questions is set
if ($quiz['max_questions'] > 0) {
    $qs = array_slice($qs, 0, $quiz['max_questions']);
}

echo json_encode([
    'quiz' => [
        'id'          => $quiz['id'],
        'title'       => $quiz['title'],
        'sector'      => $quiz['sector'],
        'timer'       => (int)$quiz['time_per_question'],
        'pass_percentage' => (int)$quiz['pass_percentage'],
        'max_questions' => (int)$quiz['max_questions'],
        'expires_at'    => $quiz['expires_at'],
        'feedback'      => (bool)$quiz['show_feedback'],
        'has_certificate' => (bool)($quiz['has_certificate'] ?? true),
    ],
    'questions' => $qs,
]);
