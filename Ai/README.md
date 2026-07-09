# Template padrão de projeto — Claude Code (harness) + MCP + Skills + SDD

Scaffold reutilizável para novos projetos web, agnóstico de domínio de negócio. A stack sugerida por default (Laravel 11 + Filament 3 + Livewire + MySQL) vive na Skill `.claude/skills/laravel-filament-stack/` — substitua ou remova essa skill se o projeto usar outra stack. Copie esta estrutura para a raiz de cada novo projeto e preencha os placeholders `[ ]` em `CLAUDE.md` via `/constitution`, e o modelo de domínio via `/bootstrap`.

## O que este template resolve

Sem estrutura, cada sessão de Claude Code começa do zero: reexplica stack, reinventa convenção, não tem rastreabilidade entre "o que foi pedido" e "o que foi implementado", e não tem gate mínimo de qualidade/segurança antes de considerar uma feature pronta. Este template fixa quatro camadas:

1. **CLAUDE.md** — regras duráveis (constituição): stack, convenções, segurança, gate de teste. Lido por todo comando, sempre.
2. **Skills** (`.claude/skills/`) — conhecimento procedural reutilizável entre projetos, carregado sob demanda quando a tarefa é relevante.
3. **Domain model** (`docs/domain-model.md`, gerado por `/bootstrap`) — glossário e entidades canônicas que ancoram toda spec futura, evitando divergência de nomenclatura entre features.
4. **Specs** (`specs/<feature>/`) — rastreabilidade por feature: requirements (EARS, incluindo NFR) → design → tasks → implementação com gate de análise estática/teste → security review → verificação.

## Decisão de design: por que spec-anchored, não spec-first

Existem duas variantes dominantes de SDD:

| | **spec-first** | **spec-anchored** (adotado aqui) |
|---|---|---|
| Spec após implementação | Descartável, referência histórica | Permanece como fonte de verdade viva |
| Manutenção | Baixa — spec não precisa acompanhar mudanças futuras | Alta — toda mudança de comportamento exige atualizar a spec |
| Rastreabilidade a longo prazo | Fraca | Forte — `/verify` sempre pode auditar código contra spec atual |
| Esforço por feature | Menor | Maior (overhead de manter requirements/design sincronizados) |

Spec-anchored favorece contextos onde rastreabilidade contínua e auditabilidade têm valor de negócio explícito — tipicamente domínios regulados, integrações críticas, ou times que precisam justificar decisão técnica a stakeholder não-técnico. **Trade-off assumido:** exige disciplina de revisar e atualizar `requirements.md`/`design.md` quando a implementação diverge — se essa disciplina não é mantida, a spec apodrece e vira documentação mentirosa, o que é pior do que não ter spec. Para protótipo descartável ou contexto onde velocidade pesa mais que auditabilidade, considere spec-first e pule a manutenção pós-entrega — isso é decisão por projeto, ajustável em `CLAUDE.md`, não mudança estrutural neste template.

## Fluxo de uso

```
/bootstrap              # fase 0 — uma vez por projeto, gera docs/domain-model.md
/constitution            # uma vez por projeto (ou quando a stack/regras mudam)
/specify <descrição>      # por feature — requirements + NFR, valida contra domain-model.md
/plan <slug>              # gera design.md a partir do requirements aprovado
/tasks <slug>             # decompõe design.md em tarefas pequenas e testáveis
/implement <slug>         # executa as tarefas: análise estática + teste por tarefa
/security-review <slug>   # checklist de segurança — dado sensível, autorização, dependências novas
/verify <slug>            # audita código real contra os critérios de aceite originais, bloqueia se houver divergência
/adr <descrição>          # registra formalmente qualquer desvio arquitetural, a qualquer momento
```

Cada seta acima tem um checkpoint de revisão humana antes da próxima fase — isso é intencional, não redundância. É o que evita o problema clássico de agente que gera 2000 linhas de código a partir de uma spec ambígua e só descobre o desalinhamento depois.

## Detecção de divergência — onde e como o agente avisa

Rastreabilidade sem detecção ativa de desvio é documentação decorativa. Este template implementa 3 pontos de checagem, cada um cobrindo um tipo diferente de divergência:

| Ponto de checagem | O que detecta | Comportamento |
|---|---|---|
| `/specify`, etapa 1.1 | Nova entidade nomeada de forma diferente de algo já existente em `domain-model.md` | **Bloqueia** — pergunta se é sinônimo ou entidade nova antes de prosseguir |
| `/implement`, etapa 4 | Necessidade real de código diverge do que `design.md` previu | **Pausa** — oferece ajustar design.md ou abrir `/adr`, nunca resolve silenciosamente |
| `/verify`, etapas 3-4 | Código implementado diverge do que `requirements.md` descreve | **Bloqueia o relatório** com alerta `⚠️ DIVERGÊNCIA DETECTADA` no topo, antes de qualquer outra informação |

