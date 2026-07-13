<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/layout.php';
requireLogin();

$cid = adminCompanyId();

// ── Download do modelo CSV ────────────────────────────────────────────────────
if (isset($_GET['template'])) {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="modelo-usuarios.csv"');
    echo "\xEF\xBB\xBF"; // BOM para Excel
    echo "nome;email;setor;senha\n";
    echo "Maria Silva;maria@empresa.com;Coleta;senha123\n";
    echo "João Santos;joao@empresa.com;TI;\n";
    echo "Ana Costa;ana@empresa.com;RH;\n";
    exit;
}

$imported  = 0;
$skipped   = [];
$generated = []; // [email => temp_pass] quando senha não fornecida

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
    $file = $_FILES['csv_file'];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        flash('Erro no upload do arquivo.', 'error');
        redirect('users-import.php');
    }

    $handle = fopen($file['tmp_name'], 'r');
    if (!$handle) {
        flash('Não foi possível ler o arquivo.', 'error');
        redirect('users-import.php');
    }

    // Detecta BOM UTF-8
    $bom = fread($handle, 3);
    if ($bom !== "\xEF\xBB\xBF") rewind($handle);

    // Detecta delimitador
    $firstLine = fgets($handle);
    $delim = substr_count($firstLine, ';') >= substr_count($firstLine, ',') ? ';' : ',';
    rewind($handle);
    if ($bom === "\xEF\xBB\xBF") fread($handle, 3); // pula BOM novamente

    // Pula cabeçalho
    fgetcsv($handle, 0, $delim, '"', '');

    $row = 2;
    $db  = getDB();

    while (($cols = fgetcsv($handle, 0, $delim, '"', '')) !== false) {
        if (count($cols) < 2) {
            $skipped[] = "Linha $row: colunas insuficientes";
            $row++;
            continue;
        }

        $name   = trim($cols[0] ?? '');
        $email  = strtolower(trim($cols[1] ?? ''));
        $sector = trim($cols[2] ?? '');
        $pass   = trim($cols[3] ?? '');

        if (!$name || !$email) {
            $skipped[] = "Linha $row: nome ou e-mail ausente";
            $row++;
            continue;
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $skipped[] = "Linha $row: e-mail inválido ($email)";
            $row++;
            continue;
        }

        // Verifica duplicidade dentro do tenant
        if (dbRow("SELECT id FROM users WHERE email = ? AND company_id = ?", [$email, $cid])) {
            $skipped[] = "Linha $row: e-mail já cadastrado ($email)";
            $row++;
            continue;
        }

        // Gera senha temporária se não fornecida
        $tempPass = null;
        if (!$pass || strlen($pass) < 6) {
            $tempPass = substr(str_replace(['+','/','='], '', base64_encode(random_bytes(9))), 0, 10);
            $pass = $tempPass;
        }

        $hash = password_hash($pass, PASSWORD_DEFAULT);
        dbExec(
            "INSERT INTO users (name, email, password_hash, sector, active, company_id) VALUES (?,?,?,?,1,?)",
            [$name, $email, $hash, $sector, $cid]
        );
        $imported++;
        if ($tempPass) $generated[$email] = $tempPass;
        $row++;
    }
    fclose($handle);

    if ($imported === 0) {
        flash('Nenhum usuário importado. Verifique o formato do arquivo.', 'error');
        redirect('users-import.php');
    }

    // Armazena resultado na sessão para exibir na tela (evita re-POST)
    $_SESSION['_import_result'] = [
        'imported'  => $imported,
        'skipped'   => $skipped,
        'generated' => $generated,
    ];
    redirect('users.php?import_done=1#tab-importar');
}

$result = null;
if (isset($_GET['done']) && !empty($_SESSION['_import_result'])) {
    $result = $_SESSION['_import_result'];
    unset($_SESSION['_import_result']);
}

adminHead('Importar Usuários', 'users.php');
?>
<div class="admin-wrap">

<div class="flex items-center gap-8 mb-24" style="font-size:13px">
    <a href="users.php" style="color:var(--blue);text-decoration:none">← Usuários</a>
    <span style="color:var(--gray-300)">/</span>
    <span style="color:var(--gray-500)">Importar CSV</span>
</div>

<h1 style="font-size:22px;font-weight:700;color:var(--gray-800);margin-bottom:24px">
  <i class="fa-solid fa-file-arrow-up"></i> Importar Colaboradores via CSV
</h1>

