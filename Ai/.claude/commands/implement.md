---
description: Executa as tarefas de specs/<feature>/tasks.md sequencialmente, marcando progresso e respeitando dependências.
---

Você vai implementar as tarefas já planejadas para uma feature.

Argumento do usuário (slug da feature, e opcionalmente um ID de tarefa específica): $ARGUMENTS

Passos:

1. Leia `CLAUDE.md`, `specs/<slug>/requirements.md`, `specs/<slug>/design.md` e `specs/<slug>/tasks.md`. Todos devem existir — se algum faltar, pare e indique qual fase anterior rodar.

2. Se um ID de tarefa específico foi passado em $ARGUMENTS, execute apenas essa tarefa. Caso contrário, execute as tarefas não marcadas em ordem, respeitando dependências (não pule uma tarefa bloqueante para chegar em uma marcada `[P]`).

3. Para cada tarefa:
   - Implemente exatamente o escopo descrito — não expanda para "enquanto estou aqui, já vou melhorar X" fora do escopo da tarefa. Se identificar necessidade real de mudança fora de escopo, registre como nota ao final em vez de implementar sem revisão.
   - Aplique as convenções de `CLAUDE.md` e da(s) skill(s) de stack habilitada(s).
   - Escreva o teste correspondente como parte da mesma tarefa, não depois.
   - Rode a análise estática da stack (linter + type-checker, ex.: PHPStan/Larastan, ESLint/tsc, ou equivalente definido em `CLAUDE.md` Seção 3) sobre os arquivos alterados. Erros de nível "error" bloqueiam a conclusão da tarefa; warnings são reportados mas não bloqueiam, salvo se `CLAUDE.md` definir o contrário.
   - Rode o teste antes de marcar a tarefa como concluída.
   - Marque `- [x]` em `tasks.md` apenas após análise estática sem erro e teste passando.

4. Se uma tarefa expõe um problema no design (ex.: FK que deveria existir e não foi prevista), **pare e sinalize explicitamente** o gap entre design.md e a necessidade real — não prossiga silenciosamente com solução ad-hoc que diverge do design aprovado, isso quebra a rastreabilidade que a spec existe para garantir. Ofereça duas opções ao usuário: (a) ajustar `design.md` manualmente antes de continuar, ou (b) rodar `/adr` para registrar o desvio formalmente se ele representa uma decisão arquitetural válida, não apenas um ajuste cosmético.

5. Ao final da execução (parcial ou completa), reporte: tarefas concluídas, tarefas pendentes e bloqueadas, e qualquer divergência encontrada entre design.md e a implementação real.
