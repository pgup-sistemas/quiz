# Requirements — SaaS Multi-tenancy (PageQuiz)
> **Revisão:** 2026-07-09 — Ajuste de modelo de planos e auto-cadastro de empresa.

## Contexto

O PageQuiz existe hoje como plataforma single-tenant. A evolução para SaaS permite que qualquer empresa se cadastre autonomamente (self-service) escolhendo entre o plano **Free** ou **Pro**. No plano Free a empresa pode criar até N quizzes (padrão 12, configurável pelo super-admin). No plano Pro os quizzes são ilimitados. O super-admin da PageUp Sistemas gerencia todas as empresas, configura limites globais dos planos e pode suspender ou fazer upgrade de qualquer conta.

---

## Histórias de usuário

### US-1: Auto-cadastro de empresa (self-service)
Como responsável por uma empresa, eu quero me cadastrar na plataforma escolhendo meu plano, para que eu possa começar a usar o PageQuiz sem depender de contato com a PageUp Sistemas.

**Critérios de aceite:**
- [REQ-1] O sistema SEMPRE deve exibir uma página pública de cadastro (`/cadastro`) com campos: nome da empresa, CNPJ/CPF (opcional), e-mail do responsável, senha, e escolha do plano (Free ou Pro).
- [REQ-2] QUANDO o formulário de cadastro for submetido com dados válidos, o sistema deve: criar a empresa, criar o admin inicial com os dados fornecidos e redirecionar para o painel admin da empresa.
- [REQ-3] QUANDO uma empresa for criada via auto-cadastro, o sistema deve gerar automaticamente um slug/subdomínio baseado no nome da empresa (ex.: `Clínica São João` → `clinica-sao-joao`), garantindo unicidade com sufixo numérico se necessário.
- [REQ-4] SE o e-mail já estiver cadastrado como admin de outra empresa, ENTÃO o sistema deve bloquear o cadastro com mensagem clara e sugerir o login.
- [REQ-5] SE o CNPJ/CPF for informado e já estiver vinculado a outra empresa, ENTÃO o sistema deve bloquear o cadastro.
- [REQ-6] QUANDO uma empresa escolher o plano Pro no cadastro, o sistema deve registrar o pedido com status `pending_payment` e exibir instruções de contato/pagamento — ativação do Pro é confirmada manualmente pelo super-admin nesta fase *(sem gateway de pagamento automático)*.

### US-2: Planos Free e Pro
Como responsável por uma empresa, eu quero entender claramente os limites do meu plano e poder solicitar upgrade, para que eu saiba o que posso usar e o que precisa de upgrade.

**Critérios de aceite:**
- [REQ-7] O sistema SEMPRE deve aplicar os seguintes limites por plano:
  - **Free:** até N quizzes ativos (N configurável pelo super-admin, padrão = 12), usuários ilimitados, certificado padrão (sem personalização de logo/cor).
  - **Pro:** quizzes ilimitados, usuários ilimitados, certificado com logo e cor da empresa, subdomínio próprio configurável.
- [REQ-8] QUANDO o super-admin alterar o valor global de `free_quiz_limit`, o sistema deve aplicar o novo limite imediatamente a todas as empresas no plano Free — empresas que já tiverem quizzes acima do novo limite não perdem os quizzes existentes, mas ficam bloqueadas de criar novos até ficarem abaixo do limite.
- [REQ-9] SE uma empresa Free tentar criar um quiz além do limite configurado, ENTÃO o sistema deve bloquear a criação e exibir mensagem convidando para upgrade ao Pro.
- [REQ-10] QUANDO uma empresa Free atingir 80% do limite de quizzes, o sistema deve exibir banner de aviso no painel admin da empresa.
- [REQ-11] O sistema DEVE exibir na página de cadastro e no painel admin uma comparação clara entre Free e Pro.

### US-3: Ativação e gestão de plano Pro
Como super-admin da PageUp Sistemas, eu quero ativar o plano Pro de uma empresa e gerenciar os planos de todas as empresas, para que eu possa monetizar a plataforma e dar suporte aos clientes.

**Critérios de aceite:**
- [REQ-12] QUANDO o super-admin ativar o plano Pro de uma empresa (`status = active, plan = pro`), o sistema deve remover imediatamente os limites de quizzes daquela empresa.
- [REQ-13] QUANDO o super-admin rebaixar uma empresa de Pro para Free, o sistema deve aplicar o limite Free imediatamente — quizzes existentes acima do limite ficam inativos automaticamente (não excluídos), com aviso no painel da empresa.
- [REQ-14] O super-admin DEVE poder configurar o valor global de `free_quiz_limit` em `superadmin/settings.php` sem precisar alterar código — valor padrão 12.
- [REQ-15] QUANDO uma empresa for criada pelo super-admin (via painel), o sistema deve permitir escolher o plano diretamente (Free ou Pro) e definir o admin inicial.

### US-4: Painel super-admin (PageUp Sistemas)
Como super-admin da PageUp, eu quero um painel centralizado para gerenciar todas as empresas, para que eu possa monitorar uso, suspender contas, ativar Pro e suportar clientes.

**Critérios de aceite:**
- [REQ-16] O sistema SEMPRE deve manter o painel super-admin em rota separada (`/superadmin/`) e protegida, inacessível a admins de empresa.
- [REQ-17] QUANDO o super-admin acessar o painel, deve visualizar: lista de empresas, plano atual, total de quizzes usados vs. limite, total de usuários, data de cadastro e status.
- [REQ-18] O super-admin DEVE poder fazer impersonation (entrar como admin de qualquer empresa) sem precisar da senha, com log auditável dessa ação.
- [REQ-19] QUANDO o super-admin suspender uma empresa, o sistema deve bloquear todos os acessos imediatamente, exibindo página de suspensão para admins e usuários daquela empresa.

