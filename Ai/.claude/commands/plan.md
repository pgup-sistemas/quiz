---
description: Gera specs/<feature>/design.md a partir de requirements.md já aprovado, definindo arquitetura, modelo de dados e decisões técnicas.
---

Você vai criar o design técnico para uma feature cujo requirements.md já foi aprovado.

Argumento do usuário (slug da feature): $ARGUMENTS

Passos:

1. Leia `CLAUDE.md` e `specs/<slug>/requirements.md`. Se requirements.md não existir, pare e instrua o usuário a rodar `/specify` primeiro.

2. Se requirements.md tiver "Perguntas em aberto" não resolvidas, pare e sinalize — não prossiga design sobre requisito não confirmado.

3. Explore o código existente relevante (models, migrations, controllers/resources Filament já relacionados ao domínio da feature) antes de propor qualquer estrutura nova — reaproveitar padrão existente é preferível a introduzir um novo, salvo justificativa.

4. Gere `specs/<slug>/design.md` com esta estrutura:

   - **Visão geral técnica:** como a feature se encaixa na arquitetura existente (1 parágrafo).
   - **Modelo de dados:** tabelas novas/alteradas, com colunas, tipos, FKs e índices. Para cada FK, referenciar explicitamente qual critério de aceite EARS do requirements.md ela sustenta.
   - **Camadas afetadas:** Models, Policies, Filament Resources, Livewire Components, Jobs/Listeners, rotas — o que é novo vs modificado.
   - **Fluxos assíncronos (se houver):** jobs, filas, agendadores — incluindo comportamento de falha/retry, relevante especialmente para rotinas temporais (ex.: expiração, verificação periódica).
   - **Notificações:** eventos que disparam comunicação, canal, e para qual perfil.
   - **Riscos técnicos e trade-offs:** decisões com mais de uma alternativa viável — apresentar as opções consideradas e a justificativa da escolha. Se a decisão for estrutural o suficiente para impactar outras features, sinalizar necessidade de ADR (`docs/adr/`).
   - **Rastreabilidade:** tabela mapeando cada critério de aceite EARS do requirements.md ao componente técnico que o implementa.

5. Não gere código nesta fase. Design.md é o contrato antes da geração de tarefas (`/tasks`).

6. Ao final, informe: "Design gerado em specs/<slug>/design.md — revisão humana obrigatória antes de rodar /tasks."
