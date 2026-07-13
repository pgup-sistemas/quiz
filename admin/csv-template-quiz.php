<?php
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

// Gera e força download do modelo CSV completo (config + questões)
$filename = 'modelo-quiz-completo.csv';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-cache, no-store');

// BOM UTF-8 para Excel reconhecer acentos automaticamente
echo chr(0xEF) . chr(0xBB) . chr(0xBF);

$rows = [
    // ── Seção Quiz ──────────────────────────────────────────────────────────
    ['[QUIZ]'],
    ['titulo', 'descricao', 'setor', 'tempo_por_questao_seg', 'aprovacao_minima_pct'],
    [
        'Treinamento de Biossegurança 2025',
        'Quiz obrigatório para todos os colaboradores que manuseiam amostras biológicas.',
        'Laboratório',
        '30',
        '70',
    ],

    // Linha em branco separadora
    [''],

    // ── Seção Questões ───────────────────────────────────────────────────────
    ['[QUESTOES]'],
    [
        'pergunta',
        'categoria',
        'opcao_a',
        'opcao_b',
        'opcao_c',
        'opcao_d',
        'resposta_correta_(A_B_C_D)',
        'explicacao',
    ],
    [
        'Qual o EPI mínimo obrigatório ao manusear amostras biológicas?',
        'Biossegurança',
        'Apenas máscara cirúrgica',
        'Luvas e avental',
        'Somente óculos de proteção',
        'Nenhum EPI é necessário',
        'B',
        'Luvas e avental são os EPIs mínimos conforme NR-32 para manuseio de amostras biológicas.',
    ],
    [
        'Com que frequência mínima devem ser realizados os treinamentos de segurança?',
        'Procedimentos',
        'A cada 5 anos',
        'A cada 2 anos',
        'Anualmente',
        'Somente na admissão',
        'C',
        'Treinamentos anuais são obrigatórios pela legislação vigente.',
    ],
    [
        'O que deve ser feito imediatamente após um acidente com material biológico?',
        'Emergência',
        'Continuar o trabalho normalmente',
        'Lavar a área com água e sabão e acionar o SESMT',
        'Aguardar o fim do turno para relatar',
        'Aplicar álcool 70% e ignorar',
        'B',
        'Em caso de acidente com material biológico deve-se higienizar imediatamente e notificar o setor de segurança.',
    ],
    [
        'Qual a temperatura correta para armazenar amostras refrigeradas?',
        'Procedimentos',
        'Entre 0°C e 4°C',
        'Entre 8°C e 12°C',
        'Temperatura ambiente',
        'Abaixo de -20°C',
        'A',
        'Amostras refrigeradas devem ser mantidas entre 0°C e 4°C para preservar a integridade.',
    ],
    [
        'Em caso de derramamento de reagente químico corrosivo, qual é o procedimento correto?',
        'Segurança Química',
        'Limpar com papel toalha comum',
        'Evacuar a área e chamar a equipe especializada',
        'Diluir com água abundante imediatamente sem avisar ninguém',
        'Cobrir com areia e continuar trabalhando',
        'B',
        'Derramamentos de reagentes corrosivos exigem evacuação da área e acionamento de pessoal treinado.',
    ],
];

$out = fopen('php://output', 'w');
foreach ($rows as $row) {
    fputcsv($out, $row, ';', '"');
}
fclose($out);
exit;
