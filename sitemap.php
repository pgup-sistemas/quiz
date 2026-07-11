<?php
/**
 * Sitemap XML dinâmico — indexa apenas páginas públicas.
 * Responde em /sitemap.xml via rewrite no .htaccess.
 */
require_once __DIR__ . '/includes/db.php';

$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host   = $_SERVER['HTTP_HOST'] ?? 'quiz.pageup.net.br';
$base   = $scheme . '://' . $host;

// Quizzes ativos (apenas da empresa raiz se não houver subdomain, ou do tenant)
$quizzes = dbRows("
    SELECT id, updated_at, created_at
    FROM quizzes
    WHERE active = 1
      AND (expires_at IS NULL OR expires_at = '' OR expires_at >= date('now','localtime'))
    ORDER BY updated_at DESC
");

header('Content-Type: application/xml; charset=UTF-8');
header('X-Robots-Tag: noindex');
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"
        xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
        xsi:schemaLocation="http://www.sitemaps.org/schemas/sitemap/0.9
        http://www.sitemaps.org/schemas/sitemap/0.9/sitemap.xsd">

  <!-- Homepage -->
  <url>
    <loc><?= htmlspecialchars($base . '/') ?></loc>
    <changefreq>daily</changefreq>
    <priority>1.0</priority>
    <lastmod><?= date('Y-m-d') ?></lastmod>
  </url>

  <!-- Verificação de certificados -->
  <url>
    <loc><?= htmlspecialchars($base . '/verify.php') ?></loc>
    <changefreq>monthly</changefreq>
    <priority>0.5</priority>
  </url>

<?php foreach ($quizzes as $q): ?>
  <!-- Quiz: <?= htmlspecialchars((string)$q['id']) ?> -->
  <url>
    <loc><?= htmlspecialchars($base . '/quiz.php?id=' . (int)$q['id']) ?></loc>
    <changefreq>weekly</changefreq>
    <priority>0.8</priority>
    <lastmod><?= date('Y-m-d', strtotime($q['updated_at'] ?: $q['created_at'])) ?></lastmod>
  </url>
<?php endforeach; ?>

</urlset>
