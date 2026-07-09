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
- **Banco de dados:** SQLite via PDO — arquivo único em `data/quiz.db`; inicializado/migrado em `includes/db.php → initDB()`
- **Frontend:** HTML/CSS/JS vanilla — sem bundler, sem npm. Fontes via Google Fonts CDN, ícones via Font Awesome CDN
- **Servidor:** Apache 2.4 com `.htaccess` (mod_rewrite habilitado)
- **Multi-tenancy:** shared database com `company_id` em todas as tabelas de domínio — ver `specs/saas-multitenancy/design.md`
- **Testes:** scripts PHP em `tmp/` para seed/validação manual — não há framework de testes automatizados

Qualquer mudança de stack exige ADR em `Ai/docs/adr/`.

---

## 3. Arquitetura e estrutura

### Inicialização do banco
Todo schema e migração vive em `includes/db.php → initDB()`. Colunas novas em tabelas existentes são adicionadas via migrations inline com `ALTER TABLE … ADD COLUMN` guardadas por `PRAGMA table_info()`. **Nunca alterar schema direto no banco — sempre via `initDB()`.**

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
- **Datas:** sempre `datetime('now','localtime')` em SQL SQLite para timestamps; `date('Y-m-d H:i:s')` em PHP
- **Multi-tenancy (após implementação):** toda query de domínio deve filtrar por `company_id` — usar helper `tenantId()` que será criado em `includes/tenant.php`

---

## 5. Segurança

- Senhas sempre com `password_hash($pass, PASSWORD_DEFAULT)` / `password_verify()`
- Arquivo `data/quiz.db` protegido por `.htaccess` (`Deny from all`)
- Pasta `includes/` bloqueada por rewrite rule no `.htaccess` raiz
- `DEFAULT_ADMIN_PASS` em `includes/config.php` é seed inicial — deve ser trocado via `admin/settings.php` após primeiro deploy
- Dados sensíveis (e-mail, CPF futuro) nunca em query string

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

O banco SQLite é criado automaticamente na primeira requisição em `data/quiz.db` via `initDB()`.

**Credenciais admin padrão:** usuário `admin` / senha `alphaclin2025` (seed em `includes/config.php → DEFAULT_ADMIN_PASS`)
**Admin PageUp:** `pageupsistemas@gmail.com` / `Admin@2026!`

---

## 8. Repositório

- **Git remote:** https://github.com/pgup-sistemas/quiz.git
- **Branch principal:** `master`
- **`.gitignore`** protege: `data/*.db`, `.env`, `uploads/`, `tmp/sync_*.php`
