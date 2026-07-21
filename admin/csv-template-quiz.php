<?php
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="modelo-importacao-quiz.csv"');
header('Cache-Control: no-cache, must-revalidate');

// BOM para Excel reconhecer UTF-8
echo "\xEF\xBB\xBF";

$out = fopen('php://output', 'w');
fputcsv($out, ['pergunta', 'alternativa_a', 'alternativa_b', 'alternativa_c', 'alternativa_d', 'correta', 'explicacao']);

fputcsv($out, [
    'Qual é a capital do Brasil?',
    'São Paulo', 'Rio de Janeiro', 'Brasília', 'Salvador',
    'C',
    'Brasília é a capital federal desde 1960.',
]);
fputcsv($out, [
    'Quantos estados tem o Brasil?',
    '24', '26', '27', '28',
    'C',
    'O Brasil possui 26 estados mais o Distrito Federal.',
]);
fputcsv($out, [
    'Qual NR regulamenta o uso de EPI?',
    'NR-6', 'NR-9', 'NR-10', 'NR-35',
    'A',
    'A NR-6 regulamenta o uso de Equipamentos de Proteção Individual.',
]);

fclose($out);
