<?php
/**
 * Job de downgrade automático — cobre o gap que o checkAndApplyDowngrades()
 * chamado em admin/layout.php NÃO cobre: empresas Pro cuja assinatura venceu
 * e cujo admin parou de logar. Sem este cron, essas empresas ficam marcadas
 * como Pro indefinidamente, porque a verificação via painel só roda quando
 * alguém abre uma página do admin.
 *
 * Uso recomendado (CLI, via cron do servidor — cPanel/Locaweb):
 *   php /caminho/completo/para/pagequiz_v1/cron/check-downgrades.php
 *
 * Alternativa (host só permite cron via URL/wget): defina CRON_SECRET no
 * .env/.env.production e agende:
 *   wget -qO- "https://quiz.pageup.net.br/cron/check-downgrades.php?secret=SEU_SEGREDO"
 *
 * Sugestão de frequência: 1x por dia é suficiente (a carência já é de 7 dias
 * e o vencimento avulso é de 30 dias — não há necessidade de rodar de hora em hora).
 */

$isCli = (php_sapi_name() === 'cli');

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/billing.php';

if (!$isCli) {
    header('Content-Type: text/plain; charset=UTF-8');
    header('X-Robots-Tag: noindex, nofollow');

    if (!CRON_SECRET || !hash_equals(CRON_SECRET, $_GET['secret'] ?? '')) {
        http_response_code(403);
        echo "forbidden\n";
        exit;
    }
}

$start       = microtime(true);
$downgraded  = runDowngradeSweep();
$elapsedMs   = (int)round((microtime(true) - $start) * 1000);

$line = sprintf(
    "[%s] check-downgrades: %d empresa(s) rebaixada(s) em %dms\n",
    date('Y-m-d H:i:s'),
    $downgraded,
    $elapsedMs
);

echo $line;
error_log('[cron/check-downgrades] ' . trim($line));
