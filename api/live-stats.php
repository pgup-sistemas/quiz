<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Não autorizado']); exit;
}

$quizId = (int)($_GET['quiz_id'] ?? 0);
if (!$quizId) {
    echo json_encode(['success' => false, 'message' => 'Quiz ID obrigatório']); exit;
}

$cid = adminCompanyId();
if (!dbRow("SELECT id FROM quizzes WHERE id=? AND company_id=?", [$quizId, $cid])) {
    echo json_encode(['success' => false, 'message' => 'Quiz não encontrado']); exit;
}

// 1. Fetch total question count for this quiz
$qCount = (int)dbRow("SELECT COUNT(*) as c FROM questions WHERE quiz_id = ?", [$quizId])['c'];

/**
 * Fetch participants:
 * - We want everyone who is currently active OR recently finished.
 * - 'Active' = last_activity within last 2 minutes.
 * - 'Finished' = completed_at within last 10 minutes.
 * - Lobby users (0 questions) should also appear.
 */
$participants = dbRows("
    SELECT 
        p.id, p.name, p.sector, p.score, p.total_questions, p.avg_time, p.completed_at, p.last_activity,
        (p.avg_time * p.total_questions) as total_time_spent
    FROM participants p
    WHERE p.quiz_id = ?
    AND (
        p.last_activity >= datetime('now', 'localtime', '-2 minutes')
        OR p.completed_at >= datetime('now', 'localtime', '-10 minutes')
    )
    ORDER BY p.score DESC, total_time_spent ASC, p.last_activity DESC
", [$quizId]);

// Format for the dashboard
$data = [];
$now = time();
foreach ($participants as $p) {
    $lastAct = strtotime($p['last_activity']);
    $isOnline = ($now - $lastAct) < 45; // Considered online if activity in last 45s
    
    $data[] = [
        'id'             => (int)$p['id'],
        'name'           => $p['name'],
        'sector'         => $p['sector'],
        'score'          => (int)$p['score'],
        'progress'       => $qCount > 0 ? round(($p['total_questions'] / $qCount) * 100) : 0,
        'total_time'     => round($p['total_time_spent'], 1),
        'is_finished'    => !is_null($p['completed_at']),
        'is_online'      => $isOnline,
        'finished_time'  => $p['completed_at']
    ];
}

// 2. Fetch overall stats for the dash
$stats = dbRow("
    SELECT 
        COUNT(*) as total_participants, 
        SUM(CASE WHEN passed = 1 THEN 1 ELSE 0 END) as passed_count,
        AVG(percentage) as avg_score
    FROM participants 
    WHERE quiz_id = ?
", [$quizId]);

echo json_encode([
    'success'      => true,
    'quiz_count'   => $qCount,
    'participants' => $data,
    'stats'        => [
        'total' => (int)($stats['total_participants'] ?? 0),
        'passed' => (int)($stats['passed_count'] ?? 0),
        'avg_score' => round($stats['avg_score'] ?? 0, 1)
    ]
]);
