# Design — Portal do Usuário (PageQuiz)
> **Criado:** 2026-07-11 — Documenta o portal participant/colaborador implementado nas sessões de 10–11 jul/2026.

---

## Visão geral

O portal do usuário (`user/`) é o ambiente onde os **colaboradores** (participantes) das empresas-tenant acessam, realizam quizzes e acompanham seu histórico. É completamente separado do portal admin — sessão, auth e layout próprios.

---

## Hierarquia de acesso

```
Super Admin (PageUp)
  └── Admin Tenant (empresa)
        └── User (colaborador) ← este portal
```

O User **não cria quizzes**. Ele consome o conteúdo publicado pelo Admin.

---

## Arquivos do portal

| Arquivo | Responsabilidade |
|---|---|
| `user/_layout.php` | `userPageHead()` / `userPageFoot()` — CSS compartilhado das páginas de auth |
| `user/login.php` | Login do colaborador |
| `user/register.php` | Auto-cadastro do colaborador (vinculado ao tenant atual) |
| `user/logout.php` | Destroy da sessão `pageup_user` |
| `user/dashboard.php` | Painel principal: stats, quizzes disponíveis, histórico, modal de perfil |
| `user/certificate.php` | Página dedicada de certificado individual com impressão + compartilhamento |
| `user/forgot-password.php` | Recuperação de senha via token |
| `user/reset-password.php` | Redefinição de senha via token |

---

## Autenticação

- **Sessão:** chave `pageup_user` em `$_SESSION` (não `session_name()`)
- **Funções principais** em `includes/user-auth.php`:
  - `userSessionStart()` — inicia a sessão com o nome correto
  - `isUserLoggedIn(): bool`
  - `currentUser(): ?array` — retorna array da sessão com `id, name, email, sector, company_id`
  - `requireUserLogin(): void` — redirect para `login.php` se não autenticado
  - `userRegister(name, email, pass, sector): true|string`
  - `userLogin(email, pass): bool`
  - `userUpdateProfile(id, name, sector): void`
  - `userChangePassword(id, currentPass, newPass): bool`
  - `generateResetToken(email): string|false`
  - `resetPassword(token, newPass): bool`

### Vinculação ao tenant

No `userRegister()` e `userLogin()`, o `company_id` é obtido de `_userCompanyId()` que lê `$_SESSION['tenant_company_id']` (populado por `resolveTenant()`). Isso garante que:
- Um colaborador só pode se cadastrar/logar no tenant do subdomínio (ou `?c=slug`) em que está acessando
- Mesmo e-mail em tenants diferentes = contas totalmente independentes (`UNIQUE(company_id, email)`)

---

## Dashboard (`user/dashboard.php`)

### Estrutura da página

```
[Navbar sticky]  Logo empresa | Início | [Avatar Iniciais] | Sair
[Header]         Olá, Nome!  email · setor
[Stats row]      Quizzes disponíveis | Realizados | Média geral
[Card: Quizzes]  Grid de cards de quizzes
[Card: Histórico] Lista de participações com badge pass/fail
[Modal Perfil]   Tabs: Meu Perfil / Alterar Senha (abre via avatar na navbar)
```

### Modal de perfil (navbar avatar)
- Botão `.nav-avatar` com as iniciais do usuário na navbar
- Abre um modal `.modal-box` com 2 tabs:
  - **Meu Perfil**: form `update_profile` (nome + setor)
  - **Alterar Senha**: form `change_pass` (senha atual + nova + confirmação)
- Auto-abre na tab correta após POST com resultado (success/error)
- Fecha com: botão ×, clique fora, tecla `Escape`

### Query de quizzes disponíveis

Filtra por `visibility` e `quiz_assignments` (Fase 2):

```sql
SELECT DISTINCT q.*
FROM quizzes q
WHERE q.active = 1 AND q.company_id = :cid
  AND (q.expires_at IS NULL OR q.expires_at >= date('now','localtime'))
  AND (
      q.visibility = 'all'
      OR (q.visibility = 'sector' AND EXISTS (
          SELECT 1 FROM quiz_assignments qa WHERE qa.quiz_id = q.id AND qa.sector_id = :sectorId
      ))
  )
ORDER BY q.created_at DESC
```

Cada card de quiz exibe: setor (badge), título, descrição (2 linhas), contagem de questões, tempo/questão, badge "Certificado" se `has_certificate=1`, badge "Feito" se já realizado, botão "Iniciar" (azul) ou "Refazer" (cinza).

### Query de histórico

