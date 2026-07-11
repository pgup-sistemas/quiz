<?php
/**
 * SEO helper — gera o bloco completo de meta tags, Open Graph, Twitter Cards,
 * JSON-LD e links canônicos para as páginas públicas do PageQuiz.
 */

function _seoBaseUrl(): string {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host   = $_SERVER['HTTP_HOST'] ?? 'quiz.pageup.net.br';
    return $scheme . '://' . $host;
}

function _seoCurrentUrl(): string {
    return _seoBaseUrl() . ($_SERVER['REQUEST_URI'] ?? '/');
}

/**
 * Retorna a string HTML do bloco SEO completo.
 *
 * @param array $opts {
 *   title        string   Título da página (sem sufixo — o sufixo é adicionado aqui)
 *   description  string   Descrição (até 160 chars)
 *   canonical    string   URL canônica (padrão: URL atual)
 *   image        string   URL absoluta da imagem OG (1200×630 recomendado)
 *   type         string   og:type — 'website' | 'article' (padrão: website)
 *   robots       string   'index,follow' | 'noindex,nofollow' etc.
 *   site_name    string   og:site_name
 *   jsonld       array    Objeto JSON-LD (Schema.org) — serializado como application/ld+json
 *   noindex      bool     Atalho para robots=noindex,nofollow
 * }
 */
function seoHead(array $opts = []): string {
    $base      = _seoBaseUrl();
    $siteName  = $opts['site_name']  ?? 'PageQuiz';
    $title     = $opts['title']      ?? 'PageQuiz · Plataforma de Treinamento e Avaliação';
    $fullTitle = str_contains($title, 'PageQuiz') ? $title : $title . ' · ' . $siteName;
    $desc      = mb_substr(strip_tags($opts['description'] ?? 'Plataforma profissional de treinamento corporativo via quizzes com emissão de certificados verificáveis. Avalie, capacite e certifique sua equipe.'), 0, 160);
    $canonical = $opts['canonical']  ?? _seoCurrentUrl();
    $image     = $opts['image']      ?? $base . '/assets/og-image.jpg';
    $type      = $opts['type']       ?? 'website';
    $robots    = ($opts['noindex'] ?? false) ? 'noindex,nofollow' : ($opts['robots'] ?? 'index,follow');
    $jsonld    = $opts['jsonld']     ?? null;

    // Canonical: remover query strings de páginas de listagem
    $canonical = htmlspecialchars($canonical, ENT_QUOTES);
    $image     = htmlspecialchars($image, ENT_QUOTES);
    $fullTitle = htmlspecialchars($fullTitle, ENT_QUOTES);
    $desc      = htmlspecialchars($desc, ENT_QUOTES);
    $siteName  = htmlspecialchars($siteName, ENT_QUOTES);

    $html  = "<!-- SEO -->\n";
    $html .= "<meta name=\"robots\" content=\"{$robots}\"/>\n";
    $html .= "<link rel=\"canonical\" href=\"{$canonical}\"/>\n";

    // Open Graph
    $html .= "<meta property=\"og:type\"        content=\"{$type}\"/>\n";
    $html .= "<meta property=\"og:site_name\"   content=\"{$siteName}\"/>\n";
    $html .= "<meta property=\"og:title\"       content=\"{$fullTitle}\"/>\n";
    $html .= "<meta property=\"og:description\" content=\"{$desc}\"/>\n";
    $html .= "<meta property=\"og:url\"         content=\"{$canonical}\"/>\n";
    $html .= "<meta property=\"og:image\"       content=\"{$image}\"/>\n";
    $html .= "<meta property=\"og:image:width\"  content=\"1200\"/>\n";
    $html .= "<meta property=\"og:image:height\" content=\"630\"/>\n";
    $html .= "<meta property=\"og:locale\"      content=\"pt_BR\"/>\n";

    // Twitter Cards
    $html .= "<meta name=\"twitter:card\"        content=\"summary_large_image\"/>\n";
    $html .= "<meta name=\"twitter:title\"       content=\"{$fullTitle}\"/>\n";
    $html .= "<meta name=\"twitter:description\" content=\"{$desc}\"/>\n";
    $html .= "<meta name=\"twitter:image\"       content=\"{$image}\"/>\n";

    // JSON-LD
    if ($jsonld) {
        $html .= "<script type=\"application/ld+json\">\n";
        $html .= json_encode($jsonld, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        $html .= "\n</script>\n";
    }

    return $html;
}

/**
 * JSON-LD de Organização para a homepage da plataforma.
 */
function seoJsonLdOrganization(string $name, string $url, string $logo, string $description = ''): array {
    return [
        '@context'    => 'https://schema.org',
        '@type'       => 'Organization',
        'name'        => $name,
        'url'         => $url,
        'logo'        => $logo,
        'description' => $description,
        'founder'     => ['@type' => 'Person', 'name' => 'Oézios Normando'],
        'contactPoint'=> ['@type' => 'ContactPoint', 'email' => 'contato@pageup.net.br', 'contactType' => 'customer support'],
    ];
}

/**
 * JSON-LD de Quiz/Course para a página do quiz.
 */
function seoJsonLdQuiz(array $quiz, string $orgName, string $quizUrl): array {
    return [
        '@context'    => 'https://schema.org',
        '@type'       => 'Course',
        'name'        => $quiz['title'],
        'description' => $quiz['description'] ?: $quiz['title'],
        'url'         => $quizUrl,
        'provider'    => ['@type' => 'Organization', 'name' => $orgName],
        'educationalLevel' => 'Intermediate',
        'inLanguage'  => 'pt-BR',
        'courseMode'  => 'online',
    ];
}

/**
 * JSON-LD para página de verificação de certificado.
 */
function seoJsonLdCertificate(array $participant, array $quiz, string $verifyUrl): array {
    return [
        '@context'       => 'https://schema.org',
        '@type'          => 'EducationalOccupationalCredential',
        'name'           => 'Certificado de Conclusão — ' . $quiz['title'],
        'description'    => 'Certificado emitido para ' . $participant['name'] . ' após conclusão do quiz com aprovação.',
        'url'            => $verifyUrl,
        'credentialCategory' => 'certificate',
        'recognizedBy'   => ['@type' => 'Organization', 'name' => 'PageQuiz · PageUp Sistemas'],
        'validFrom'      => $participant['completed_at'] ?? $participant['started_at'],
        'about'          => ['@type' => 'Course', 'name' => $quiz['title']],
    ];
}
