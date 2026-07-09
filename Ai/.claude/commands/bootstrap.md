---
description: Fase 0 — executar uma única vez no início do projeto (ou ao integrar um domínio novo). Deriva o modelo de domínio (glossário, entidades, relacionamentos de alto nível) que ancora todas as specs de feature subsequentes.
---

Você vai estabelecer a baseline de domínio do projeto, antes de qualquer `/specify` de feature individual.

Argumento do usuário (descrição de alto nível do sistema/domínio de negócio): $ARGUMENTS

Passos:

1. Se `docs/domain-model.md` já existe, trate como baseline a atualizar, não substituir — divergências entre o que existe e o novo argumento devem ser sinalizadas explicitamente ao usuário antes de sobrescrever qualquer entidade já definida.

2. Se o projeto já tem código (não greenfield), explore migrations/models existentes e derive o modelo de domínio real do código antes de perguntar — não presuma nomenclatura que já está decidida no schema.

3. Se a descrição em $ARGUMENTS for insuficiente para identificar as entidades centrais e seus relacionamentos, faça no máximo 3 perguntas objetivas. Não invente cardinalidade de relacionamento (1:N vs N:N) sem confirmar quando não é óbvio pelo domínio.

4. Gere `docs/domain-model.md` com esta estrutura:

   - **Glossário (ubiquitous language):** termo canônico de cada entidade/conceito do domínio, com definição de 1 linha. Esta é a nomenclatura oficial — toda spec futura deve usar estes termos, não sinônimos.
   - **Entidades centrais:** para cada entidade, nome canônico, atributos-chave (não schema completo — isso é papel do `/plan` por feature), e propósito de negócio em 1 frase.
   - **Relacionamentos entre entidades:** tabela com Entidade A | Relacionamento | Entidade B | Cardinalidade | Regra de negócio associada (se houver).
   - **Perfis/atores do sistema:** lista de perfis de usuário reconhecidos no domínio (ex.: Cliente, Operador, Administrador) — esta lista alimenta a Seção 3 de qualquer spec futura.
   - **Fora do domínio atual:** conceitos adjacentes explicitamente não cobertos por este projeto, para evitar que uma spec futura assuma escopo não combinado.

5. Atualize `CLAUDE.md` para referenciar `docs/domain-model.md` na Seção 1 (Identidade do projeto), em vez de duplicar a descrição de domínio em texto livre.

6. Ao final, informe: "Modelo de domínio gerado em docs/domain-model.md — toda spec futura (`/specify`) valida nomenclatura de entidade contra este arquivo antes de prosseguir."
