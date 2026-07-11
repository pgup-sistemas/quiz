# Design — SaaS Multi-tenancy (PageQuiz)
> **Revisão:** 2026-07-11 — Fase 2: atribuição de quizzes por setor, acesso por `?c=slug`, `clearTenantSession()`.
> **Revisão anterior:** 2026-07-09 — Modelo Free/Pro, auto-cadastro, limites configuráveis.

## Visão geral técnica

A feature introduz: (1) auto-cadastro público de empresa com seleção de plano; (2) dois planos — **Free** (limite de quizzes configurável pelo super-admin via `system_settings`) e **Pro** (ilimitado); (3) resolução de tenant por subdomínio com cache de sessão; (4) portal super-admin separado para gestão de empresas e configuração global. O schema existente é estendido com `company_id` em todas as tabelas de domínio. A cadeia de autenticação é ampliada: super-admin → admin de empresa → usuário participante, cada uma com sessão PHP independente.

---

## ADR associada

**ADR-0001:** Shared SQLite com `company_id` — ver `Ai/docs/adr/0001-tenant-isolation-strategy.md`.

---

## Modelo de dados

### Tabela nova: `companies`

| Coluna | Tipo | Notas |
|---|---|---|
| `id` | INTEGER PK | — |
| `name` | TEXT NOT NULL | Nome de exibição |
| `slug` | TEXT UNIQUE NOT NULL | Subdomínio (ex.: `clinica-sao-joao`) |
| `cnpj` | TEXT | Opcional; UNIQUE se preenchido |
| `email` | TEXT NOT NULL | E-mail do responsável/admin |
| `plan` | TEXT DEFAULT 'free' | `free` \| `pro` |
| `status` | TEXT DEFAULT 'active' | `active` \| `suspended` \| `pending_payment` |
| `primary_color` | TEXT DEFAULT '#219EBC' | Só exibida no Pro |
| `logo_path` | TEXT | `uploads/companies/{id}/logo.*` — só usado no Pro |
| `created_at` | TEXT | — |
| `updated_at` | TEXT | — |

> **Sem `trial`** — modelo simplificado: Free é permanente, Pro é ativado manualmente pelo super-admin.
> **Sem `max_quizzes` na tabela** — limite vem de `system_settings.free_quiz_limit` para plano Free; Pro = ilimitado.

**Sustenta:** REQ-1, REQ-2, REQ-3, REQ-4, REQ-5, REQ-7, REQ-12, REQ-13

---

### Tabela nova: `system_settings`

| Coluna | Tipo | Notas |
|---|---|---|
| `key` | TEXT PK | Ex.: `free_quiz_limit` |
| `value` | TEXT NOT NULL | Valor configurável |
| `description` | TEXT | Label para exibição no painel |
| `updated_at` | TEXT | — |

**Seeds:**
```
('free_quiz_limit', '12', 'Limite de quizzes no plano Free')
('app_name',        'PageQuiz', 'Nome da plataforma')
('support_email',   'contato@pageup.net.br', 'E-mail de suporte exibido no upgrade')
```

**Sustenta:** REQ-8, REQ-14, REQ-NFR-6

---

### Tabela nova: `super_admins`

| Coluna | Tipo | Notas |
|---|---|---|
| `id` | INTEGER PK | — |
| `username` | TEXT UNIQUE NOT NULL | — |
| `password_hash` | TEXT NOT NULL | — |
| `name` | TEXT | — |
| `created_at` | TEXT | — |

**Seed:** `pageupsistemas@gmail.com` / `Admin@2026!`
**Sustenta:** REQ-16, REQ-18

---

### Tabela nova: `audit_log`

| Coluna | Tipo | Notas |
|---|---|---|
| `id` | INTEGER PK | — |
| `actor_type` | TEXT | `super_admin` \| `admin` |
| `actor_id` | INTEGER | — |
| `action` | TEXT | `login` \| `impersonate` \| `suspend` \| `change_plan` \| `downgrade` \| `approve_pro` |
| `target_company_id` | INTEGER | — |
| `ip` | TEXT | — |
| `detail` | TEXT | JSON com contexto adicional |
| `created_at` | TEXT | — |

