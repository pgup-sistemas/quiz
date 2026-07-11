# Tasks — SaaS Multi-tenancy (PageQuiz)
> **Revisão:** 2026-07-11 — Fase 2 (atribuição + acesso por link) implementada. Status das tasks atualizado.
> **Revisão anterior:** 2026-07-09 — Modelo Free/Pro, auto-cadastro, limites configuráveis via `system_settings`.
> Cada task inclui seu próprio teste. Dependências sequenciais: Fase 1 → Fase 2 → Fases 3/4/5 (paralelas) → Fase 6 → Fase 7 → Fase 8 → Fase 9.

### Legenda de status
- ✅ **Concluído** — implementado e validado
- 🚧 **Parcial** — implementado mas sem cobertura de teste automatizado
- ⬜ **Pendente** — não iniciado

---

## Fase 1 — Schema e migrations ✅

### T01 — Criar `companies` e `system_settings` no `initDB()` ✅
**Arquivo:** `includes/db.php`
- `CREATE TABLE IF NOT EXISTS companies (id, name, slug UNIQUE, cnpj, email, plan DEFAULT 'free', status DEFAULT 'active', primary_color DEFAULT '#219EBC', logo_path, created_at, updated_at)`.
- `CREATE TABLE IF NOT EXISTS system_settings (key TEXT PK, value, description, updated_at)`.
- Seeds: `('free_quiz_limit','12',…)`, `('app_name','PageQuiz',…)`, `('support_email','contato@pageup.net.br',…)`.
- Seed empresa `id=1`: Alphaclin, `plan='pro'`, `status='active'` com `INSERT OR IGNORE`.

**Teste:** `php -r "require 'includes/db.php'; initDB(); var_dump(dbRow('SELECT * FROM companies WHERE id=1'));"` → Alphaclin. `dbRow("SELECT value FROM system_settings WHERE key='free_quiz_limit'")['value']` → `'12'`.

---

### T02 — Criar `super_admins` e `audit_log` no `initDB()` ✅
**Arquivo:** `includes/db.php`
- `CREATE TABLE IF NOT EXISTS super_admins (id PK, username UNIQUE, password_hash, name, created_at)`.
- `CREATE TABLE IF NOT EXISTS audit_log (id PK, actor_type, actor_id, action, target_company_id, ip, detail, created_at)`.
- Seed super-admin `pageupsistemas@gmail.com` / `Admin@2026!` com `INSERT OR IGNORE`.

**Teste:** `dbRow("SELECT id FROM super_admins WHERE username='pageupsistemas@gmail.com'")` → registro encontrado.

---

### T03 — Adicionar `company_id` nas tabelas existentes via migration ✅
**Arquivo:** `includes/db.php → initDB()`
- Para cada tabela de domínio (`quizzes`, `questions`, `participants`, `answers`, `sectors`, `admins`, `contact_messages`): `PRAGMA table_info` → se sem `company_id` → `ALTER TABLE ADD COLUMN company_id INTEGER NOT NULL DEFAULT 1`.
- Para `admins`: adicionar `first_login INTEGER DEFAULT 0` se não existir.
- Criar índices: `idx_quizzes_company(company_id, active)`, `idx_participants_company(company_id, quiz_id)`, etc.

**Teste:** `PRAGMA table_info(quizzes)` → coluna `company_id` aparece com valor `1` em registros existentes.

---

### T04 — Recriar `users` com UNIQUE `(company_id, email)` ✅
**Arquivo:** `includes/db.php → initDB()`
- `PRAGMA index_list(users)` → se não tem UNIQUE em `(company_id, email)`: criar `users_new` com constraint correta → copiar dados → `DROP TABLE users` → renomear.
- Garantir `company_id DEFAULT 1` em registros migrados.

**Teste:** dois usuários com mesmo e-mail mas `company_id` diferente → INSERT OK. Mesmo `company_id` + mesmo e-mail → UNIQUE error.

---

## Fase 2 — Resolução de tenant ✅

### T05 — Criar `includes/tenant.php` ✅
> **Implementado:** `resolveTenant()` com 3 fontes em cascata (subdomínio → `?c=slug` → `$_SESSION['_tenant_slug']`). Adicionado `clearTenantSession()`. Ver `Ai/specs/saas-multitenancy/design.md` seção "Fase 2".
- `resolveTenant(): ?array` — extrai slug de `HTTP_HOST`, busca banco, cacheia em sessão. Páginas sem subdomínio válido → `null`. Empresa `suspended` → redirect. Não encontrada → 404.
- `tenantId(): int` — `$_SESSION['tenant_company_id']` ou lança `RuntimeException`.
- `tenantCompany(): array` — `$_SESSION['tenant_company']`.
- `planLimits(string $plan): array` — retorna `['quizzes'=>N, 'unlimited'=>bool, 'custom_brand'=>bool]`.
- `tenantCanCreateQuiz(): bool` — compara count de quizzes ativos com limite do plano.
- `slugUnico(string $name): string` — normaliza + garante unicidade com sufixo numérico.

