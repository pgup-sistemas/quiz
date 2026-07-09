---
description: Gera specs/<feature>/tasks.md a partir de design.md aprovado, decompondo em tarefas pequenas e testáveis.
---

Você vai decompor um design técnico aprovado em tarefas de implementação.

Argumento do usuário (slug da feature): $ARGUMENTS

Passos:

1. Leia `CLAUDE.md`, `specs/<slug>/requirements.md` e `specs/<slug>/design.md`. Se design.md não existir, pare e instrua a rodar `/plan` primeiro.

2. Gere `specs/<slug>/tasks.md` como checklist markdown (`- [ ]`), na ordem de execução, seguindo estas regras:

   - Cada tarefa deve ser pequena o suficiente para ser revisada em um único diff (migration isolada, um Resource, um Job, etc.) — não agrupe "implementar toda a feature" em uma tarefa.
   - Cada tarefa referencia o(s) critério(s) de aceite EARS que ela satisfaz (ex.: `[REQ-3]`), incluindo requisitos não-funcionais (`[REQ-NFR-1]`) quando o `requirements.md` tiver seção NFR preenchida.
   - Tarefas de schema/migration vêm antes de tarefas de camada de aplicação que dependem delas.
   - Tarefas que podem rodar em paralelo (não tocam nos mesmos arquivos) devem ser marcadas com `[P]`.
   - Toda tarefa de lógica de negócio tem uma tarefa de teste correspondente — não como tarefa separada opcional, mas como parte do critério de conclusão da própria tarefa.
   - Penúltima tarefa da lista é sempre `/security-review`, se a feature toca dado classificado como sensível em `CLAUDE.md`/`docs/domain-model.md` ou introduz dependência nova.
   - Última tarefa da lista é sempre verificação cruzada: rodar `/verify` contra os critérios de aceite.

3. Formato de cada item:
   ```
   - [ ] [ID] Descrição da tarefa (REQ-N) [P se paralelizável]
   ```

4. Não implemente nada nesta fase — apenas gere a lista.

5. Ao final, informe: "Tasks geradas em specs/<slug>/tasks.md — pronto para /implement."