**Sustenta:** REQ-NFR-3

---

### Alterações em tabelas existentes

`company_id INTEGER NOT NULL DEFAULT 1` adicionado via `ALTER TABLE … ADD COLUMN` guardado por `PRAGMA table_info`:

| Tabela | Índice adicionado |
|---|---|
| `quizzes` | `(company_id, active)` |
| `questions` | `(company_id, quiz_id)` |
| `participants` | `(company_id, quiz_id)` |
| `answers` | `(company_id, participant_id)` |
| `sectors` | UNIQUE `(company_id, name)` |
| `admins` | `(company_id)` + coluna `first_login INTEGER DEFAULT 0` |
| `contact_messages` | `(company_id)` |

**Tabela `users`:** recriar com UNIQUE `(company_id, email)` substituindo o UNIQUE `email` global — ver seção de migração.

**Sustenta:** REQ-20, REQ-NFR-1, REQ-NFR-5

---

## Função `planLimits()` em `includes/tenant.php`

```php
function planLimits(string $plan): array {
    $freeLimit = (int)(dbRow("SELECT value FROM system_settings WHERE key='free_quiz_limit'")['value'] ?? 12);
    return match($plan) {
        'free' => ['quizzes' => $freeLimit, 'unlimited' => false, 'custom_brand' => false],
        'pro'  => ['quizzes' => -1,          'unlimited' => true,  'custom_brand' => true],
        default => ['quizzes' => $freeLimit, 'unlimited' => false, 'custom_brand' => false],
    };
}
```

`-1` = ilimitado. `tenantGuard('quizzes', $count)` verifica se `$count >= limits['quizzes']` e `!unlimited` — lança exceção ou retorna `false` para exibição de aviso.

---

## Página de cadastro público `/cadastro.php`

```
[Nome da empresa *]
[E-mail do responsável *]
[Senha *]        [Confirmar senha *]
[CNPJ/CPF]       (opcional)

Escolha seu plano:
  ◉ Free   — até 12 quizzes, usuários ilimitados, certificado padrão
  ○ Pro    — quizzes ilimitados, certificado personalizado, logo e cor da empresa
             → Solicite o Pro: entraremos em contato para ativação

[Criar minha conta →]

Já tem conta? Entrar
```

Ao submeter com plano Pro: cria empresa com `plan='free', status='pending_payment'` + aviso para aguardar ativação. O super-admin vê a empresa com badge `⏳ Pro Solicitado` e pode aprovar com 1 clique.

---

## Fluxo de resolução de tenant

```
Requisição → includes/tenant.php → resolveTenant()
  1. Cache: $_SESSION['tenant_company_id'] → retorna imediatamente (REQ-NFR-2)
  2. Extrai slug do HTTP_HOST: strtok(HOST, '.') → slug
  3. SELECT * FROM companies WHERE slug = ? AND status = 'active'
     → não encontrado / status = 'suspended' → página 404 / suspensão (REQ-29, REQ-30)
  4. Cache na sessão → retorna company[]
```

Para páginas públicas sem subdomínio de tenant (landing da PageUp, `/cadastro`, `/superadmin`): `resolveTenant()` retorna `null` — não bloqueia.

---

## Hierarquia de acesso e sessões

```
Super Admin (session: SUPER_ADMIN_SESS)
  └── superadmin/ — vê e gerencia TODAS as empresas

Admin de Empresa (session: ADMIN_SESS — com company_id vinculado)
  └── admin/ — vê apenas sua empresa

Usuário/Participante (session: pageup_user — com company_id vinculado)
  └── user/ — vê apenas seu histórico na empresa
```