**Teste:** mock `$_SERVER['HTTP_HOST']='alphaclin.pagequiz'` → resolve `id=1`. `tenantId()` sem sessão → exceção. Empresa `suspended` → redirect.

---

### T06 — Criar `includes/superadmin-auth.php` ✅
- `const SUPER_ADMIN_SESS = 'SUPER_ADMIN_SESS'`.
- `superAdminLogin(username, password): bool` — verifica hash + inicia sessão separada.
- `requireSuperAdmin(): void` — redirect para login se não autenticado.
- `superAdminId(): int`, `superAdminName(): string`.
- `logAudit(action, companyId, detail=''): void` — INSERT em `audit_log`.

**Teste:** login correto → `true`. Errado → `false`. `requireSuperAdmin()` sem sessão → redirect.

---

### T07 — Atualizar `includes/config.php` ✅
- Adicionar `define('SUPER_ADMIN_SESS', 'SUPER_ADMIN_SESS')` via `defined()` guard.

**Teste:** constante disponível após `require_once`.

---

---

## Fase 2B — Atribuição de quizzes por setor ✅
> Implementada em 2026-07-11. Tasks fora do escopo original do planejamento — adicionadas como registro.

### T05B — Migration `sectors` UNIQUE `(company_id, name)` ✅
**Arquivo:** `includes/db.php`
- Recria `sectors` com `UNIQUE(company_id, name)` substituindo `UNIQUE(name)` global.
- Garante que duas empresas possam ter setor "RH" sem conflito.

### T05C — Nova tabela `quiz_assignments` ✅
**Arquivo:** `includes/db.php`
- `quiz_assignments(quiz_id, sector_id, UNIQUE(quiz_id, sector_id))`.
- `quizzes.visibility TEXT DEFAULT 'all'` adicionado via migration inline.

### T05D — UI de visibilidade no `admin/quiz-edit.php` ✅
**Arquivo:** `admin/quiz-edit.php`
- Radio "Todos" / "Setores específicos" + checkboxes dos setores da empresa.
- Ao salvar: trunca `quiz_assignments` e reinsere os selecionados.

### T05E — Filtro por atribuição no dashboard do user ✅
**Arquivo:** `user/dashboard.php`
- Query usa `q.visibility = 'all' OR EXISTS(quiz_assignments)`.
- Resolve `sector_id` pelo nome do setor do usuário na empresa.

### T05F — Link de acesso no dashboard admin ✅
**Arquivo:** `admin/index.php`
- Bloco com URL de acesso + URL de cadastro + QR Code automático + botões "Copiar".
- Detecta ambiente (subdomínio vs. local) e gera URL correta.

---

## Fase 3 — Portal super-admin 🚧

### T08 — `superadmin/login.php` ✅
- Form username+password. POST → `superAdminLogin()` → redirect ou erro. GET → redirect se já autenticado.

**Teste:** credenciais corretas → redirect `index.php`. Erradas → "Credenciais inválidas".

---

### T09 — `superadmin/layout.php` ✅
- `superadminHead(title, active)` e `superadminFoot()`. Topbar com nav (Dashboard, Empresas, Config, Auditoria, Sair). Paleta padrão PageQuiz.

---

### T10 — `superadmin/index.php` (dashboard) ✅
- Cards: total empresas, Free vs Pro, Pro Solicitados (`pending_payment`), total quizzes, total usuários.
- Tabela: 10 empresas mais recentes. Filtro por plano/status.

**Teste:** cards com dados reais. Badge `⏳ Pro Solicitado` visível.

---

### T11 — `superadmin/companies.php` ✅
- Tabela paginada: nome, slug, plano (badge), status (badge), quizzes usados, usuários, data, ações (Editar, Suspender/Ativar, Ativar Pro, Impersonar).
- Busca por nome/slug. Badge `⏳ Pro Solicitado` para `pending_payment`.

**Teste:** empresa `pending_payment` mostra badge. Filtro "Pro Solicitado" funciona.

---

