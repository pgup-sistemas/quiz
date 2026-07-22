<?php
require_once __DIR__ . '/config.php';

function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
        initDB($pdo);
    }
    return $pdo;
}

/**
 * Verifica se uma coluna existe numa tabela (usado para migrações incrementais futuras).
 */
function columnExists(PDO $db, string $table, string $column): bool {
    $st = $db->prepare("SELECT COUNT(*) FROM information_schema.columns
                         WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?");
    $st->execute([$table, $column]);
    return (int)$st->fetchColumn() > 0;
}

function initDB(PDO $db): void {
    // ── Tabelas SaaS ─────────────────────────────────────────────────────────
    $db->exec("
    CREATE TABLE IF NOT EXISTS companies (
        id                  INT PRIMARY KEY AUTO_INCREMENT,
        name                VARCHAR(255) NOT NULL,
        slug                VARCHAR(150) NOT NULL UNIQUE,
        cnpj                VARCHAR(20)  DEFAULT NULL,
        email               VARCHAR(255) NOT NULL,
        plan                VARCHAR(20)  NOT NULL DEFAULT 'free',
        status              VARCHAR(20)  NOT NULL DEFAULT 'active',
        primary_color       VARCHAR(20)  NOT NULL DEFAULT '#219EBC',
        logo_path           VARCHAR(255) DEFAULT NULL,
        allow_self_register TINYINT(1)   NOT NULL DEFAULT 1,
        created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

    CREATE TABLE IF NOT EXISTS system_settings (
        `key`       VARCHAR(100) PRIMARY KEY,
        value       TEXT NOT NULL,
        description VARCHAR(255) DEFAULT '',
        updated_at  DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

    CREATE TABLE IF NOT EXISTS super_admins (
        id            INT PRIMARY KEY AUTO_INCREMENT,
        username      VARCHAR(255) NOT NULL UNIQUE,
        password_hash VARCHAR(255) NOT NULL,
        name          VARCHAR(255) DEFAULT '',
        active        TINYINT(1) DEFAULT 1,
        created_at    DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

    CREATE TABLE IF NOT EXISTS audit_log (
        id                 INT PRIMARY KEY AUTO_INCREMENT,
        actor_type         VARCHAR(50) NOT NULL,
        actor_id           INT NOT NULL DEFAULT 0,
        action             VARCHAR(100) NOT NULL,
        target_company_id  INT DEFAULT NULL,
        ip                 VARCHAR(64) DEFAULT '',
        detail             TEXT DEFAULT NULL,
        created_at         DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

    CREATE TABLE IF NOT EXISTS subscriptions (
        id                   INT PRIMARY KEY AUTO_INCREMENT,
        company_id           INT NOT NULL,
        efi_subscription_id  VARCHAR(100) DEFAULT NULL,
        efi_charge_id        VARCHAR(100) DEFAULT NULL,
        type                 VARCHAR(20) NOT NULL DEFAULT 'pix',
        status               VARCHAR(20) NOT NULL DEFAULT 'pending',
        amount               INT NOT NULL DEFAULT 0,
        next_billing_at      DATETIME DEFAULT NULL,
        grace_until          DATETIME DEFAULT NULL,
        pix_txid             VARCHAR(100) DEFAULT NULL,
        pix_qrcode           TEXT DEFAULT NULL,
        pix_copiaecola       TEXT DEFAULT NULL,
        payment_link_url     VARCHAR(500) DEFAULT NULL,
        created_at           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

    CREATE TABLE IF NOT EXISTS payment_events (
        id                    INT PRIMARY KEY AUTO_INCREMENT,
        company_id            INT DEFAULT NULL,
        subscription_id       INT DEFAULT NULL,
        efi_notification_id   VARCHAR(150) UNIQUE,
        event_type            VARCHAR(100) NOT NULL,
        raw_payload           TEXT DEFAULT NULL,
        processed             TINYINT(1) NOT NULL DEFAULT 0,
        created_at            DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    // Migração incremental: nota da ativação manual de Pro (approve_pro)
    if (!columnExists($db, 'subscriptions', 'notes')) {
        $db->exec("ALTER TABLE subscriptions ADD COLUMN notes VARCHAR(255) DEFAULT NULL AFTER payment_link_url");
    }

    // Migração incremental: dispensar checklist de primeiros passos no dashboard
    if (!columnExists($db, 'companies', 'onboarding_dismissed')) {
        $db->exec("ALTER TABLE companies ADD COLUMN onboarding_dismissed TINYINT(1) NOT NULL DEFAULT 0 AFTER allow_self_register");
    }

    // Seeds: empresa base (Alphaclin = id 1)
    $db->exec("INSERT IGNORE INTO companies (id, name, slug, email, plan, status)
               VALUES (1, 'Alphaclin', 'alphaclin', 'comunicacao@alphaclin.net.br', 'pro', 'active')");

    // Seeds: configurações globais
    $settingsSeeds = [
        ['free_quiz_limit',   '12',                       'Limite de quizzes no plano Free'],
        ['app_name',          'PageQuiz',                 'Nome da plataforma'],
        ['support_email',     'contato@pageup.net.br',    'E-mail de suporte exibido no upgrade'],
        // E-mail transacional
        ['resend_api_key',    '',                         'API Key da Resend (resend.com) — deixe vazio para usar PHP mail()'],
        ['mail_from',         'noreply@quiz.pageup.net.br','Endereço remetente dos e-mails transacionais'],
        ['mail_from_name',    'PageQuiz',                 'Nome exibido como remetente'],
        // EFI Bank
        ['pro_price_monthly', '4990',                     'Preço mensal do Pro em centavos (4990 = R$49,90)'],
        ['efi_client_id',     '',                         'Client ID EFI Bank'],
        ['efi_client_secret', '',                         'Client Secret EFI Bank'],
        ['efi_sandbox',       '1',                        '1=sandbox (homologacao), 0=producao'],
        ['efi_pix_key',       '',                         'Chave PIX da conta EFI (e-mail, CPF, CNPJ ou aleatoria)'],
        ['efi_cert_path',     'certs/efi-sandbox.p12',    'Caminho do certificado .p12 relativo a raiz do projeto'],
        ['efi_cert_password', '',                         'Senha do certificado .p12 (deixe vazio se nao tiver)'],
    ];
    $stmt = $db->prepare("INSERT IGNORE INTO system_settings (`key`, value, description) VALUES (?,?,?)");
    foreach ($settingsSeeds as $s) $stmt->execute($s);

    // Seed super-admin
    $saExists = $db->query("SELECT COUNT(*) FROM super_admins")->fetchColumn();
    if ($saExists == 0) {
        $saHash = password_hash('Admin@2026!', PASSWORD_DEFAULT);
        $db->prepare("INSERT INTO super_admins (username, password_hash, name) VALUES (?,?,?)")
           ->execute(['pageupsistemas@gmail.com', $saHash, 'PageUp Sistemas']);
    }

    // ── Tabelas de domínio ──────────────────────────────────────────────────
    $db->exec("
    CREATE TABLE IF NOT EXISTS admins (
        id              INT PRIMARY KEY AUTO_INCREMENT,
        username        VARCHAR(255) NOT NULL UNIQUE,
        password_hash   VARCHAR(255) NOT NULL,
        name            VARCHAR(255) DEFAULT 'Administrador',
        first_login     TINYINT(1) NOT NULL DEFAULT 0,
        active          TINYINT(1) NOT NULL DEFAULT 1,
        reset_token     VARCHAR(255) DEFAULT NULL,
        reset_expires   DATETIME DEFAULT NULL,
        company_id      INT NOT NULL DEFAULT 1,
        created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_admins_company (company_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

    CREATE TABLE IF NOT EXISTS sectors (
        id         INT PRIMARY KEY AUTO_INCREMENT,
        company_id INT NOT NULL DEFAULT 1,
        name       VARCHAR(150) NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_sector_company_name (company_id, name),
        INDEX idx_sectors_company (company_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

    CREATE TABLE IF NOT EXISTS quizzes (
        id                  INT PRIMARY KEY AUTO_INCREMENT,
        title               VARCHAR(255) NOT NULL,
        description         TEXT DEFAULT NULL,
        sector              VARCHAR(150) DEFAULT 'Geral',
        created_by          VARCHAR(255) DEFAULT '',
        time_per_question   INT DEFAULT 30,
        pass_percentage     INT DEFAULT 70,
        max_questions       INT DEFAULT 0,
        allow_retake        TINYINT(1) DEFAULT 1,
        show_feedback       TINYINT(1) DEFAULT 1,
        has_certificate     TINYINT(1) DEFAULT 1,
        randomize           TINYINT(1) DEFAULT 0,
        active              TINYINT(1) DEFAULT 1,
        expires_at          DATETIME DEFAULT NULL,
        visible_from        DATETIME DEFAULT NULL,
        visibility          VARCHAR(20) NOT NULL DEFAULT 'all',
        company_id          INT NOT NULL DEFAULT 1,
        created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_quizzes_company (company_id, active)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

    CREATE TABLE IF NOT EXISTS questions (
        id              INT PRIMARY KEY AUTO_INCREMENT,
        quiz_id         INT NOT NULL,
        question_text   TEXT NOT NULL,
        category        VARCHAR(150) DEFAULT '',
        option_a        TEXT NOT NULL,
        option_b        TEXT NOT NULL,
        option_c        TEXT DEFAULT NULL,
        option_d        TEXT DEFAULT NULL,
        correct_answer  INT NOT NULL DEFAULT 0,
        explanation     TEXT DEFAULT NULL,
        sort_order      INT DEFAULT 0,
        company_id      INT NOT NULL DEFAULT 1,
        created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
        CONSTRAINT fk_questions_quiz FOREIGN KEY (quiz_id) REFERENCES quizzes(id) ON DELETE CASCADE,
        INDEX idx_questions_company (company_id, quiz_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

    CREATE TABLE IF NOT EXISTS users (
        id              INT PRIMARY KEY AUTO_INCREMENT,
        company_id      INT NOT NULL DEFAULT 1,
        name            VARCHAR(255) NOT NULL,
        email           VARCHAR(255) NOT NULL,
        password_hash   VARCHAR(255) NOT NULL,
        sector          VARCHAR(150) DEFAULT '',
        active          TINYINT(1) DEFAULT 1,
        reset_token     VARCHAR(255) DEFAULT NULL,
        reset_expires   DATETIME DEFAULT NULL,
        last_login      DATETIME DEFAULT NULL,
        created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_user_company_email (company_id, email),
        INDEX idx_users_company (company_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

    CREATE TABLE IF NOT EXISTS participants (
        id              INT PRIMARY KEY AUTO_INCREMENT,
        quiz_id         INT DEFAULT NULL,
        name            VARCHAR(255) NOT NULL,
        email           VARCHAR(255) DEFAULT '',
        sector          VARCHAR(150) DEFAULT '',
        score           INT DEFAULT 0,
        total_questions INT DEFAULT 0,
        percentage      DECIMAL(6,2) DEFAULT 0,
        passed          TINYINT(1) DEFAULT 0,
        avg_time        DECIMAL(8,2) DEFAULT 0,
        started_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
        last_activity   DATETIME DEFAULT CURRENT_TIMESTAMP,
        completed_at    DATETIME DEFAULT NULL,
        verify_code     VARCHAR(20) DEFAULT NULL,
        user_id         INT DEFAULT NULL,
        company_id      INT NOT NULL DEFAULT 1,
        CONSTRAINT fk_participants_quiz FOREIGN KEY (quiz_id) REFERENCES quizzes(id) ON DELETE SET NULL,
        CONSTRAINT fk_participants_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
        INDEX idx_participants_user (user_id),
        INDEX idx_participants_company (company_id, quiz_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

    CREATE TABLE IF NOT EXISTS answers (
        id              INT PRIMARY KEY AUTO_INCREMENT,
        participant_id  INT NOT NULL,
        company_id      INT NOT NULL DEFAULT 1,
        question_id     INT NOT NULL,
        selected_answer INT DEFAULT -1,
        is_correct      TINYINT(1) DEFAULT 0,
        time_taken      INT DEFAULT 0,
        CONSTRAINT fk_answers_participant FOREIGN KEY (participant_id) REFERENCES participants(id) ON DELETE CASCADE,
        INDEX idx_answers_company (company_id, participant_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

    CREATE TABLE IF NOT EXISTS quiz_assignments (
        id         INT PRIMARY KEY AUTO_INCREMENT,
        quiz_id    INT NOT NULL,
        sector_id  INT NOT NULL,
        CONSTRAINT fk_qa_quiz   FOREIGN KEY (quiz_id)   REFERENCES quizzes(id) ON DELETE CASCADE,
        CONSTRAINT fk_qa_sector FOREIGN KEY (sector_id) REFERENCES sectors(id) ON DELETE CASCADE,
        UNIQUE KEY uniq_quiz_sector (quiz_id, sector_id),
        INDEX idx_qa_quiz (quiz_id),
        INDEX idx_qa_sector (sector_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

    CREATE TABLE IF NOT EXISTS invites (
        id         INT PRIMARY KEY AUTO_INCREMENT,
        company_id INT NOT NULL,
        email      VARCHAR(255) DEFAULT NULL,
        sector     VARCHAR(150) DEFAULT '',
        token      VARCHAR(255) NOT NULL UNIQUE,
        expires_at DATETIME NOT NULL,
        used_at    DATETIME DEFAULT NULL,
        created_by INT NOT NULL DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_invites_company (company_id),
        INDEX idx_invites_token (token)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

    CREATE TABLE IF NOT EXISTS contact_messages (
        id         INT PRIMARY KEY AUTO_INCREMENT,
        company_id INT NOT NULL DEFAULT 1,
        name       VARCHAR(255) NOT NULL,
        email      VARCHAR(255) NOT NULL,
        subject    VARCHAR(255) NOT NULL,
        message    TEXT NOT NULL,
        ip         VARCHAR(64) DEFAULT '',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    // ── Seed setores iniciais ────────────────────────────────────────────────
    $sc = $db->query("SELECT COUNT(*) FROM sectors")->fetchColumn();
    if ($sc == 0) {
        $existing = $db->query("SELECT DISTINCT sector FROM quizzes WHERE sector != '' AND sector IS NOT NULL")->fetchAll(PDO::FETCH_COLUMN);
        $stmt = $db->prepare("INSERT IGNORE INTO sectors (company_id, name) VALUES (1,?)");
        foreach (array_filter($existing) as $s) $stmt->execute([$s]);
        $db->exec("INSERT IGNORE INTO sectors (company_id, name) VALUES (1,'Geral')");
    }

    // Seed default admin if none exists
    $count = $db->query("SELECT COUNT(*) FROM admins")->fetchColumn();
    if ($count == 0) {
        $hash = password_hash(DEFAULT_ADMIN_PASS, PASSWORD_DEFAULT);
        $db->prepare("INSERT INTO admins (username, password_hash, name) VALUES (?,?,?)")
           ->execute([DEFAULT_ADMIN_USER, $hash, 'Administrador']);
    }

    // Seed demo quiz if none exists
    $qcount = $db->query("SELECT COUNT(*) FROM quizzes")->fetchColumn();
    if ($qcount == 0) {
        seedDemoQuiz($db);
    }
}

function seedDemoQuiz(PDO $db): void {
    $db->exec("
    INSERT INTO quizzes (title, description, sector, created_by, time_per_question, pass_percentage)
    VALUES (
        'Quiz de Segurança 2025',
        'Quiz obrigatório de segurança. Os resultados são utilizados como evidência de treinamento e capacitação profissional.',
        'Todos os Setores',
        'Administrador',
        30,
        70
    );
    ");
    $qid = $db->lastInsertId();

    $questions = [
        ['Biossegurança','Qual EPI é obrigatório ao manusear amostras biológicas na coleta?',
         'Máscara N95','Luvas de nitrila e avental','Óculos de proteção','Capote estéril',1,
         'Luvas e avental são os EPIs mínimos obrigatórios para coleta, conforme NR-32.'],
        ['Biossegurança','O que fazer imediatamente após acidente perfurocortante?',
         'Lavar com álcool 70%','Apertar o local para sangrar e lavar com água e sabão','Cobrir com curativo','Aplicar antisséptico tópico',1,
         'Deve-se lavar abundantemente com água e sabão. Apertar o ferimento aumenta o risco de transmissão.'],
        ['LGPD','Qual dado do paciente é considerado dado sensível pela LGPD?',
         'Nome completo','CPF','Diagnóstico médico','Endereço',2,
         'Dados de saúde, como diagnósticos, são dados sensíveis com proteção reforçada pela LGPD.'],
        ['LGPD','Por quanto tempo resultados de exames devem ser mantidos em arquivo?',
         '2 anos','5 anos','10 anos','20 anos',1,
         'Conforme CFM e regulações ANVISA, prontuários e resultados devem ser mantidos por no mínimo 5 anos.'],
        ['Qualidade','O que significa a sigla ONA?',
         'Organização Nacional de Acreditação','Ordem Nacional de Análises','Órgão Nacional de Auditoria','Organização de Normas Analíticas',0,
         'ONA é a Organização Nacional de Acreditação, responsável por avaliar e certificar a qualidade em saúde.'],
        ['Qualidade','Qual a temperatura correta para armazenamento de reagentes refrigerados?',
         'Entre 0°C e 2°C','Entre 2°C e 8°C','Entre 8°C e 15°C','Entre 15°C e 25°C',1,
         'Reagentes refrigerados devem ser mantidos entre 2°C e 8°C para garantir sua estabilidade.'],
        ['Segurança de Dados','O que fazer ao identificar um e-mail suspeito de phishing?',
         'Clicar no link para verificar','Encaminhar ao TI imediatamente e não interagir','Responder pedindo mais informações','Excluir silenciosamente',1,
         'E-mails suspeitos devem ser encaminhados ao TI para análise. Nunca clique em links ou anexos não solicitados.'],
        ['Coleta','Qual tubo é utilizado para hemograma completo?',
         'Tubo verde (heparina)','Tubo roxo (EDTA)','Tubo vermelho (seco)','Tubo azul (citrato)',1,
         'O tubo com EDTA (tampa roxa/lilás) é o indicado para hematologia, incluindo hemograma.'],
    ];

    $stmt = $db->prepare("
        INSERT INTO questions (quiz_id, question_text, category, option_a, option_b, option_c, option_d,
                               correct_answer, explanation, sort_order)
        VALUES (?,?,?,?,?,?,?,?,?,?)
    ");

    foreach ($questions as $i => $q) {
        $stmt->execute([$qid, $q[1], $q[0], $q[2], $q[3], $q[4], $q[5], $q[6], $q[7], $i+1]);
    }
}

/* ─── Query helpers ──────────────────────────────────────────── */

function dbRow(string $sql, array $params = []): ?array {
    $st = getDB()->prepare($sql);
    $st->execute($params);
    $r = $st->fetch();
    return $r ?: null;
}

function dbRows(string $sql, array $params = []): array {
    $st = getDB()->prepare($sql);
    $st->execute($params);
    return $st->fetchAll();
}

function dbExec(string $sql, array $params = []): void {
    getDB()->prepare($sql)->execute($params);
}

function dbLastId(): string {
    return getDB()->lastInsertId();
}