Três sessões PHP completamente independentes — nenhuma tem acesso ao que a outra armazena.

---

## Fluxo de upgrade Free → Pro

```
Admin Free clica "Fazer Upgrade" no painel
    ↓
Página upgrade.php: exibe benefícios do Pro + e-mail/WhatsApp de contato
    ↓
Admin envia solicitação (form simples → salva em companies: status = 'pending_payment')
    ↓
Super-admin vê badge "⏳ Pro Solicitado" em superadmin/companies.php
    ↓
Super-admin clica "Ativar Pro" → UPDATE companies SET plan='pro', status='active'
    ↓
INSERT audit_log (action='approve_pro')
    ↓
Admin da empresa vê plano atualizado no próximo login/reload
```

---

## Fluxo de rebaixamento Pro → Free (REQ-13)

```
Super-admin altera plano para Free
    ↓
Sistema calcula quizzes ativos da empresa
    ↓
SE count(quizzes ativos) > free_quiz_limit:
    Inativa automaticamente os quizzes mais antigos além do limite
    (ORDER BY created_at ASC → inativa os excedentes)
    ↓
    INSERT audit_log (action='downgrade', detail: JSON com IDs inativados)
    ↓
    Banner no painel da empresa: "Seu plano foi alterado para Free.
    X quizzes foram desativados. Reative manualmente até o limite de N."
```

---

## Branding por plano

| Recurso | Free | Pro |
|---|---|---|
| Logo próprio | ✗ (logo PageQuiz) | ✓ |
| Cor primária | ✗ (cor padrão) | ✓ |
| Certificado personalizado | ✗ (template padrão) | ✓ |
| Subdomínio próprio | ✓ (slug.pagequiz.com.br) | ✓ |

No plano Free: se admin tentar acessar configurações de logo/cor → modal de upgrade (REQ-26).

---

## Arquivos novos

| Arquivo | Responsabilidade |
|---|---|
| `cadastro.php` | Auto-cadastro público de empresa + seleção de plano |
| `upgrade.php` | Página de upgrade Free → Pro (form de contato/solicitação) |
| `includes/tenant.php` | `resolveTenant()`, `tenantId()`, `tenantCompany()`, `tenantGuard()`, `planLimits()` |
| `includes/superadmin-auth.php` | Sessão e auth do super-admin |
| `superadmin/index.php` | Dashboard: lista empresas, stats, filtros |
| `superadmin/companies.php` | Lista + ações rápidas (suspender, ativar Pro, impersonar) |
| `superadmin/company-edit.php` | Criar/editar empresa + admin inicial |
| `superadmin/company-approve-pro.php` | Endpoint de ativação Pro (POST) |
| `superadmin/impersonate.php` | Impersonation com audit_log |
| `superadmin/audit.php` | Visualizar audit_log |
| `superadmin/settings.php` | Configurar `system_settings` (free_quiz_limit, etc.) |
| `superadmin/login.php` | Login super-admin |
| `superadmin/logout.php` | — |
| `superadmin/layout.php` | Head/foot do portal super-admin |
| `admin/onboarding.php` | Wizard: logo (Pro) + cor (Pro) ou só nome de exibição (Free) |
| `admin/upgrade.php` | Página de upgrade dentro do painel admin |

## Arquivos modificados

| Arquivo | Mudança |
|---|---|
| `includes/db.php → initDB()` | Novas tabelas; migrations `company_id`; recriar `users`; seeds |
| `includes/auth.php` | `requireLogin()` valida `company_id` da sessão vs. tenant; `adminLogin()` filtra por `company_id` |
| `includes/user-auth.php` | `userLogin()` e `userRegister()` incluem `company_id` |
| `includes/config.php` | `SUPER_ADMIN_SESS` |
| `admin/layout.php` | Nome da empresa no topbar; banner de uso (% do limite); badge do plano; banner Pro Solicitado |
| `admin/*.php` (todos) | `require_once '../includes/tenant.php'`; filtros `company_id` em todas as queries |
| `user/*.php` (todos) | `tenant.php`; filtros `company_id` |
| `api/*.php` (todos) | `tenant.php`; filtros `company_id` |
| `index.php` | Resolver tenant; exibir logo/cor da empresa (se Pro) |
| `quiz.php`, `verify.php` | Contexto de tenant; verificar `company_id` no certificado |
| `lgpd.php`, `privacidade.php`, `cookies.php`, `contato.php` | Tenant branding; `company_id` em mensagens de contato |