**Limite honesto:** estes 3 pontos só disparam quando você *roda o comando correspondente*. Se alguém editar código diretamente sem passar por `/implement`, nenhum gate impede isso em tempo real — a divergência só é capturada na próxima execução de `/verify`. Não há hook de pre-commit ou CI neste template (ver Limitações conhecidas); se isso for necessário, é a extensão natural seguinte: rodar `/verify` como step obrigatório de pipeline, falhando o build se houver `DIVERGÊNCIA`.

## Camadas de qualidade/segurança cobertas — e o que ainda não é

| Camada | Coberto neste template | Onde |
|---|---|---|
| Requisitos funcionais rastreáveis | Sim | `/specify`, EARS |
| Requisitos não-funcionais (performance, disponibilidade, segurança específica) | Sim | Seção NFR de `requirements.md` |
| Análise estática (lint/type-check) | Sim, como gate por tarefa | `/implement`, etapa 3 |
| Checklist de segurança (autorização, exposição de dado sensível, dependência vulnerável) | Sim, como gate manual/comando | `/security-review` |
| Decisão arquitetural rastreável | Sim | `/adr` |
| **CI/CD e gate de pipeline automatizado** | **Não** | Fora de escopo — ver Limitações |
| **Observabilidade (log estruturado, métrica, tracing)** | **Não** | Fora de escopo — ver Limitações |
| **Revisão de código por par humano (PR checklist formal)** | **Não** | `/security-review` cobre parte disso, mas não é substituto de code review |
| **Pentest/revisão de segurança formal exigida por regulação setorial** | **Não** | `/security-review` é gate mínimo de engenharia, não certificação |

## Estrutura de diretórios

```
.
├── CLAUDE.md                              # Constituição do projeto
├── .mcp.json                              # Servidores MCP (MySQL read-only, GitHub)
├── .claude/
│   ├── commands/                          # /bootstrap /constitution /specify /plan /tasks /implement /security-review /verify /adr
│   └── skills/
│       └── laravel-filament-stack/        # Convenções de stack sugerida — remover/substituir se não aplicável
├── specs/
│   └── _template/                         # requirements.md (com NFR), design.md, tasks.md — copiar por feature
└── docs/
    ├── domain-model.md                    # Gerado por /bootstrap — glossário e entidades canônicas
    └── adr/
        └── _template.md                   # Architecture Decision Records
```

## Configuração do `.mcp.json`

- **`mysql`** (`@benborla29/mcp-server-mysql`): read-only por padrão, com suporte a mascaramento de PII — habilitar escrita apenas via env flags explícitas (`ALLOW_INSERT_OPERATION`, etc.), nunca por padrão. Remover/substituir se o projeto não usar MySQL.
- **`github`**: servidor oficial remoto (`https://api.githubcopilot.com/mcp`), autenticado via PAT. O pacote npm antigo (`@modelcontextprotocol/server-github`) está descontinuado desde abril de 2025 — não usar em projetos novos.
- Credenciais nunca hardcoded — todas via variável de ambiente (`${MYSQL_HOST}`, `${GITHUB_PAT}`, etc.), setadas no shell ou em gerenciador de secrets do projeto, nunca commitadas.

## Quando adicionar um novo MCP server ou Skill

- **MCP server novo:** só adicionar se resolve algo que as ferramentas nativas do harness (bash, view, edit) não resolvem — ex.: introspecção de schema remoto, API externa. Cada servidor adicional aumenta a superfície de ferramentas que o agente precisa selecionar corretamente; acúmulo sem critério degrada a precisão de seleção de ferramenta.
- **Skill nova:** quando um padrão se repete entre 2+ projetos (ex.: convenção de nomenclatura, armadilha conhecida da stack). Skill de projeto único deve ficar em `CLAUDE.md`, não virar Skill — Skills são para conhecimento portátil entre projetos.

## Limitações conhecidas deste template

- Não cobre CI/CD nem ambiente de deploy — escopo é organização do fluxo de desenvolvimento assistido por agente, não pipeline de entrega.
- Não cobre observabilidade (log estruturado, métricas, tracing) — se o projeto exige isso, tratar como convenção adicional em `CLAUDE.md` Seção 3, ou como Skill dedicada.
- Pressupõe que o projeto já tem (ou terá) testes automatizados como gate — se o projeto não tiver cultura de teste, `/verify` e o gate de `/implement` perdem grande parte do valor.
- `/security-review` é checklist de engenharia, não substitui processo formal de segurança exigido por regulação setorial específica do domínio do projeto — se aplicável, declarar esse processo adicional em `CLAUDE.md` Seção 4.
