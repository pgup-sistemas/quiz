<?php
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="modelo_questoes.csv"');
header('Cache-Control: no-cache');

$out = fopen('php://output', 'w');
fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF)); // UTF-8 BOM

fputcsv($out, ['Pergunta','Categoria','Opcao_A','Opcao_B','Opcao_C','Opcao_D','Correta_(A/B/C/D)','Explicacao'], ';', '"', "");

// Example rows
$examples = [
    [
        'Qual EPI é obrigatório ao manusear amostras biológicas na coleta?',
        'Biossegurança',
        'Máscara N95',
        'Luvas de nitrila e avental',
        'Óculos de proteção',
        'Capote estéril',
        'B',
        'Luvas e avental são os EPIs mínimos obrigatórios para coleta conforme NR-32.'
    ],
    [
        'O que fazer imediatamente após acidente perfurocortante?',
        'Biossegurança',
        'Lavar com álcool 70%',
        'Apertar o local para sangrar e lavar com água e sabão',
        'Cobrir com curativo',
        'Aplicar antisséptico tópico',
        'B',
        'Lavar abundantemente com água e sabão é o procedimento correto.'
    ],
    [
        'Qual dado do paciente é considerado dado sensível pela LGPD?',
        'LGPD',
        'Nome completo',
        'CPF',
        'Diagnóstico médico',
        'Endereço',
        'C',
        'Dados de saúde como diagnósticos são dados sensíveis com proteção reforçada pela LGPD.'
    ],
];

foreach ($examples as $row) {
    fputcsv($out, $row, ';', '"', "");
}

fclose($out);