### T12 — `superadmin/company-edit.php` ✅
- Form criar/editar: nome, slug (auto + editável), e-mail, CNPJ, plano, status.
- Criar: gera slug único + empresa + admin inicial (senha exibida na tela na v1).
- Editar: slug imutável pós-criação.

**Teste:** criar → aparece em `companies.php`. Slug duplicado → erro + slug sugerido.

---

### T13 — `superadmin/company-approve-pro.php` (POST) ✅
- Valida `status='pending_payment'` → `UPDATE plan='pro', status='active'` → `logAudit('approve_pro')` → redirect com flash.

**Teste:** empresa `pending_payment` → após POST → `plan=pro, status=active`. Audit log registra ação.

---

### T14 — `superadmin/impersonate.php` ✅
- GET `?company_id=X`: salva `impersonating_*` na sessão → `logAudit('impersonate')` → inicia sessão admin → redirect `admin/index.php`.
- GET `?stop=1`: restaura sessão super-admin.
- Banner de impersonation no `admin/layout.php`.

**Teste:** super-admin impersona → banner visível no admin → "Encerrar" volta para superadmin.

---

### T15 — `superadmin/settings.php` ✅
- Form com todos os `system_settings` (free_quiz_limit, app_name, support_email). POST: UPDATE + exibir `updated_at`.

**Teste:** alterar `free_quiz_limit` para `5` → salvar → recarregar → mantido. Empresa Free com 7 quizzes bloqueada de criar novos.

---

### T16 — `superadmin/audit.php` ✅
- Tabela paginada do `audit_log`: data, ator, ação (badge), empresa, IP, detalhe JSON. Filtro por ação/empresa.

**Teste:** ativar Pro → registro aparece com todos os campos.

---

### T17 — `superadmin/logout.php` ✅
- Destroy sessão `SUPER_ADMIN_SESS` → redirect login.

---

## Fase 4 — Cadastro público de empresa ⬜

### T18 — `cadastro.php` ⬜
- Form: nome empresa, e-mail, senha, confirmar senha, CNPJ (opcional), escolha de plano (visual, tabela comparativa).
- Validações server-side: campos obrigatórios, e-mail único em admins, CNPJ único, senhas iguais.
- Free: cria empresa `plan='free', status='active'` + admin + login automático → `admin/onboarding.php`.
- Pro: cria empresa `plan='free', status='pending_payment'` + admin → tela "Pro solicitado".
- Usa `slugUnico()` de `includes/tenant.php`.
- Sem JS obrigatório — 100% server-side.

**Teste:** Free → login automático + onboarding. Pro → tela de aguardo. E-mail duplicado → erro com sugestão de login. CNPJ duplicado → erro. Slug auto com sufixo.

---

## Fase 5 — Onboarding e admin/layout ✅

### T19 — `admin/onboarding.php` ✅
- Wizard 3 passos (`first_login=1`): (1) nome empresa; (2) Pro: upload logo + cor / Free: widget upgrade; (3) `UPDATE admins SET first_login=0` → redirect `admin/index.php`.

**Teste:** admin Free — sem upload (widget upgrade). Admin Pro — upload logo funcional.

---

### T20 — `admin/upgrade.php` ✅
- Benefícios Pro + contato (`support_email` de `system_settings`). POST → `companies.status='pending_payment'`.

**Teste:** após POST → super-admin vê badge `⏳ Pro Solicitado`.

---

### T21 — Atualizar `admin/layout.php` com tenant info ✅
- Nome empresa no topbar. Badge plano `[Free]` / `[Pro]`. Banner aviso ≥80% quizzes. Banner "Pro Solicitado". Banner impersonation. Nav link "Upgrade" (só Free).

**Teste:** admin Free com 10/12 quizzes → banner amarelo. Admin Pro → sem banner de limite.

---

## Fase 6 — Filtros `company_id` no portal admin ✅

### T22 — `company_id` no login admin ✅
**Arquivo:** `includes/auth.php`
- `adminLogin()`: busca por e-mail **E** `company_id` do tenant. Salva `company_id` na sessão.
- `requireLogin()`: valida `session company_id` vs `tenantId()`.

**Teste:** admin da empresa A não loga no subdomínio da empresa B.

---

### T23 — Filtros `company_id` em `admin/quizzes.php` ✅
- Todas as queries filtradas por `tenantId()`. Criação: `company_id=tenantId()`. Edição/delete: verificar `company_id` (proteção IDOR). Criar quiz: `tenantCanCreateQuiz()` → se falso, modal upgrade.

**Teste:** admin A não vê quizzes de B. Limite Free atingido → modal upgrade.

