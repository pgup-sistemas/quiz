<?php
require_once __DIR__ . '/../includes/db.php';

$quiz = dbRow("SELECT id FROM quizzes WHERE active = 1 ORDER BY id DESC LIMIT 1");
if (!$quiz) {
    die("Erro: Nenhum quiz ativo encontrado para o teste.");
}

$id = $quiz['id'];
$names = [
    "Ana Silva", "Beto Junior", "Carla Dias", "Daniel Souza", "Eduardo Lima", 
    "Fernanda Costa", "Gabriel Rocha", "Heloísa Melo", "Ícaro Porto", "Julia Reis",
    "Kevin Santos", "Larissa Ferreira", "Marcos Oliveira", "Nathalia Lima", "Otávio Mendes",
    "Patrícia Gomes", "Quintino Ramos", "Renata Araújo", "Samuel Cavalcanti", "Tatiana Vieira",
    "Ulysses Guimarães", "Vanessa Castro", "Wagner Barata", "Xuxa Meneghel", "Yuri Alberto",
    "Zeca Pagodinho", "Amanda Duarte", "Bruno Meireles", "Cecília Lopes", "Danilo Gentili",
    "Elaine Martins", "Fábio Porchat", "Geovana Terra", "Hugo Gloss", "Ivete Sangalo"
];

$db = getDB();
$db->exec("DELETE FROM participants WHERE email LIKE '%@fake.test'");

$qCount = (int)dbRow("SELECT COUNT(*) as c FROM questions WHERE quiz_id = ?", [$id])['c'];
if ($qCount == 0) $qCount = 10; // Fallback

foreach ($names as $i => $name) {
    $answered    = rand(0, $qCount);
    $is_finished = ($answered === $qCount && rand(0, 1)); // Randomly finish some
    $score       = rand(0, $answered);
    $sector      = ['TI', 'Enfermagem', 'ADM', 'Qualidade', 'Recepção'][rand(0, 4)];
    $pct         = $answered > 0 ? ($score / $answered) * 100 : 0;
    
    // Simulating activity in the last 30 seconds to be "Online"
    $last_activity = date('Y-m-d H:i:s', time() - rand(0, 30));
    $completed_at  = $is_finished ? date('Y-m-d H:i:s') : null;

    $db->prepare("
        INSERT INTO participants (
            quiz_id, name, email, sector, score, total_questions, percentage, passed, 
            last_activity, completed_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ")->execute([
        $id, $name, "user$i@fake.test", $sector, $score, $answered, $pct, 
        ($pct >= 70 ? 1 : 0), $last_activity, $completed_at
    ]);
}

echo "✅ Sucesso! 35 participantes fictícios gerados para o Quiz ID $id.\n";
echo "Abra o Modo Ao Vivo para visualizar as 3 colunas em ação.\n";
