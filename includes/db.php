<?php
require_once __DIR__ . '/config.php';

function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dir = dirname(DB_PATH);
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        $pdo = new PDO('sqlite:' . DB_PATH);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->exec('PRAGMA foreign_keys = ON');
        initDB($pdo);
    }
    return $pdo;
}

function initDB(PDO $db): void {
    $db->exec("
    CREATE TABLE IF NOT EXISTS admins (
        id              INTEGER PRIMARY KEY AUTOINCREMENT,
        username        TEXT    UNIQUE NOT NULL,
        password_hash   TEXT    NOT NULL,
        name            TEXT    DEFAULT 'Administrador',
        created_at      TEXT    DEFAULT (datetime('now','localtime'))
    );

    CREATE TABLE IF NOT EXISTS sectors (
        id         INTEGER PRIMARY KEY AUTOINCREMENT,
        name       TEXT    UNIQUE NOT NULL,
        created_at TEXT    DEFAULT (datetime('now','localtime'))
    );

    CREATE TABLE IF NOT EXISTS quizzes (
        id                  INTEGER PRIMARY KEY AUTOINCREMENT,
        title               TEXT    NOT NULL,
        description         TEXT    DEFAULT '',
        sector              TEXT    DEFAULT 'Geral',
        created_by          TEXT    DEFAULT '',
        time_per_question   INTEGER DEFAULT 30,
        pass_percentage     INTEGER DEFAULT 70,
        max_questions       INTEGER DEFAULT 0,
        allow_retake        INTEGER DEFAULT 1,
        show_feedback       INTEGER DEFAULT 1,
        has_certificate     INTEGER DEFAULT 1,
        randomize           INTEGER DEFAULT 0,
        active              INTEGER DEFAULT 1,
        expires_at          TEXT    DEFAULT NULL,
        created_at          TEXT    DEFAULT (datetime('now','localtime')),
        updated_at          TEXT    DEFAULT (datetime('now','localtime'))
    );

    CREATE TABLE IF NOT EXISTS questions (
        id              INTEGER PRIMARY KEY AUTOINCREMENT,
        quiz_id         INTEGER NOT NULL REFERENCES quizzes(id) ON DELETE CASCADE,
        question_text   TEXT    NOT NULL,
        category        TEXT    DEFAULT '',
        option_a        TEXT    NOT NULL,
        option_b        TEXT    NOT NULL,
        option_c        TEXT    DEFAULT '',
        option_d        TEXT    DEFAULT '',
        correct_answer  INTEGER NOT NULL DEFAULT 0,
        explanation     TEXT    DEFAULT '',
        sort_order      INTEGER DEFAULT 0,
        created_at      TEXT    DEFAULT (datetime('now','localtime'))
    );

    CREATE TABLE IF NOT EXISTS participants (
        id              INTEGER PRIMARY KEY AUTOINCREMENT,
        quiz_id         INTEGER NOT NULL REFERENCES quizzes(id) ON DELETE SET NULL,
        name            TEXT    NOT NULL,
        email           TEXT    DEFAULT '',
        sector          TEXT    DEFAULT '',
        score           INTEGER DEFAULT 0,
        total_questions INTEGER DEFAULT 0,
        percentage      REAL    DEFAULT 0,
        passed          INTEGER DEFAULT 0,
        avg_time        REAL    DEFAULT 0,
        started_at      TEXT    DEFAULT (datetime('now','localtime')),
        last_activity   TEXT    DEFAULT (datetime('now','localtime')),
        completed_at    TEXT,
        verify_code     TEXT    DEFAULT NULL
    );

    CREATE TABLE IF NOT EXISTS answers (
        id              INTEGER PRIMARY KEY AUTOINCREMENT,
        participant_id  INTEGER NOT NULL REFERENCES participants(id) ON DELETE CASCADE,
        question_id     INTEGER NOT NULL,
        selected_answer INTEGER DEFAULT -1,
        is_correct      INTEGER DEFAULT 0,
        time_taken      INTEGER DEFAULT 0
    );

    CREATE TABLE IF NOT EXISTS users (
        id              INTEGER PRIMARY KEY AUTOINCREMENT,
        name            TEXT    NOT NULL,
        email           TEXT    NOT NULL UNIQUE,
        password_hash   TEXT    NOT NULL,
        sector          TEXT    DEFAULT '',
        active          INTEGER DEFAULT 1,
        reset_token     TEXT    DEFAULT NULL,
        reset_expires   TEXT    DEFAULT NULL,
        last_login      TEXT    DEFAULT NULL,
        created_at      TEXT    DEFAULT (datetime('now','localtime'))
    );
    ");

    // Migrations — add columns to existing DBs that were created before these fields existed
    $cols = array_column($db->query("PRAGMA table_info(quizzes)")->fetchAll(PDO::FETCH_ASSOC), 'name');
    if (!in_array('expires_at',    $cols)) $db->exec("ALTER TABLE quizzes ADD COLUMN expires_at TEXT DEFAULT NULL");
    if (!in_array('max_questions', $cols)) $db->exec("ALTER TABLE quizzes ADD COLUMN max_questions INTEGER DEFAULT 0");
    if (!in_array('has_certificate', $cols)) $db->exec("ALTER TABLE quizzes ADD COLUMN has_certificate INTEGER DEFAULT 1");

    $pCols = array_column($db->query("PRAGMA table_info(participants)")->fetchAll(PDO::FETCH_ASSOC), 'name');
    if (!in_array('last_activity', $pCols)) {
        $db->exec("ALTER TABLE participants ADD COLUMN last_activity TEXT");
        $db->exec("UPDATE participants SET last_activity = datetime('now','localtime') WHERE last_activity IS NULL");
    }
    if (!in_array('verify_code',   $pCols)) {
        $db->exec("ALTER TABLE participants ADD COLUMN verify_code TEXT DEFAULT NULL");
    }

    // Seed initial sectors from existing quizzes if sectors table is empty
    $sc = $db->query("SELECT COUNT(*) FROM sectors")->fetchColumn();
    if ($sc == 0) {
        $existing = $db->query("SELECT DISTINCT sector FROM quizzes WHERE sector != '' AND sector IS NOT NULL")->fetchAll(PDO::FETCH_COLUMN);
        $stmt = $db->prepare("INSERT OR IGNORE INTO sectors (name) VALUES (?)");
        foreach (array_filter($existing) as $s) $stmt->execute([$s]);
        // Always ensure Geral exists
        $db->exec("INSERT OR IGNORE INTO sectors (name) VALUES ('Geral')");
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