---

### T24 — Filtros em `admin/results.php`, `admin/live.php` ✅
- Joins de participants/answers com `WHERE company_id = tenantId()`.

**Teste:** participantes de B não aparecem nos resultados de A.

---

### T25 — Filtros em `admin/sectors.php` e `admin/users.php` ✅
- Sectors: UNIQUE `(company_id, name)`. Users: filtrar por `company_id`.

**Teste:** empresa A e B podem ter setor "RH" sem conflito.

---

### T26 — Filtros em `api/*.php` ✅
- Todos os endpoints: `resolveTenant()` + `tenantId()` + filtros em todas as queries.

**Teste:** POST para API de empresa A com session de empresa B → 403.

---

## Fase 7 — Fluxo público tenant-aware ✅

### T27 — `index.php` tenant-aware ✅
- `resolveTenant()` no topo. `null` → landing PageQuiz padrão. Tenant encontrado → landing da empresa com logo/cor (Pro) ou defaults (Free). Injetar `--company-primary` como CSS var.

**Teste:** `alphaclin.pagequiz` → landing Alphaclin com suas cores.

---

### T28 — `quiz.php` e `verify.php` tenant-aware ✅
- `quiz.php`: busca quiz por `id AND company_id`. Certificado Free: template padrão. Certificado Pro: logo + cor.
- `verify.php`: verificar certificado no contexto do `company_id` correto.

**Teste:** URL de quiz da empresa A não abre no subdomínio de B → 404.

---

## Fase 8 — Limites e rebaixamento ⬜

### T29 — Rebaixamento Pro → Free ⬜
- Ao alterar plano para Free: contar quizzes ativos. Se acima do limite: inativar os mais antigos (`ORDER BY created_at ASC`). `logAudit('downgrade', …, json_encode(['inactivated_ids'=>…]))`. Banner na empresa.

**Teste:** 15 quizzes → rebaixar (limite=12) → 3 mais antigos inativos. Audit registra IDs.

---

### T30 — Banner de rebaixamento no `admin/layout.php` ⬜
- Se empresa tem quizzes acima do limite e plano Free → banner laranja com count e botão "Ver quizzes desativados".

---

## Fase 9 — Segurança e testes de isolamento ⬜

### T31 — Security review: grep por queries sem `company_id` ⬜
- Grep em `admin/`, `user/`, `api/` por SELECT/INSERT/UPDATE/DELETE sem `company_id`. Corrigir todas as queries de domínio.

**Teste:** 0 queries de domínio sem `company_id`.

---

### T32 — Testes de isolamento cross-tenant ⬜
- Admin empresa A tenta acessar quiz da empresa B via URL → 403. API cross-tenant → 403.

**Teste:** todos os acessos cross-tenant retornam 403 sem vazar dados.

---

### T33 — Teste limite Free configurável ⬜
- `free_quiz_limit=3` → empresa Free com 3 quizzes não cria o 4º. Alterar para 5 → pode criar mais 2.

**Teste:** `tenantCanCreateQuiz()` respeita `system_settings` sem deploy.

---

### T34 — Teste de suspensão ⬜
- Suspender empresa A → subdomínio mostra página suspensão. Reativar → acesso normalizado.

**Teste:** suspensão e reativação imediatas.

---

## Resumo

| Fase | Tasks | Status | Entrega |
|---|---|---|---|
| 1 — Schema | T01–T04 | ✅ | Banco com `company_id` em todas as tabelas |
| 2 — Tenant | T05–T07 | ✅ | Resolução de tenant por subdomínio + `?c=slug` |
| 2B — Atribuição | T05B–T05F | ✅ | `quiz_assignments`, visibilidade por setor, link de acesso |
| 3 — Super-admin | T08–T17 | 🚧 | Portal de gestão completo (sem `cadastro.php` público) |
| 4 — Cadastro | T18 | ⬜ | Auto-cadastro público Free/Pro |
| 5 — Onboarding | T19–T21 | ✅ | Wizard + admin/layout com info de plano |
| 6 — Admin filtros | T22–T26 | ✅ | Isolamento no portal admin e API |
| 7 — Quiz público | T27–T28 | ✅ | Quiz e certificado tenant-aware |
| 8 — Limites | T29–T30 | ⬜ | Rebaixamento automático + banners |
| 9 — Segurança | T31–T34 | ⬜ | Review automatizado + testes de isolamento |

> **Portal do Usuário** (dashboard, certificado, pré-preenchimento) — documentado separadamente em `Ai/specs/user-portal/design.md`.