---

---

## Fase 2 — Atribuição de quizzes e acesso por link (2026-07-11)

### Motivação

Com o tenant resolvido apenas por subdomínio, testes locais e links de convite eram inviáveis. Além disso, todos os quizzes ativos ficavam visíveis a todos os colaboradores da empresa independentemente do setor — sem controle granular de acesso.

### Novas tabelas/colunas

#### `quizzes.visibility` (nova coluna, migration inline)
```sql
ALTER TABLE quizzes ADD COLUMN visibility TEXT NOT NULL DEFAULT 'all'
```
| Valor | Comportamento |
|---|---|
| `'all'` | Quiz visível para todos os colaboradores da empresa |
| `'sector'` | Visível apenas para setores listados em `quiz_assignments` |

#### `quiz_assignments` (nova tabela)
```sql
CREATE TABLE IF NOT EXISTS quiz_assignments (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    quiz_id    INTEGER NOT NULL REFERENCES quizzes(id) ON DELETE CASCADE,
    sector_id  INTEGER NOT NULL REFERENCES sectors(id) ON DELETE CASCADE,
    UNIQUE(quiz_id, sector_id)
);
```
Admin seleciona setores ao criar/editar um quiz. Se `visibility='all'`, a tabela fica vazia para aquele quiz (não precisam de linhas).

#### `sectors` — migration UNIQUE `(company_id, name)`

A tabela `sectors` foi criada originalmente com `UNIQUE(name)` global, impossibilitando que duas empresas tivessem setor com o mesmo nome (ex: "RH" em empresa A e empresa B). A migration recria a tabela com `UNIQUE(company_id, name)`:

```sql
CREATE TABLE sectors_v2 (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    company_id INTEGER NOT NULL DEFAULT 1,
    name       TEXT    NOT NULL,
    created_at TEXT    DEFAULT (datetime('now','localtime')),
    UNIQUE(company_id, name)
);
-- copia dados → DROP sectors → RENAME sectors_v2 → sectors
```

### `resolveTenant()` — 3 fontes em cascata

```
1. $_SESSION['tenant_company'] — cache de sessão (retorna imediato)
2. HTTP_HOST → extrai slug do subdomínio (produção: alphaclin.pagequiz.com.br)
3. $_GET['c'] → slug passado como parâmetro de URL (dev local / link de convite)
4. $_SESSION['_tenant_slug'] → slug persistido na sessão pelo acesso anterior com ?c=
```

Fonte 3 e 4 permitem que o link de convite `http://pagequiz/?c=alphaclin` funcione sem DNS wildcard. O slug é sanitizado com `preg_replace('/[^a-z0-9\-]/', '', ...)` antes de consultar o banco. Slug inválido via `?c=` retorna `null` silenciosamente (não dá 404).

Nova função: **`clearTenantSession(): void`** — limpa `tenant_company`, `tenant_company_id` e `_tenant_slug` da sessão para uso no logout do user ou no encerramento de impersonation.

### Link de acesso para colaboradores

O dashboard admin (`admin/index.php`) exibe, no topo, o link gerado automaticamente:

| Ambiente | URL de acesso | URL de cadastro |
|---|---|---|
| Produção (subdomínio) | `https://alphaclin.pagequiz.com.br/` | `.../user/register.php` |
| Dev local | `http://pagequiz/?c=alphaclin` | `.../user/register.php?c=alphaclin` |

