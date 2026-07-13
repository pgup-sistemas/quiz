<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/layout.php';
requireLogin();

adminHead('Manual do Sistema', 'manual.php');
?>
<style>
.manual-wrap { max-width: 860px; }

/* Navegação rápida */
.manual-nav {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    background: #fff;
    border: 1px solid var(--gray-100);
    border-radius: 12px;
    padding: 16px 20px;
    margin-bottom: 36px;
}
.manual-nav a {
    font-size: 12px;
    font-weight: 600;
    color: var(--gray-500);
    text-decoration: none;
    padding: 5px 12px;
    border-radius: 20px;
    border: 1px solid var(--gray-200);
    display: flex;
    align-items: center;
    gap: 6px;
    transition: all .15s;
}
.manual-nav a:hover { background: var(--prussian); color: #fff; border-color: var(--prussian); }

/* Seções */
.manual-section { margin-bottom: 44px; scroll-margin-top: 80px; }
.manual-section h2 {
    font-size: 18px;
    font-weight: 700;
    color: var(--navy);
    margin-bottom: 16px;
    display: flex;
    align-items: center;
    gap: 10px;
    border-bottom: 2px solid var(--gray-100);
    padding-bottom: 10px;
}
.manual-section h2 i { color: var(--pacific); }
.manual-section h3 { font-size: 14px; font-weight: 700; color: var(--gray-700); margin: 20px 0 10px; }
.manual-section p  { line-height: 1.7; color: var(--gray-600); margin-bottom: 14px; font-size: 14px; }

.manual-list { list-style: none; padding: 0; margin: 0 0 16px; }
.manual-list li {
    margin-bottom: 12px;
    display: flex;
    gap: 12px;
    font-size: 13.5px;
    color: var(--gray-600);
    line-height: 1.6;
}
.manual-list li i { color: var(--pacific); margin-top: 3px; flex-shrink: 0; width: 16px; text-align: center; }

/* Steps numerados */
.step-list { list-style: none; padding: 0; margin: 0 0 16px; }
.step-list li {
    margin-bottom: 14px;
    display: flex;
    gap: 12px;
    font-size: 13.5px;
    color: var(--gray-600);
    line-height: 1.6;
    align-items: flex-start;
}
.step-badge {
    background: var(--prussian);
    color: #fff;
    width: 22px; height: 22px;
    border-radius: 50%;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 11px;
    font-weight: 700;
    flex-shrink: 0;
    margin-top: 2px;
}

/* Card de dica */
.manual-card {
    background: #f0f9ff;
    border-left: 4px solid var(--pacific);
    padding: 16px 20px;
    border-radius: 8px;
    margin: 16px 0;
    font-size: 13.5px;
    color: var(--gray-700);
    line-height: 1.6;
}
.manual-card strong { color: var(--prussian); display: block; margin-bottom: 6px; }

/* Card de aviso */
.manual-warn {
    background: #fffbeb;
    border-left: 4px solid var(--yellow);
    padding: 14px 18px;
    border-radius: 8px;
    margin: 16px 0;
    font-size: 13px;
    color: #78350f;
}
</style>

<div class="admin-wrap manual-wrap">

    <!-- Header -->
    <div class="flex items-center justify-between mb-24">
        <div>
            <h1 style="font-size:22px;font-weight:700;color:var(--gray-800)">
                <i class="fa-solid fa-book-open" style="color:var(--pacific)"></i> Manual do Sistema
            </h1>
            <p class="text-muted" style="font-size:13px;margin-top:2px">
                Guia de uso e configuração da plataforma PageQuiz
            </p>
        </div>
    </div>

    <!-- Navegação rápida -->
    <nav class="manual-nav" aria-label="Índice do manual">
        <a href="#visao-geral"><i class="fa-solid fa-circle-info"></i> Visão Geral</a>
        <a href="#dashboard"><i class="fa-solid fa-table-columns"></i> Dashboard</a>
        <a href="#quizzes"><i class="fa-solid fa-list-check"></i> Quizzes</a>
        <a href="#questoes"><i class="fa-solid fa-file-import"></i> Questões</a>
        <a href="#setores"><i class="fa-solid fa-sitemap"></i> Setores</a>
        <a href="#usuarios"><i class="fa-solid fa-users"></i> Usuários</a>
        <a href="#portal"><i class="fa-solid fa-door-open"></i> Portal do Usuário</a>
        <a href="#resultados"><i class="fa-solid fa-graduation-cap"></i> Resultados</a>
        <a href="#ao-vivo"><i class="fa-solid fa-tower-broadcast"></i> Ao Vivo</a>
        <a href="#seguranca"><i class="fa-solid fa-shield-halved"></i> Segurança</a>
    </nav>

    <!-- ─── 1. Visão Geral ──────────────────────────────────── -->
    <div class="manual-section" id="visao-geral">
        <h2><i class="fa-solid fa-circle-info"></i> Visão Geral</h2>
        <p>
            O <strong>PageQuiz</strong> é uma plataforma de treinamento e avaliação corporativa via quizzes,
            com emissão de certificados verificáveis. Cada empresa tem seu próprio espaço isolado de quizzes,
            usuários e resultados.
        </p>
        <ul class="manual-list">
            <li><i class="fa-solid fa-check"></i> Crie avaliações por setor com tempo controlado e nota mínima configurável.</li>
            <li><i class="fa-solid fa-check"></i> Colaboradores acessam via link exclusivo ou auto-cadastro.</li>
            <li><i class="fa-solid fa-check"></i> Certificados gerados automaticamente para aprovados, com QR code de validação.</li>
            <li><i class="fa-solid fa-check"></i> Relatórios exportáveis em CSV para auditorias.</li>
        </ul>
    </div>

    <!-- ─── 2. Dashboard ───────────────────────────────────── -->
    <div class="manual-section" id="dashboard">
        <h2><i class="fa-solid fa-table-columns"></i> Dashboard</h2>
        <p>A tela inicial do painel administrativo apresenta uma visão rápida do estado da plataforma:</p>
        <ul class="manual-list">
            <li><i class="fa-solid fa-gauge-high"></i> <strong>KPIs:</strong> total de quizzes ativos, usuários cadastrados, participações e aprovações.</li>
            <li><i class="fa-solid fa-clock-rotate-left"></i> <strong>Atividade Recente:</strong> últimas participações com nome, quiz e nota.</li>
            <li><i class="fa-solid fa-link"></i> <strong>Acesso &amp; Links:</strong> URL e QR code para o portal dos colaboradores, com botão de cópia rápida.</li>
            <li><i class="fa-solid fa-chart-bar"></i> <strong>Análise por Setor:</strong> desempenho comparativo entre setores com barra de aprovação.</li>
        </ul>
    </div>

    <!-- ─── 3. Gestão de Quizzes ──────────────────────────── -->
    <div class="manual-section" id="quizzes">
        <h2><i class="fa-solid fa-list-check"></i> Gestão de Quizzes</h2>
        <p>No menu <strong>Quizzes</strong> você gerencia todas as avaliações da plataforma:</p>
        <ol class="step-list">
            <li>
                <span class="step-badge">1</span>
                <div><strong>Criar/Editar:</strong> Defina título, descrição, setor, tempo limite e nota mínima de aprovação (padrão 70%).</div>
            </li>
            <li>
                <span class="step-badge">2</span>
                <div><strong>Data de Expiração:</strong> O quiz some automaticamente do portal após a data definida — ideal para avaliações periódicas.</div>
            </li>
            <li>
                <span class="step-badge">3</span>
                <div><strong>Limite de Questões:</strong> Com um banco grande, o sistema pode sortear X questões por participante, tornando cada tentativa única.</div>
            </li>
            <li>
                <span class="step-badge">4</span>
                <div><strong>Duplicar (Clonar):</strong> Cria uma cópia completa do quiz com todas as questões — útil para versões por período ou setor.</div>
            </li>
            <li>
                <span class="step-badge">5</span>
                <div><strong>Ativar/Desativar:</strong> Apenas quizzes <strong>ativos</strong> e dentro do prazo aparecem no portal dos colaboradores.</div>
            </li>
        </ol>
    </div>

    <!-- ─── 4. Questões e Importação ─────────────────────── -->
    <div class="manual-section" id="questoes">
        <h2><i class="fa-solid fa-file-import"></i> Questões e Importação</h2>
        <p>Ao editar um quiz, a seção inferior permite gerenciar o banco de questões.</p>
        <div class="manual-card">
            <strong><i class="fa-solid fa-bolt"></i> Importação em Lote via CSV</strong>
            Cadastre dezenas de questões de uma vez usando um arquivo CSV. Baixe o modelo oficial
            na tela de importação para garantir a ordem correta das colunas (enunciado, alternativas A–E, resposta correta, explicação).
        </div>
        <ul class="manual-list">
            <li><i class="fa-solid fa-shuffle"></i> <strong>Embaralhar:</strong> Marque a opção para que as alternativas sejam reordenadas a cada tentativa.</li>
            <li><i class="fa-solid fa-comment-dots"></i> <strong>Explicações:</strong> Preencha sempre o campo "Explicação" — ela é exibida logo após o participante responder, reforçando o aprendizado.</li>
            <li><i class="fa-solid fa-sort"></i> <strong>Ordenação:</strong> Arraste as questões para definir a sequência manual ou deixe o sistema embaralhar automaticamente.</li>
        </ul>
    </div>

    <!-- ─── 5. Setores ────────────────────────────────────── -->
    <div class="manual-section" id="setores">
        <h2><i class="fa-solid fa-sitemap"></i> Setores</h2>
        <p>Organize seus quizzes e colaboradores por área ou departamento.</p>
        <ul class="manual-list">
            <li><i class="fa-solid fa-tag"></i> <strong>Criação:</strong> Cada quiz e cada usuário pertence a um setor. O setor <strong>Geral</strong> é o padrão e não pode ser excluído.</li>
            <li><i class="fa-solid fa-rotate"></i> <strong>Renomeação em Cascata:</strong> Renomear um setor atualiza automaticamente todos os quizzes associados.</li>
            <li><i class="fa-solid fa-trash-can"></i> <strong>Exclusão Segura:</strong> Se o setor tiver quizzes vinculados, o sistema oferece a opção de migrá-los para "Geral" antes de excluir.</li>
        </ul>
    </div>

    <!-- ─── 6. Usuários e Colaboradores ──────────────────── -->
    <div class="manual-section" id="usuarios">
        <h2><i class="fa-solid fa-users"></i> Usuários e Colaboradores</h2>
        <p>No menu <strong>Usuários</strong> você controla quem pode acessar o portal de quizzes. A tela está dividida em três abas:</p>

        <h3><i class="fa-solid fa-table-list"></i> Aba Usuários</h3>
        <ul class="manual-list">
            <li><i class="fa-solid fa-magnifying-glass"></i> Busque por nome, e-mail ou setor para localizar rapidamente um colaborador.</li>
            <li><i class="fa-solid fa-user-pen"></i> Redefina a senha de qualquer usuário sem precisar conhecer a senha atual.</li>
            <li><i class="fa-solid fa-user-xmark"></i> Desative ou exclua contas que não devem mais ter acesso à plataforma.</li>
        </ul>

        <h3><i class="fa-solid fa-envelope"></i> Aba Convites</h3>
        <ul class="manual-list">
            <li><i class="fa-solid fa-link"></i> Gere <strong>links de convite</strong> por setor. Cada link permite que um colaborador crie sua própria conta no portal.</li>
            <li><i class="fa-solid fa-copy"></i> Copie o link com um clique e compartilhe via WhatsApp, e-mail ou intranet.</li>
            <li><i class="fa-solid fa-ban"></i> Revogue convites que não devem ser mais utilizados.</li>
        </ul>
        <div class="manual-warn">
            <i class="fa-solid fa-triangle-exclamation"></i>
            <strong>Auto-cadastro:</strong> se habilitado em <em>Configurações → Empresa</em>, colaboradores podem criar conta
            livremente sem precisar de convite. Desabilite quando quiser controle total sobre quem acessa.
        </div>

        <h3><i class="fa-solid fa-file-csv"></i> Aba Importar CSV</h3>
        <p>
            Cadastre múltiplos colaboradores de uma vez enviando um arquivo CSV com colunas:
            <code>nome</code>, <code>email</code>, <code>senha</code>, <code>setor</code>.
            Baixe o modelo na própria tela antes de importar.
        </p>
    </div>

    <!-- ─── 7. Portal do Usuário ─────────────────────────── -->
    <div class="manual-section" id="portal">
        <h2><i class="fa-solid fa-door-open"></i> Portal do Usuário</h2>
        <p>
            O portal é a área onde os <strong>colaboradores</strong> fazem login, respondem os quizzes e baixam seus certificados.
            Ele é acessado pela URL pública da empresa, disponível na aba <em>Acesso &amp; Links</em> do Dashboard.
        </p>
        <ul class="manual-list">
            <li><i class="fa-solid fa-qrcode"></i> <strong>QR Code:</strong> Imprima ou exiba em tela o QR code disponível no Dashboard para acesso rápido via celular.</li>
            <li><i class="fa-solid fa-user-plus"></i> <strong>Registro:</strong> Se o auto-cadastro estiver habilitado, o colaborador clica em "Criar conta" e preenche nome, e-mail e setor.</li>
            <li><i class="fa-solid fa-envelope-open-text"></i> <strong>Via Convite:</strong> O link de convite leva diretamente ao formulário de cadastro pré-configurado com o setor correto.</li>
            <li><i class="fa-solid fa-medal"></i> <strong>Certificados:</strong> Após aprovação, o colaborador pode baixar o certificado em PDF diretamente do portal, sem precisar do admin.</li>
        </ul>
    </div>

    <!-- ─── 8. Resultados e Certificados ─────────────────── -->
    <div class="manual-section" id="resultados">
        <h2><i class="fa-solid fa-graduation-cap"></i> Resultados e Certificados</h2>
        <p>Acompanhe o desempenho da equipe em tempo real no menu <strong>Resultados</strong>:</p>
        <ul class="manual-list">
            <li><i class="fa-solid fa-filter"></i> <strong>Filtros:</strong> Filtre por status (aprovado/reprovado), quiz ou busca por nome.</li>
            <li><i class="fa-solid fa-file-export"></i> <strong>Exportar CSV:</strong> Baixe relatórios filtrados para evidências em auditorias (ONA, ISO, etc.).</li>
            <li><i class="fa-solid fa-award"></i> <strong>Certificação:</strong> O certificado é gerado somente para quem atinge a nota mínima. Notas acima de 90% recebem o selo <em>"Aprovado com Distinção"</em>.</li>
            <li><i class="fa-solid fa-qrcode"></i> <strong>Validação:</strong> Cada certificado possui um ID único no rodapé — qualquer pessoa pode confirmar a autenticidade em <code>/verify.php?id=…</code>.</li>
        </ul>
    </div>

    <!-- ─── 9. Ao Vivo ────────────────────────────────────── -->
    <div class="manual-section" id="ao-vivo">
        <h2><i class="fa-solid fa-tower-broadcast"></i> Ao Vivo</h2>
        <p>
            O painel <strong>Ao Vivo</strong> exibe, em tempo real, quem está respondendo um quiz naquele momento.
            Ideal para sessões de treinamento presencial com acompanhamento simultâneo.
        </p>
        <ul class="manual-list">
            <li><i class="fa-solid fa-eye"></i> Visualize nome do participante, quiz em andamento e progresso.</li>
            <li><i class="fa-solid fa-arrows-rotate"></i> A tela atualiza automaticamente a cada poucos segundos, sem necessidade de recarregar.</li>
        </ul>
        <div class="manual-card">
            <strong><i class="fa-solid fa-circle-info"></i> Quando usar</strong>
            Use o painel Ao Vivo durante treinamentos em grupo ou quando precisar confirmar que
            todos os colaboradores completaram a avaliação antes de encerrar uma sessão.
        </div>
    </div>

    <!-- ─── 10. Segurança e Configurações ────────────────── -->
    <div class="manual-section" id="seguranca">
        <h2><i class="fa-solid fa-shield-halved"></i> Segurança e Configurações</h2>
        <p>No menu <strong>Configurações</strong> gerencie sua conta, empresa e acessos administrativos:</p>
        <ul class="manual-list">
            <li><i class="fa-solid fa-id-card"></i> <strong>Perfil:</strong> Atualize o nome de exibição que aparece na navbar do admin.</li>
            <li><i class="fa-solid fa-key"></i> <strong>Senha:</strong> Recomenda-se a alteração periódica da senha de acesso ao painel.</li>
            <li><i class="fa-solid fa-toggle-on"></i> <strong>Auto-cadastro:</strong> Habilite ou desabilite o registro livre de colaboradores no portal.</li>
            <li><i class="fa-solid fa-user-shield"></i> <strong>Administradores:</strong> Crie usuários específicos para cada gestor de setor — cada um acessa apenas os dados da sua empresa.</li>
        </ul>
        <div class="manual-warn">
            <i class="fa-solid fa-triangle-exclamation"></i>
            <strong>Troca de senha:</strong> altere sua senha no primeiro acesso e periodicamente após isso.
            Acesse <em>Configurações → Alterar Senha</em> para atualizar suas credenciais.
        </div>
    </div>

</div><!-- /admin-wrap -->
<?php adminFoot(); ?>
