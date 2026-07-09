# CLAUDE.md — Constituição do Projeto

> Este arquivo é a fonte de verdade de decisões duráveis do projeto. Toda spec, plano e ação de agente deve respeitar o que está aqui. Não é documentação de feature — é regra de longo prazo (stack, convenções, segurança, política de dependências). Specs individuais vivem em `specs/<feature>/`.
>
> Para gerar/atualizar este arquivo por entrevista guiada, use `/constitution`. Para o modelo de domínio (entidades, glossário), use `/bootstrap` — este arquivo não descreve o negócio, descreve como o agente deve trabalhar neste projeto.

---

## 1. Identidade do projeto

- **Nome:** [ ]
- **Domínio de negócio:** [ ] (ver `docs/domain-model.md` para detalhamento)
- **Stakeholders/decisores técnicos:** [ ]

## 2. Stack (decisão fechada — não redebater por feature)

- **Backend:** [ ] (default sugerido: Laravel 11)
- **Admin/CRUD:** [ ] (default sugerido: Filament 3, se o projeto tiver painel administrativo)
- **Interatividade:** [ ] (default sugerido: Livewire)
- **Banco de dados:** [ ] (default sugerido: MySQL 8)
- **Cache/filas (se aplicável):** [ ]
- **Multi-tenancy (se aplicável):** [ ] — confirmar no início do projeto se o domínio exige isolamento por tenant
- **Frontend fora do admin (se houver):** [ ]

Desvios desta stack exigem ADR (`docs/adr/`) justificando o motivo — ver Seção 6. Se este projeto usa a skill de stack padrão (`.claude/skills/`), reflita a stack real aqui — a skill assume Laravel/Filament/Livewire/MySQL; ajuste ou remova se o projeto usar outra combinação.

## 3. Convenções de código (aplicadas por todos os agentes)

- Padrão de estilo de código: [ ] (ex.: PSR-12 para PHP, ESLint config para JS/TS)
- Nomenclatura: [ ] (ex.: tabelas em `snake_case` plural, classes em `PascalCase` singular, FKs como `<entidade_singular>_id`)
- Toda tabela que armazena dado classificado como sensível (ver Seção 4) tem `created_by`, `updated_by`, timestamps, e histórico de alteração via mecanismo de auditoria — decisão fechada por projeto, registrar em ADR se divergir.
- Migrations (ou equivalente de controle de schema) são a única fonte de verdade de schema — nunca alterar coluna direto no banco.
- Toda FK obrigatória por regra de negócio deve existir no schema, não apenas validada em código de aplicação.

## 4. Segurança e compliance (não negociável)

- **Classificação de dado sensível:** definir explicitamente quais entidades/campos deste projeto são sensíveis (dado pessoal identificável, financeiro, credencial, ou outro critério regulatório aplicável — LGPD/GDPR/setorial conforme o domínio) — preencher em `docs/domain-model.md` ou aqui: [ ]
- Dado classificado como sensível nunca é logado em texto claro, nunca é exposto em endpoint sem autorização explícita (Policy/Guard), independentemente de o middleware de autenticação já cobrir a rota.
- Toda rota de mutação de estado crítico do domínio (definido em `docs/domain-model.md`) exige autorização explícita por Policy, não apenas autenticação.
- Segredos nunca em código, spec, ou ADR — apenas `.env` / secret manager.
- Dependências de terceiros: rodar auditoria de vulnerabilidade (ex.: `composer audit`, `npm audit`, ou equivalente da stack) como parte do gate de `/security-review` antes de merge em feature que introduz nova dependência.

## 5. Testes (gate de aceite)

- Cobertura mínima exigida para merge: [ ]% (definir por projeto)
- Toda feature gerada via SDD (`specs/<feature>/tasks.md`) só é considerada `done` quando os critérios de aceite EARS do `requirements.md` correspondente têm teste automatizado associado.
- Fluxos classificados como críticos em `docs/domain-model.md` exigem teste de integração, não apenas unitário.
- Análise estática (linter + type-checker da stack, ex.: PHPStan/Larastan para PHP, tsc/ESLint para TS) roda como parte de `/implement` antes de marcar qualquer tarefa como concluída — ver comando `/implement`.

## 6. Decisões arquiteturais (ADRs)

Qualquer decisão que desvie da Seção 2/3, ou que tenha impacto estrutural relevante (ex.: escolha de fila assíncrona, estratégia de cache, modelagem de multi-tenancy), deve ser registrada em `docs/adr/NNNN-titulo.md` usando o template em `docs/adr/_template.md`. Specs não substituem ADRs — spec é sobre *o que* construir; ADR é sobre *por que* uma decisão estrutural foi tomada.

## 7. MCP e ferramentas habilitadas neste projeto

Ver `.mcp.json` na raiz. Qualquer servidor MCP adicional deve ser justificado (o que ele resolve que as ferramentas nativas do harness não resolvem) antes de ser adicionado — evitar acúmulo de ferramentas não utilizadas, que degrada a seleção de ferramentas do agente.

## 8. Skills habilitadas

- `.claude/skills/laravel-filament-stack/` — convenções de código específicas da stack sugerida (Seção 2). Remover ou substituir se o projeto usar outra stack.

## 9. Fluxo obrigatório para novas features (SDD)

Nenhuma feature não-trivial (qualquer coisa além de fix pontual) deve ir direto para código. Fluxo:

0. `/bootstrap` → gera `docs/domain-model.md` (uma vez por projeto, ou ao integrar domínio novo) — glossário e entidades canônicas que ancoram toda spec futura
1. `/specify` → gera `specs/<feature>/requirements.md` (EARS, incluindo requisitos não-funcionais), validando nomenclatura contra `docs/domain-model.md`
2. Revisão humana do requirements.md (checkpoint obrigatório — não pular)
3. `/plan` → gera `specs/<feature>/design.md`
4. `/tasks` → gera `specs/<feature>/tasks.md`
5. `/implement` → executa tasks.md, task por task, com análise estática e testes antes de marcar conclusão
6. `/security-review` → checklist de segurança para features que tocam dado sensível ou introduzem dependência nova (ver Seção 4)
7. `/verify` → valida cada critério de aceite do requirements.md contra o código gerado, bloqueando com alerta explícito se houver `DIVERGÊNCIA`
8. `/adr` → registra formalmente qualquer desvio arquitetural identificado em `/plan`, `/implement` ou `/verify`

Detalhes de cada fase em `specs/_template/`.