Inclui QR Code gerado via `api.qrserver.com` e botões "Copiar" para cada URL.

### Filtro de quizzes no dashboard do usuário

Query atualizada em `user/dashboard.php`:
```sql
SELECT DISTINCT q.*
FROM quizzes q
WHERE q.active = 1 AND q.company_id = ?
  AND (q.expires_at IS NULL OR q.expires_at >= date('now','localtime'))
  AND (
      q.visibility = 'all'
      OR (q.visibility = 'sector' AND EXISTS (
          SELECT 1 FROM quiz_assignments qa
          WHERE qa.quiz_id = q.id AND qa.sector_id = ?
      ))
  )
ORDER BY q.created_at DESC
```
O `sector_id` do usuário é resolvido via `SELECT id FROM sectors WHERE name = user.sector AND company_id = ?`.

### UI no `admin/quiz-edit.php`

Seção "Visibilidade" adicionada ao formulário:
- Radio `Todos os colaboradores` (default) / `Setores específicos`
- Checkboxes com todos os setores da empresa (visíveis ao escolher "Setores específicos")
- Ao salvar: `DELETE FROM quiz_assignments WHERE quiz_id=?` + `INSERT` para cada setor selecionado

---

## Riscos e trade-offs

| Risco | Mitigação |
|---|---|
| Query sem `company_id` vaza dados | `tenantGuard()` obrigatório; grep em security review |
| Recriar `users` com nova UNIQUE constraint | Migration com backup + teste antes de produção |
| Super-admin aprovar Pro sem confirmar pagamento | Responsabilidade do processo manual; audit_log registra aprovação |
| `free_quiz_limit` alterado globalmente quebrar empresas existentes | REQ-8 especifica: quizzes existentes não são excluídos, apenas bloqueio de novos |
| Slug duplicado no auto-cadastro | Auto-sufixo numérico (`clinica-sao-joao-2`) |

---

## Rastreabilidade

| REQ | Componente responsável |
|---|---|
| REQ-1, REQ-2, REQ-3, REQ-4, REQ-5 | `cadastro.php` + validações |
| REQ-6 | `cadastro.php` → `plan='free'` + `status='pending_payment'` para Pro solicitado |
| REQ-7, REQ-8, REQ-14 | `system_settings.free_quiz_limit` + `planLimits()` + `superadmin/settings.php` |
| REQ-9, REQ-10 | `tenantGuard()` + banner em `admin/layout.php` |
| REQ-11 | `cadastro.php` tabela comparativa + `admin/upgrade.php` |
| REQ-12, REQ-13 | `superadmin/company-approve-pro.php` + lógica de rebaixamento |
| REQ-15 | `superadmin/company-edit.php` |
| REQ-16 | `superadmin/` com `SUPER_ADMIN_SESS` separado |
| REQ-17 | `superadmin/index.php` |
| REQ-18 | `superadmin/impersonate.php` + `audit_log` |
| REQ-19 | `status='suspended'` + `resolveTenant()` bloqueando |
| REQ-20, REQ-21, REQ-22, REQ-23 | `company_id` em todas as tabelas + `tenantId()` + `tenantGuard()` |
| REQ-24, REQ-25, REQ-26 | `admin/onboarding.php` + verificação de plano antes de salvar logo/cor |
| REQ-27, REQ-28, REQ-29, REQ-30 | `resolveTenant()` + CSS vars injetadas |
| REQ-NFR-1 | `tenantId()` lança exceção se sem tenant |
| REQ-NFR-2 | Cache `$_SESSION['tenant_company_id']` |
| REQ-NFR-3 | `audit_log` + INSERTs nos pontos críticos |
| REQ-NFR-4 | `company_id` vinculado à sessão |
| REQ-NFR-5 | Seed `company_id=1` para Alphaclin (plano Pro) |
| REQ-NFR-6 | `system_settings` table + `superadmin/settings.php` |
