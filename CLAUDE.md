# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

---

## 1. Identidade do projeto

- **Nome:** PageQuiz
- **Domínio de negócio:** Plataforma SaaS de treinamento e avaliação corporativa via quizzes, com emissão de certificados verificáveis. Cada empresa contratante (tenant) tem seu próprio espaço isolado de quizzes, usuários e resultados.
- **Desenvolvedor:** PageUp Sistemas / Oézios Normando
- **URL de produção:** https://quiz.pageup.net.br

---

## 2. Stack (decisão fechada)

- **Backend:** PHP 8.2 puro (sem framework), organizado em `includes/`, `admin/`, `api/`, `user/`
- **Banco de dados:** MySQL (5.7+) via PDO — credenciais em `.env`/`.env.production` (lidas por `includes/env.php`), schema inicializado/migrado em `includes/db.php → initDB()`. Ver `Ai/docs/adr/0003-migracao-sqlite-para-mysql.md` (migrado de SQLite em 2026-07-21)
- **Frontend:** HTML/CSS/JS vanilla — sem bundler, sem npm. Fontes via Google Fonts CDN, ícones via Font Awesome CDN
- **Servidor:** Apache 2.4 com `.htaccess` (mod_rewrite habilitado)
- **Multi-tenancy:** shared database com `company_id` em todas as tabelas de domínio — ver `specs/saas-multitenancy/design.md`
- **Testes:** scripts PHP em `tmp/` para seed/validação manual — não há framework de testes automatizados

Qualquer mudança de stack exige ADR em `Ai/docs/adr/`.

---

## 3. Arquitetura e estrutura

### Inicialização do banco
Todo schema vive em `includes/db.php → initDB()` (MySQL, `ENGINE=InnoDB`). Colunas novas em tabelas existentes devem ser adicionadas via `ALTER TABLE`, guardadas por checagem em `information_schema.columns` (helper `columnExists()` em `includes/db.php`). **Nunca alterar schema direto no banco — sempre via `initDB()`.**

### Dois portais de autenticação separados
| Portal | Sessão | Tabela | Arquivos |
|---|---|---|---|
| Admin/gestor | `ADMIN_SESS` (nome da sessão via `session_name()`) | `admins` | `admin/`, `includes/auth.php` |
| Usuário/participante | `pageup_user` (chave em `$_SESSION`) | `users` | `user/`, `includes/user-auth.php` |

As duas sessões coexistem — `includes/auth.php` e `includes/user-auth.php` são independentes e não devem ser misturados.

### Helpers de query
Todos os arquivos usam `dbRow()`, `dbRows()`, `dbExec()`, `dbLastId()` de `includes/db.php`. Nunca instanciar PDO diretamente — usar `getDB()`.

### Roteamento
Não há roteador central. Cada arquivo PHP é uma rota. Redirecionamentos usam `header('Location: ...')` + `exit`. No admin, usar sempre `adminUrl('pagina.php')` e `redirect()` de `includes/auth.php` para garantir paths corretos independente de subdiretório.

### Layout de páginas
- **Admin:** `adminHead(string $title, string $activeNav)` + `adminFoot()` em `admin/layout.php` — inclui navbar, flash messages e estilos
- **Usuário:** `userPageHead(string $title)` + `userPageFoot()` em `user/_layout.php`
- **Páginas públicas** (index, lgpd, privacidade, cookies, contato): HTML inline completo com navbar/footer próprios seguindo a paleta `--prussian / --pacific`

### Variáveis CSS globais (definidas em `assets/style.css`)
`--prussian` (#023047), `--pacific` (#219EBC), `--yellow` (#FFB703), `--orange`, `--red`, `--green`, `--gray-*`

---

## 4. Convenções de código

- **PHP:** sem namespace, sem autoload — `require_once` explícito no topo de cada arquivo
- **Saída HTML:** sempre `htmlspecialchars()` ou a helper `e()` de `includes/auth.php` em qualquer dado vindo do banco/usuário
- **SQL:** sempre prepared statements via PDO — nunca concatenação de variáveis em SQL
- **Flash messages:** `flash(string $msg, string $type)` + `getFlash()` de `includes/auth.php` — somente no portal admin
- **Datas:** sempre `NOW()` em SQL (MySQL) para timestamps; `date('Y-m-d H:i:s')` em PHP. Funções de data usam sintaxe MySQL (`DATE_SUB(NOW(), INTERVAL n DAY)`, `DATE_FORMAT(col, '%Y-%m')`) — não usar sintaxe SQLite (`date('now','localtime')`, `strftime()`)
- **Coluna reservada:** `system_settings.key` é palavra reservada no MySQL — sempre referenciar como `` `key` `` (com crase) em SQL
- **Multi-tenancy (após implementação):** toda query de domínio deve filtrar por `company_id` — usar helper `tenantId()` que será criado em `includes/tenant.php`

---

## 5. Segurança

- Senhas sempre com `password_hash($pass, PASSWORD_DEFAULT)` / `password_verify()`
- Credenciais de banco (MySQL) nunca em código — apenas em `.env`/`.env.production` (ambos no `.gitignore`, nunca commitados)
- Pasta `includes/` bloqueada por rewrite rule no `.htaccess` raiz
- `DEFAULT_ADMIN_PASS` em `includes/config.php` é seed inicial — deve ser trocado via `admin/settings.php` após primeiro deploy
- Dados sensíveis (e-mail, CPF futuro) nunca em query string
- Toda ação de mutação de estado no admin/superadmin exige token CSRF — usar `csrfField()`/`requireCsrf()` de `includes/auth.php`

---

## 6. Fluxo de desenvolvimento de features

Seguir o processo SDD definido em `Ai/README.md`:

```
/specify → revisão humana → /plan → /tasks → /implement → /security-review → /verify
```

Specs ficam em `Ai/specs/<feature>/`. ADRs ficam em `Ai/docs/adr/`.

---

## 7. Como rodar localmente

**Pré-requisito:** XAMPP com Apache + PHP 8.2

Virtual host configurado em `C:\xampp\apache\conf\extra\httpd-vhosts.conf`:
```apache
<VirtualHost *:80>
    DocumentRoot "C:/Users/User/Documents/pagequiz_v1"
    ServerName pagequiz
    ServerAlias alphaclin-quiz_v1
</VirtualHost>
```

Hosts: `127.0.0.1 pagequiz` e `127.0.0.1 alphaclin-quiz_v1` em `C:\Windows\System32\drivers\etc\hosts`

Banco MySQL: credenciais em `.env` (dev) / `.env.production` (produção), carregadas por `includes/env.php` — ver `DB_HOST`/`DB_NAME`/`DB_USER`/`DB_PASS`. O schema é criado/migrado automaticamente na primeira requisição via `initDB()`. **Nota (débito técnico, ver ADR-0003):** local e produção apontam para o mesmo MySQL remoto da Locaweb até que a senha do root do MySQL local esteja disponível para isolar um banco de dev.

**Credenciais admin padrão:** usuário `admin` / senha `alphaclin2025` (seed em `includes/config.php → DEFAULT_ADMIN_PASS`)
**Admin PageUp:** `pageupsistemas@gmail.com` / `Admin@2026!`

---

## 8. Repositório

- **Git remote:** https://github.com/pgup-sistemas/quiz.git
- **Branch principal:** `master`
- **`.gitignore`** protege: `data/*.db`, `.env`, `.env.production`, `uploads/`, `tmp/sync_*.php`
