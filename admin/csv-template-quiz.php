<?php
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="modelo-importacao-quiz.csv"');
header('Cache-Control: no-cache, must-revalidate');

// BOM para Excel reconhecer UTF-8
echo "\xEF\xBB\xBF";

$out = fopen('php://output', 'w');

// Seção [QUIZ] — configuração do quiz (uma única linha de dados)
fputcsv($out, ['[QUIZ]'], ';', '"', "");
fputcsv($out, ['titulo', 'descricao', 'setor', 'tempo_por_questao_seg', 'nota_minima_pct'], ';', '"', "");
fputcsv($out, [
    'Segurança no Trabalho — Módulo 1',
    'Treinamento introdutório sobre normas de segurança e EPIs.',
    'Geral',
    30,
    70,
], ';', '"', "");

// Seção [QUESTOES] — mesmo formato de admin/import.php
fputcsv($out, ['[QUESTOES]'], ';', '"', "");
fputcsv($out, ['pergunta', 'categoria', 'alternativa_a', 'alternativa_b', 'alternativa_c', 'alternativa_d', 'correta', 'explicacao'], ';', '"', "");

$examples = [
    [
        'Qual é a capital do Brasil?',
        'Geografia',
        'São Paulo', 'Rio de Janeiro', 'Brasília', 'Salvador',
        'C',
        'Brasília é a capital federal desde 1960.',
    ],
    [
        'Quantos estados tem o Brasil?',
        'Geografia',
        '24', '26', '27', '28',
        'C',
        'O Brasil possui 26 estados mais o Distrito Federal.',
    ],
    [
        'Qual NR regulamenta o uso de EPI?',
        'Segurança do Trabalho',
        'NR-6', 'NR-9', 'NR-10', 'NR-35',
        'A',
        'A NR-6 regulamenta o uso de Equipamentos de Proteção Individual.',
    ],
];

foreach ($examples as $row) {
    fputcsv($out, $row, ';', '"', "");
}

fclose($out);
