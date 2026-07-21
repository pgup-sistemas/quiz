<?php
/**
 * Script one-shot: copia todos os dados de data/quiz.db (SQLite) para o MySQL
 * já com schema criado por includes/db.php -> initDB(). Roda manual via CLI.
 *
 * Uso: php tmp/migrate_sqlite_to_mysql.php
 */
chdir(__DIR__ . '/..');
require 'includes/db.php'; // garante que initDB() já rodou no MySQL (getDB() chama initDB)

$sqlitePath = __DIR__ . '/../data/quiz.db';
if (!is_file($sqlitePath)) {
    fwrite(STDERR, "Arquivo SQLite não encontrado: $sqlitePath\n");
    exit(1);
}

$sqlite = new PDO('sqlite:' . $sqlitePath);
$sqlite->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$sqlite->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

$mysql = getDB();

// Ordem respeita dependências de FK (pais antes de filhos)
$tables = [
    'companies', 'system_settings', 'super_admins', 'audit_log', 'subscriptions', 'payment_events',
    'admins', 'sectors', 'quizzes', 'questions', 'users', 'participants', 'answers',
    'quiz_assignments', 'invites', 'contact_messages',
];

$sqliteTables = array_column(
    $sqlite->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll(),
    'name'
);

$mysql->exec('SET FOREIGN_KEY_CHECKS=0');

$summary = [];
foreach ($tables as $table) {
    if (!in_array($table, $sqliteTables, true)) {
        $summary[$table] = 'tabela não existe no SQLite (pulada)';
        continue;
    }

    // Esvazia a tabela no MySQL antes de recarregar (idempotência do script)
    $mysql->exec("DELETE FROM `$table`");

    $rows = $sqlite->query("SELECT * FROM `$table`")->fetchAll();
    if (!$rows) {
        $summary[$table] = '0 linhas (vazia no SQLite)';
        continue;
    }

    $cols = array_keys($rows[0]);
    $colList = implode(', ', array_map(fn($c) => "`$c`", $cols));
    $placeholders = implode(', ', array_fill(0, count($cols), '?'));
    $stmt = $mysql->prepare("INSERT INTO `$table` ($colList) VALUES ($placeholders)");

    $n = 0;
    foreach ($rows as $row) {
        // Normaliza valores vazios de datetime para NULL (SQLite às vezes tem '' onde MySQL exige NULL/DATETIME válido)
        foreach ($row as $k => $v) {
            if ($v === '' && preg_match('/_at$|_expires$|last_login$|last_activity$|completed_at$|visible_from$/', $k)) {
                $row[$k] = null;
            }
        }
        $stmt->execute(array_values($row));
        $n++;
    }
    $summary[$table] = "$n linhas migradas";

    // Ajusta AUTO_INCREMENT para não colidir com os IDs importados (tabelas com PK `id`)
    if (in_array('id', $cols, true)) {
        $maxId = (int)$mysql->query("SELECT COALESCE(MAX(id),0) FROM `$table`")->fetchColumn();
        if ($maxId > 0) {
            $mysql->exec("ALTER TABLE `$table` AUTO_INCREMENT = " . ($maxId + 1));
        }
    }
}

$mysql->exec('SET FOREIGN_KEY_CHECKS=1');

echo "=== Migração concluída ===\n";
foreach ($summary as $table => $result) {
    echo str_pad($table, 22) . " → $result\n";
}