<?php if ($result): ?>
<!-- Resultado da importação -->
<div class="card" style="border-left:4px solid var(--green);margin-bottom:20px">
  <div class="card-header">
    <h2 style="color:var(--green)"><i class="fa-solid fa-circle-check"></i> Importação concluída</h2>
  </div>
  <p style="font-size:14px;margin-bottom:16px">
    <strong><?= $result['imported'] ?></strong> usuário(s) importado(s) com sucesso.
    <?= count($result['skipped']) ? '<strong style="color:var(--orange)">'.count($result['skipped']).'</strong> linha(s) ignorada(s).' : '' ?>
  </p>

  <?php if (!empty($result['generated'])): ?>
  <div style="margin-bottom:16px">
    <div class="form-label" style="margin-bottom:8px">
      <i class="fa-solid fa-key"></i> Senhas temporárias geradas — repasse para os usuários:
    </div>
    <div style="overflow-x:auto">
    <table style="width:100%;border-collapse:collapse;font-size:13px">
      <thead>
        <tr style="background:var(--gray-100)">
          <th style="text-align:left;padding:8px 12px;border-bottom:1px solid var(--gray-200)">E-mail</th>
          <th style="text-align:left;padding:8px 12px;border-bottom:1px solid var(--gray-200)">Senha temporária</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($result['generated'] as $em => $pw): ?>
      <tr style="border-bottom:1px solid var(--gray-100)">
        <td style="padding:8px 12px"><?= htmlspecialchars($em) ?></td>
        <td style="padding:8px 12px"><code style="background:var(--gray-100);padding:2px 8px;border-radius:4px"><?= htmlspecialchars($pw) ?></code></td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    </div>
    <p style="font-size:12px;color:var(--gray-400);margin-top:8px">
      <i class="fa-solid fa-triangle-exclamation"></i> Anote as senhas antes de sair desta página — elas não serão exibidas novamente.
    </p>
  </div>
  <?php endif; ?>

  <?php if (!empty($result['skipped'])): ?>
  <details style="margin-top:8px">
    <summary style="cursor:pointer;font-size:13px;font-weight:600;color:var(--gray-600)">
      Ver linhas ignoradas (<?= count($result['skipped']) ?>)
    </summary>
    <ul style="margin:10px 0 0 16px;font-size:12px;color:var(--gray-500)">
      <?php foreach ($result['skipped'] as $sk): ?>
      <li><?= htmlspecialchars($sk) ?></li>
      <?php endforeach; ?>
    </ul>
  </details>
  <?php endif; ?>

  <div style="margin-top:20px;display:flex;gap:10px">
    <a href="users.php" class="btn btn-primary"><i class="fa-solid fa-users"></i> Ver Usuários</a>
    <a href="users-import.php" class="btn btn-secondary"><i class="fa-solid fa-redo"></i> Nova Importação</a>
  </div>
</div>

<?php else: ?>

<!-- Instruções -->
<div class="card" style="margin-bottom:20px">
  <div class="card-header"><h2><i class="fa-solid fa-table-list"></i> Formato do CSV</h2></div>
  <div class="alert alert-info" style="margin-bottom:16px">
    Separador ponto-e-vírgula <code>;</code> (ou vírgula <code>,</code>). Primeira linha = cabeçalho (será ignorada).
    <strong>A senha é opcional</strong> — se omitida, uma senha temporária é gerada automaticamente.
  </div>
  <div class="table-wrap" style="margin-bottom:16px">
    <table>
      <thead>
        <tr>
          <th>Col.</th><th>Campo</th><th>Obrigatório</th><th>Exemplo</th>
        </tr>
      </thead>
      <tbody>
        <tr><td>1</td><td>Nome completo</td><td><span class="badge badge-red">Sim</span></td><td>Maria Silva</td></tr>
        <tr><td>2</td><td>E-mail</td><td><span class="badge badge-red">Sim</span></td><td>maria@empresa.com</td></tr>
        <tr><td>3</td><td>Setor</td><td><span class="badge badge-gray">Não</span></td><td>Coleta</td></tr>
        <tr><td>4</td><td>Senha</td><td><span class="badge badge-gray">Não</span></td><td>senha123 (mín. 6 chars)</td></tr>
      </tbody>
    </table>
  </div>
  <a href="users-import.php?template=1" class="btn btn-outline btn-sm">
    <i class="fa-solid fa-download"></i> Baixar Modelo CSV
  </a>
</div>

<!-- Upload -->
<div class="card">
  <div class="card-header"><h2><i class="fa-solid fa-upload"></i> Enviar Arquivo</h2></div>
  <form method="POST" enctype="multipart/form-data">
    <div class="form-group">
      <label class="form-label">Arquivo CSV *</label>
      <input class="form-control" type="file" name="csv_file" accept=".csv,.txt" required/>
      <div class="form-hint">Arquivo .csv com separador ponto-e-vírgula ou vírgula. Máx. 2 MB.</div>
    </div>
    <div class="flex gap-8">
      <button type="submit" class="btn btn-primary"><i class="fa-solid fa-file-import"></i> Importar Usuários</button>
      <a href="users.php" class="btn btn-outline">Cancelar</a>
    </div>
  </form>
</div>

<?php endif; ?>

</div>
<?php adminFoot(); ?>