### US-5: Isolamento de dados por empresa
Como administrador de uma empresa, eu quero que os quizzes, usuários e resultados da minha empresa sejam completamente isolados, para que eu nunca veja dados de outras empresas.

**Critérios de aceite:**
- [REQ-20] O sistema SEMPRE deve filtrar quizzes, questões, participantes, usuários e setores pelo `company_id` do tenant autenticado.
- [REQ-21] SE um administrador tentar acessar um recurso que pertence a outra empresa via URL direta, ENTÃO o sistema deve retornar 403 — nunca expor o recurso.
- [REQ-22] QUANDO um usuário participante fizer login, o sistema deve resolver o tenant pelo subdomínio ou por vínculo direto da conta com a empresa.
- [REQ-23] O sistema SEMPRE deve garantir que certificados emitidos só sejam verificáveis no contexto do tenant correto.

### US-6: Onboarding e personalização da empresa
Como novo admin de empresa, eu quero configurar a identidade da minha empresa no primeiro acesso, para que meus usuários vejam a cara da minha empresa na plataforma.

**Critérios de aceite:**
- [REQ-24] QUANDO um admin fizer o primeiro login, o sistema deve exibir um wizard de onboarding: configurar nome de exibição, logo (upload) e cor primária.
- [REQ-25] O sistema DEVE permitir que admins Pro personalizem logo e cor em qualquer momento, não apenas no onboarding.
- [REQ-26] Admins Free NÃO podem personalizar logo/cor — ao tentar, o sistema exibe mensagem convidando para upgrade Pro.
- [REQ-27] QUANDO o tenant for resolvido por subdomínio, o sistema deve exibir o logo e cor primária da empresa nas páginas públicas (landing, quiz, certificado).

### US-7: Acesso por subdomínio
Como usuário participante, eu quero acessar o quiz pelo endereço da minha empresa, para que a experiência seja personalizada.

**Critérios de aceite:**
- [REQ-28] QUANDO uma requisição chegar, o sistema deve resolver o tenant pelo subdomínio antes de qualquer lógica de negócio.
- [REQ-29] SE o subdomínio não corresponder a empresa ativa, o sistema deve retornar 404.
- [REQ-30] SE a empresa estiver suspensa, o sistema deve exibir página de suspensão ao invés da landing.

---

## Requisitos não-funcionais (NFR)

- **[REQ-NFR-1] Segurança de isolamento:** toda query de domínio DEVE filtrar por `company_id` — `tenantId()` lança exceção se chamado sem tenant resolvido. Nenhuma rota de domínio pode funcionar sem tenant.
- **[REQ-NFR-2] Performance:** resolução de tenant cacheada em sessão — no máximo 1 query por sessão.
- **[REQ-NFR-3] Auditoria:** login de super-admin, impersonation, suspensão, mudança de plano e rebaixamento de Pro → Free devem ser registrados em `audit_log` com timestamp, IP e ator.
- **[REQ-NFR-4] Isolamento de sessão:** sessão autenticada em tenant A não acessa recursos do tenant B mesmo com manipulação de cookies.
- **[REQ-NFR-5] Migração sem perda:** dados do tenant atual (Alphaclin, `company_id = 1`, plano Pro) migrados automaticamente na primeira execução.
- **[REQ-NFR-6] Configuração global por super-admin:** `free_quiz_limit` é um parâmetro de sistema armazenado em banco (tabela `system_settings`), não hardcoded — alterável pelo super-admin sem deploy.

---

## Fora de escopo (nesta fase)

- Gateway de pagamento automático (Stripe, PagSeguro) — ativação Pro é manual pelo super-admin
- E-mail transacional automático (SMTP configurado) — avisos de limite são apenas banners no painel
- Verificação de e-mail no cadastro (e-mail confirmation link)
- SSO / OAuth / SAML
- Múltiplos administradores por empresa com perfis diferentes (nesta fase: 1 admin por empresa)
- App mobile
- Planos com mais de 2 tiers (Free/Pro) — possível expansão futura via `system_settings`

---

## Perguntas em aberto — necessitam decisão antes de /implement

- [ ] **Limite de usuários no Free:** usuários são ilimitados no Free? (sugerido: sim, apenas quizzes são limitados)
- [ ] **Certificado no Free:** o plano Free emite certificado padrão (sem logo/cor da empresa) ou não emite certificado? (sugerido: emite certificado padrão sem personalização)
- [ ] **Rebaixamento Pro → Free:** quizzes além do limite ficam inativos automaticamente (REQ-13) ou o admin escolhe quais desativar?
- [ ] **Slug/subdomínio no Free:** empresa Free usa subdomínio `slug.pagequiz.com.br` normalmente, ou apenas o Pro tem subdomínio? (sugerido: todos têm subdomínio, diferencial do Pro é personalização)
- [ ] **Duração do Pro:** assinatura mensal/anual controlada manualmente, ou Pro é "vitalício" até o super-admin revogar?
- [ ] **Limite de usuários participantes:** há limite de participantes (não admins) por plano? (sugerido: não nesta fase)

---

## Dependências

- `system_settings` table: armazena `free_quiz_limit` e outros parâmetros globais (REQ-NFR-6, REQ-14)
- Apache wildcard DNS `*.pagequiz.com.br` para subdomínios em produção
- ADR-0001: isolamento shared DB com `company_id` — decisão fechada
