<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/layout.php';
requireLogin();

adminHead('Manual do Sistema', 'manual.php');
?>
<style>
.manual-wrap { max-width: 900px; }
.manual-section { margin-bottom: 40px; }
.manual-section h2 { font-size: 20px; font-weight: 700; color: var(--navy); margin-bottom: 20px; display: flex; align-items: center; gap: 10px; border-bottom: 2px solid var(--gray-100); padding-bottom: 10px; }
.manual-section h3 { font-size: 16px; font-weight: 700; color: var(--blue-dark); margin: 24px 0 12px; }
.manual-section p { line-height: 1.6; color: var(--gray-600); margin-bottom: 16px; font-size: 14.5px; }
.manual-section ul { list-style: none; padding: 0; }
.manual-section li { margin-bottom: 12px; display: flex; gap: 12px; font-size: 14px; color: var(--gray-600); }
.manual-section li i { color: var(--blue); margin-top: 4px; }
.manual-card { background: var(--blue-pale); border-left: 4px solid var(--blue); padding: 20px; border-radius: 8px; margin: 20px 0; }
.manual-card strong { color: var(--blue-dark); display: block; margin-bottom: 8px; }
.step-badge { background: var(--navy); color: #fff; width: 24px; height: 24px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 700; flex-shrink: 0; }
</style>

<div class="admin-wrap manual-wrap">
    <div class="flex items-center justify-between mb-32">
        <div>
            <h1 style="font-size:24px;font-weight:700;color:var(--gray-800)"><i class="fa-solid fa-book-open" aria-hidden="true"></i> Manual do Sistema</h1>
            <p class="text-muted" style="font-size:14px;margin-top:4px">Guia de uso e configuração da plataforma PageQuiz.</p>
        </div>
    </div>

    <!-- ─── Visão Geral ───────────────────────────────────────── -->
    <div class="manual-section">
        <h2><i class="fa-solid fa-circle-info"></i> Visão Geral</h2>
        <p>
            O <strong>PageQuiz</strong> é uma plataforma projetada para auditorias, treinamentos e engajamento da equipe.
            O sistema permite que diferentes setores criem quizes personalizados com tempo controlado, feedbacks educativos e geração automática de certificados para os aprovados.
        </p>
    </div>

    <!-- ─── Gestão de Quizzes ─────────────────────────────────── -->
    <div class="manual-section">
        <h2><i class="fa-solid fa-list-check"></i> Gestão de Quizzes</h2>
        <p>No menu <strong>Quizzes</strong>, você pode gerenciar todas as avaliações da plataforma:</p>
        <ul>
            <li><li><span class="step-badge">1</span></li><div><strong>Criar/Editar:</strong> Defina o título, descrição, setor responsável e a nota mínima para aprovação (padrão 70%).</div></li>
            <li><li><span class="step-badge">2</span></li><div><strong>Configurações Avançadas:</strong> Você pode definir uma <strong>Data de Expiração</strong> (o quiz some do ar após a data) e um <strong>Limite de Questões</strong> (o sistema sorteia X questões do banco para cada participante).</div></li>
            <li><li><span class="step-badge">3</span></li><div><strong>Duplicar (Clonar):</strong> Ideal para criar versões de um mesmo quiz para diferentes períodos ou setores sem precisar cadastrar todas as questões novamente.</div></li>
            <li><li><span class="step-badge">4</span></li><div><strong>Ativar/Desativar:</strong> Apenas quizes <strong>Ativos</strong> e dentro do prazo de validade aparecem na página inicial.</div></li>
        </ul>
    </div>

    <!-- ─── Questões e CSV ───────────────────────────────────── -->
    <div class="manual-section">
        <h2><i class="fa-solid fa-file-import"></i> Questões e Importação</h2>
        <p>Ao editar um quiz, você pode gerenciar o banco de questões na parte inferior da página.</p>
        <div class="manual-card">
            <strong><i class="fa-solid fa-bolt"></i> Dica de Produtividade: Importação em Lote</strong>
            <p>Use o menu <strong>Importar</strong> para cadastrar dezenas de questões de uma vez usando um arquivo CSV. Baixe o modelo oficial na página de importação para garantir que as colunas estejam na ordem correta.</p>
        </div>
        <ul>
            <li><i class="fa-solid fa-check"></i> <strong>Ordenação:</strong> Você pode definir a ordem manual das questões ou marcar para que o sistema as embaralhe para o participante.</li>
            <li><i class="fa-solid fa-check"></i> <strong>Explicações:</strong> Sempre preencha o campo de "Explicação". Ela será exibida ao participante logo após ele responder, reforçando o aprendizado (feedback educativo).</li>
        </ul>
    </div>

    <!-- ─── Setores ─────────────────────────────────────────── -->
    <div class="manual-section">
        <h2><i class="fa-solid fa-sitemap"></i> Gestão de Setores</h2>
        <p>O menu <strong>Setores</strong> organiza a lista de departamentos disponíveis na plataforma.</p>
        <ul>
            <li><i class="fa-solid fa-rotate"></i> <strong>Atualização em Cascata:</strong> Se você renomear um setor, todos os quizes e participações associadas a ele serão atualizados automaticamente.</li>
            <li><i class="fa-solid fa-trash-can"></i> <strong>Exclusão Segura:</strong> Ao excluir um setor, o sistema perguntará se deseja migrar os quizes vinculados para o setor "Geral" para evitar perda de dados.</li>
        </ul>
    </div>

    <!-- ─── Resultados e Certificados ────────────────────────── -->
    <div class="manual-section">
        <h2><i class="fa-solid fa-graduation-cap"></i> Resultados e Certificados</h2>
        <p>Acompanhe o desempenho da equipe em tempo real:</p>
        <ul>
            <li><i class="fa-solid fa-file-export"></i> <strong>Exportar CSV:</strong> No menu Resultados, você pode baixar relatórios filtrados por quiz, setor ou data. Útil para evidências em auditorias ONA.</li>
            <li><i class="fa-solid fa-award"></i> <strong>Certificação:</strong> O certificado é gerado apenas para quem atinge a nota mínima. Notas acima de 90% recebem o selo de "Aprovado com Distinção".</li>
            <li><i class="fa-solid fa-qrcode"></i> <strong>Validação:</strong> Cada certificado possui um ID de Verificação único no rodapé para garantir a autenticidade do documento.</li>
        </ul>
    </div>

    <!-- ─── Segurança e Configurações ──────────────────────────── -->
    <div class="manual-section">
        <h2><i class="fa-solid fa-shield-halved"></i> Segurança e Configurações</h2>
        <p>No menu <strong>Configurações</strong>, você pode gerenciar quem tem acesso ao painel:</p>
        <ul>
            <li><i class="fa-solid fa-user-plus"></i> <strong>Novos Admins:</strong> Crie usuários específicos para cada gestor de setor.</li>
            <li><i class="fa-solid fa-key"></i> <strong>Senhas:</strong> Recomenda-se a alteração periódica da senha de acesso.</li>
            <li><i class="fa-solid fa-lock"></i> <strong>Deploy:</strong> O banco de dados SQLite e os arquivos de sistema estão protegidos contra acesso direto via web através do arquivo <code>.htaccess</code>.</li>
        </ul>
    </div>

    <div style="text-align:center; padding:40px 0; color:var(--gray-400); font-size:12px; border-top:1px solid var(--gray-100)">
        PageQuiz · Versão 1.0.0 · © <?= date('Y') ?> · PageUp Sistemas
    </div>
</div>

<?php adminFoot(); ?>
