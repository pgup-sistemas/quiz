# ADR-0001 — Estratégia de isolamento de tenant

**Data:** 2026-07-09
**Status:** Aceito
**Decisores:** Oézios Normando (PageUp Sistemas)

---

## Contexto

O PageQuiz está evoluindo de single-tenant para SaaS multi-tenant. É necessário decidir como isolar os dados de cada empresa (tenant) entre si.

## Opções consideradas

### Opção A — Shared SQLite com `company_id` em todas as tabelas de domínio *(escolhida)*

Um único arquivo `data/quiz.db` compartilhado. Todas as tabelas de domínio recebem coluna `company_id INTEGER NOT NULL`. Toda query filtra por `company_id` via helper `tenantId()`.

**Prós:**
- Backup único e simples
- Queries de super-admin (agregações cross-tenant) sem JOIN entre bancos
- Deploy e migração simplificados — um único `initDB()` gerencia tudo
- Compatível com a stack atual (SQLite via PDO, sem connection pool)

**Contras:**
- Risco de query sem filtro `company_id` vazar dados entre tenants — mitigado por `tenantGuard()` obrigatório e review de código
- SQLite tem limitações de concorrência em escrita — aceitável para o volume esperado (< 500 tenants, baixa concorrência simultânea)
- Crescimento do banco proporcional a todos os tenants — monitorar tamanho do arquivo

### Opção B — Arquivo SQLite separado por tenant

Cada empresa tem `data/quiz_{company_id}.db` próprio.

**Prós:**
- Isolamento físico total — vazamento de dados estruturalmente impossível
- Backup/restore por tenant independente

**Contras:**
- Múltiplas conexões PDO simultâneas — sem connection pool em PHP vanilla, cada request abre N arquivos
- Queries de super-admin exigem iterar sobre todos os bancos — inviável para dashboard e auditoria
- Deploy de migrations exige rodar `initDB()` em cada arquivo — complexidade operacional alta
- Restaurar um tenant específico requer identificar o arquivo correto

### Opção C — MySQL com schema por tenant

**Descartado:** a stack usa SQLite por decisão intencional (sem dependência de servidor de banco). Migrar para MySQL neste ponto seria mudança de stack, não apenas de estratégia de isolamento.

## Decisão

**Opção A — Shared SQLite com `company_id`.**

O vetor de risco (query sem filtro) é mitigado por:
1. Helper `tenantId()` que lança `RuntimeException` se chamado sem tenant resolvido
2. Convenção de código obrigatória documentada em `CLAUDE.md`
3. Checklist de security review (T32) com grep ativo por queries sem filtro
4. Testes de isolamento (T29) que cobrem tentativa de acesso cross-tenant

## Consequências

- Todas as tabelas de domínio DEVEM ter `company_id` — nunca criar tabela de domínio sem ela
- Toda query em `admin/`, `user/`, `api/` e páginas públicas DEVE usar `tenantId()` — sem exceções
- O crescimento do banco deve ser monitorado trimestralmente — se ultrapassar 1GB, reavaliar esta decisão com ADR de revisão
- Esta decisão deve ser revisada se o número de tenants superar 500 ou se houver requisito regulatório de isolamento físico de dados por cliente
