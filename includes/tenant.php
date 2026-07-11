<?php
require_once __DIR__ . '/db.php';

/**
 * Resolve o tenant pelo subdomínio do HTTP_HOST.
 * Retorna o array da empresa ou null para domínios sem tenant (superadmin, cadastro, etc.).
 * Suspensão → redireciona para /suspended.php.
 * Não encontrado → 404.
 */
function resolveTenant(): ?array {
    if (isset($_SESSION['tenant_company'])) {
        return $_SESSION['tenant_company'];
    }

    $slug = null;

    // 1. Tenta subdomínio (produção: alphaclin.pagequiz.com.br)
    $host = strtolower($_SERVER['HTTP_HOST'] ?? '');
    $host = explode(':', $host)[0];
    $noTenantHosts = ['localhost', 'pagequiz', '127.0.0.1'];
    if (!in_array($host, $noTenantHosts) && !filter_var($host, FILTER_VALIDATE_IP)) {
        $sub = strtok($host, '.');
        if ($sub && $sub !== $host && $sub !== 'superadmin') {
            $slug = $sub;
        }
    }

    // 2. Fallback: ?c=slug (desenvolvimento local ou link de convite)
    if (!$slug && !empty($_GET['c'])) {
        $slug = preg_replace('/[^a-z0-9\-]/', '', strtolower(trim($_GET['c'])));
    }

    // 3. Fallback: slug salvo na sessão por acesso anterior com ?c=
    if (!$slug && !empty($_SESSION['_tenant_slug'])) {
        $slug = $_SESSION['_tenant_slug'];
    }

    if (!$slug) {
        return null;
    }

    $company = dbRow("SELECT * FROM companies WHERE slug = ?", [$slug]);

    if (!$company) {
        // Slug inválido via ?c= — ignora silenciosamente (não dá 404)
        if (!empty($_GET['c'])) return null;
        http_response_code(404);
        include __DIR__ . '/../404.php';
        exit;
    }

    if ($company['status'] === 'suspended') {
        header('Location: /suspended.php');
        exit;
    }

    // Persiste slug na sessão para que próximas páginas (login, register) mantenham o tenant
    $_SESSION['_tenant_slug']      = $slug;
    $_SESSION['tenant_company']    = $company;
    $_SESSION['tenant_company_id'] = (int)$company['id'];

    return $company;
}

/**
 * Remove o tenant da sessão (logout do user ou impersonation).
 */
function clearTenantSession(): void {
    unset($_SESSION['tenant_company'], $_SESSION['tenant_company_id'], $_SESSION['_tenant_slug']);
}

/**
 * Retorna o ID do tenant da sessão atual.
 * Lança RuntimeException se não houver tenant resolvido.
 */
function tenantId(): int {
    if (!isset($_SESSION['tenant_company_id'])) {
        throw new RuntimeException('Tenant não resolvido. Chame resolveTenant() antes de tenantId().');
    }
    return (int)$_SESSION['tenant_company_id'];
}

/**
 * Retorna o array completo da empresa do tenant atual.
 */
function tenantCompany(): array {
    if (!isset($_SESSION['tenant_company'])) {
        throw new RuntimeException('Tenant não resolvido.');
    }
    return $_SESSION['tenant_company'];
}

/**
 * Retorna os limites do plano.
 * 'quizzes' = -1 significa ilimitado.
 */
function planLimits(string $plan): array {
    $row = dbRow("SELECT value FROM system_settings WHERE key = 'free_quiz_limit'");
    $freeLimit = (int)($row['value'] ?? 12);

    return match ($plan) {
        'pro'   => ['quizzes' => -1,         'unlimited' => true,  'custom_brand' => true],
        default => ['quizzes' => $freeLimit, 'unlimited' => false, 'custom_brand' => false],
    };
}

/**
 * Verifica se a empresa do tenant atual pode criar mais um quiz.
 */
function tenantCanCreateQuiz(): bool {
    $companyId = tenantId();
    $company   = tenantCompany();
    $limits    = planLimits($company['plan']);

    if ($limits['unlimited']) return true;

    $count = (int)(dbRow(
        "SELECT COUNT(*) AS c FROM quizzes WHERE company_id = ? AND active = 1",
        [$companyId]
    )['c'] ?? 0);

    return $count < $limits['quizzes'];
}

/**
 * Verifica uso atual de quizzes vs. limite (para exibição de banner).
 * Retorna ['used' => N, 'limit' => N, 'pct' => 0-100, 'unlimited' => bool]
 */
function tenantQuizUsage(): array {
    $companyId = tenantId();
    $company   = tenantCompany();
    $limits    = planLimits($company['plan']);

    $used = (int)(dbRow(
        "SELECT COUNT(*) AS c FROM quizzes WHERE company_id = ? AND active = 1",
        [$companyId]
    )['c'] ?? 0);

    if ($limits['unlimited']) {
        return ['used' => $used, 'limit' => -1, 'pct' => 0, 'unlimited' => true];
    }

    $pct = $limits['quizzes'] > 0 ? (int)round($used / $limits['quizzes'] * 100) : 0;
    return ['used' => $used, 'limit' => $limits['quizzes'], 'pct' => $pct, 'unlimited' => false];
}

/**
 * Converte nome de empresa em slug único.
 * Ex.: "Clínica São João" → "clinica-sao-joao" (ou "clinica-sao-joao-2" se já existir)
 */
function slugUnico(string $name): string {
    $slug = mb_strtolower($name, 'UTF-8');
    // Remove acentos via transliteração manual
    $from = ['á','à','ã','â','ä','é','è','ê','ë','í','ì','î','ï','ó','ò','õ','ô','ö','ú','ù','û','ü','ç','ñ',
             'Á','À','Ã','Â','Ä','É','È','Ê','Ë','Í','Ì','Î','Ï','Ó','Ò','Õ','Ô','Ö','Ú','Ù','Û','Ü','Ç','Ñ'];
    $to   = ['a','a','a','a','a','e','e','e','e','i','i','i','i','o','o','o','o','o','u','u','u','u','c','n',
             'a','a','a','a','a','e','e','e','e','i','i','i','i','o','o','o','o','o','u','u','u','u','c','n'];
    $slug = str_replace($from, $to, $slug);
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
    $slug = trim($slug, '-');
    $slug = preg_replace('/-+/', '-', $slug);

    if (!$slug) $slug = 'empresa';

    // Garante unicidade
    $base    = $slug;
    $counter = 1;
    while (dbRow("SELECT id FROM companies WHERE slug = ?", [$slug])) {
        $counter++;
        $slug = "$base-$counter";
    }

    return $slug;
}