```sql
SELECT p.*, q.title AS quiz_title, q.pass_percentage AS pass_pct, q.has_certificate
FROM participants p
JOIN quizzes q ON q.id = p.quiz_id
WHERE (p.email = :email OR (p.email = '' AND p.name = :name))
  AND q.company_id = :cid          -- filtra pelo tenant (quando tenant ativo)
  AND p.completed_at IS NOT NULL
ORDER BY p.completed_at DESC
LIMIT 30
```

Link de certificado aponta para `certificate.php?id=PARTICIPANT_ID` (não para `verify.php`).

---

## Certificado dedicado (`user/certificate.php`)

### Acesso
`GET /user/certificate.php?id=PARTICIPANT_ID`

### Guards (em cascata)
1. Usuário deve estar logado (`requireUserLogin()`)
2. Participante deve existir e estar completo (`completed_at IS NOT NULL AND passed=1 AND has_certificate=1`)
3. `company_id` do quiz deve bater com o tenant do usuário logado
4. E-mail ou nome do participante deve corresponder ao do usuário logado

### Conteúdo
- Mesmo HTML do certificado inline de `quiz.php` (reutiliza `.cert`, `.cert-name`, `.cert-score` etc. de `assets/style.css`)
- Navbar com "← Meu Painel"
- Botões: Imprimir/PDF, WhatsApp (compartilhamento com link de verificação), Voltar ao Painel
- QR Code via `api.qrserver.com` apontando para `verify.php?code=VERIFY_CODE`
- `@media print`: oculta navbar e botões, imprime só o certificado

---

## Pré-preenchimento do formulário de quiz (`quiz.php`)

Quando um colaborador está logado e acessa um quiz, o formulário de identificação (`#screen-login`) é pré-preenchido automaticamente:

```php
// PHP — injeta dados do usuário logado como variável JS
$quizLoggedUser = currentUser(); // null se não logado
```

```js
const LOGGED_USER = <?= json_encode(['name'=>..., 'email'=>..., 'sector'=>...]) | 'null' ?>;

// DOMContentLoaded — após carregar os dados do quiz:
if (LOGGED_USER) {
    document.getElementById('inp-name').value  = LOGGED_USER.name;
    document.getElementById('inp-email').value = LOGGED_USER.email || '';
    // pre-seleciona o setor no <select>
    for (let i = 0; i < sel.options.length; i++) {
        if (sel.options[i].value === LOGGED_USER.sector) { sel.selectedIndex = i; break; }
    }
}
```

O usuário pode alterar os campos antes de iniciar (não são travados).

---

## SEO e robots

Todas as páginas do portal `user/` têm:
```html
<meta name="robots" content="noindex,nofollow"/>
```

São páginas autenticadas — não devem ser indexadas.

---

## Fluxo completo de onboarding do colaborador

```
Admin compartilha link de acesso (admin/index.php → botão "Copiar")
    │
    ▼
Colaborador acessa: http://pagequiz/?c=alphaclin  (dev)
                 ou  https://alphaclin.pagequiz.com.br  (prod)
    │
    ├── Não tem conta → /user/register.php?c=alphaclin
    │       preenche nome, e-mail, setor, senha
    │       → vinculado a Alphaclin (company_id=X)
    │       → redirect dashboard.php
    │
    └── Já tem conta → /user/login.php?c=alphaclin
            → dashboard.php

Dashboard:
    ├── Vê quizzes atribuídos ao seu setor (ou todos, se visibility='all')
    ├── Clica "Iniciar Quiz" → quiz.php?id=N (form pré-preenchido)
    ├── Realiza quiz → resultado → certificado (se aprovado + has_certificate)
    └── Histórico + link "Certificado" → user/certificate.php?id=P
```

---

## Roles e permissões

| Ação | User | Admin | Super Admin |
|---|---|---|---|
| Fazer quizzes | ✅ | — | — |
| Ver histórico próprio | ✅ | — | — |
| Emitir/imprimir certificado | ✅ | — | — |
| Criar quizzes | ✗ | ✅ | — |
| Ver resultados de todos | ✗ | ✅ | ✅ |
| Criar empresas | ✗ | ✗ | ✅ |

---

## Arquivos modificados (implementação)

| Arquivo | Mudança |
|---|---|
| `user/dashboard.php` | Reescrita completa: stats, grid de quizzes, histórico, modal de perfil na navbar, filtro por `quiz_assignments` |
| `user/certificate.php` | **Novo** — página dedicada de certificado com guards de segurança |
| `user/login.php` | Adicionado `require_once tenant.php` + `resolveTenant()` para persistir tenant na sessão |
| `quiz.php` | Pré-preenchimento do formulário com dados do usuário logado (`LOGGED_USER` JS var) |
