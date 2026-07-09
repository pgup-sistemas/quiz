---
description: Cria um Architecture Decision Record formal em docs/adr/, numerado sequencialmente, a partir de uma decisão de desvio identificada durante /plan, /implement ou discussão direta.
---

Você vai registrar formalmente uma decisão arquitetural.

Argumento do usuário (a decisão a registrar, e contexto que a motivou): $ARGUMENTS

Passos:

1. Liste `docs/adr/` e determine o próximo número sequencial (`NNNN`, 4 dígitos, ex.: `0001`).

2. Se $ARGUMENTS referenciar uma feature específica (`specs/<slug>/`), leia `design.md` dessa feature para contexto — a decisão pode já estar parcialmente descrita lá na seção "Riscos técnicos e trade-offs".

3. Copie `docs/adr/_template.md` para `docs/adr/NNNN-<slug-da-decisao>.md` e preencha:
   - **Contexto:** qual restrição técnica ou forçamento de negócio motivou a decisão — seja específico, não genérico ("performance" não é contexto, "consulta teria N+1 query em listagem com >500 registros" é contexto).
   - **Decisão:** frase direta, sem hedging.
   - **Alternativas consideradas:** no mínimo 2, com prós/contras reais — se só havia uma alternativa viável, declare isso explicitamente em vez de preencher a tabela artificialmente.
   - **Consequências:** inclua débito técnico assumido, não apenas benefícios.
   - **Impacto em CLAUDE.md:** se esta decisão contradiz ou refina algo já escrito na constituição do projeto, sinalize qual seção precisa ser atualizada — não deixe a constituição desatualizada silenciosamente.

4. Se a Seção "Impacto em CLAUDE.md" indicar necessidade de atualização, pergunte ao usuário se deve aplicar a mudança em `CLAUDE.md` agora ou apenas sinalizar para revisão posterior — não editar a constituição sem confirmação explícita.

5. Ao final, informe o caminho do ADR criado e, se aplicável, se `CLAUDE.md` foi ou não atualizado.
