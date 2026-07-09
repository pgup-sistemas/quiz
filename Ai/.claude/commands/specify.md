---
description: Gera specs/<feature>/requirements.md em formato EARS a partir de uma descrição de alto nível da feature.
---

Você vai criar a especificação de requisitos para uma nova feature, seguindo Spec-Driven Development.

Argumento do usuário (descrição de alto nível da feature): $ARGUMENTS

Passos:

1. Leia `CLAUDE.md` primeiro — toda decisão de stack, convenção e segurança já fechada ali não deve ser redebatida ou contradita nesta spec.

1.1. Leia `docs/domain-model.md` se existir. Para cada entidade/perfil mencionado na feature, verifique se já existe no glossário:
   - **Existe com o mesmo nome:** use o termo canônico, sem variação.
   - **Existe com nome diferente (sinônimo aparente):** PARE e sinalize explicitamente ao usuário: "A feature descreve '[termo novo]', que parece equivalente a '[termo existente]' já definido em domain-model.md. Confirme se é a mesma entidade antes de prosseguir." Não decida sozinho que são a mesma coisa nem que são diferentes.
   - **Não existe (entidade genuinamente nova):** prossiga, mas ao final da spec sinalize que `docs/domain-model.md` precisa ser atualizado com esta entidade — não deixe o glossário desatualizado silenciosamente.
   - **`docs/domain-model.md` não existe:** avise que a feature está sendo especificada sem baseline de domínio e recomende rodar `/bootstrap` antes, mas prossiga se o usuário confirmar que quer seguir sem essa etapa.

2. Derive um slug curto em `kebab-case` para a feature (ex.: `re-verificacao-anual-medico`) e crie o diretório `specs/<slug>/`.

3. Se a descrição em $ARGUMENTS for ambígua ou incompleta em pontos que mudam o comportamento do sistema (não em detalhes de UI triviais), pare e faça no máximo 3 perguntas objetivas antes de prosseguir. Não presuma regra de negócio crítica (ex.: o que acontece quando um prazo expira) sem confirmar.

4. Gere `specs/<slug>/requirements.md` com esta estrutura:

   - **Contexto:** 2-3 frases situando a feature no domínio do produto.
   - **Histórias de usuário:** formato "Como [perfil], eu quero [capacidade], para que [valor]". Uma por caso de uso relevante, cobrindo todos os perfis afetados (não apenas o perfil primário).
   - **Critérios de aceite (EARS):** para cada história, usar os 5 padrões EARS conforme aplicável:
     - Ubíquo: "O sistema SEMPRE deve [comportamento]"
     - Orientado a evento: "QUANDO [evento] ocorrer, o sistema deve [comportamento]"
     - Orientado a estado: "ENQUANTO [estado] estiver ativo, o sistema deve [comportamento]"
     - Comportamento indesejado: "SE [condição de erro/borda] ocorrer, ENTÃO o sistema deve [comportamento]"
     - Feature opcional: "ONDE [condição/configuração] estiver habilitada, o sistema deve [comportamento]"
   - **Fora de escopo:** liste explicitamente o que esta feature NÃO cobre, para evitar scope creep na fase de implementação.
   - **Requisitos não-funcionais (NFR):** performance, disponibilidade, segurança específica e volumetria esperada, em formato EARS quando aplicável (ex.: "QUANDO a listagem exceder N registros, o sistema deve paginar"). Se a feature genuinamente não tem NFR relevante além do que já está em `CLAUDE.md`, declare isso explicitamente em vez de omitir a seção — omissão silenciosa é ambígua entre "não se aplica" e "esquecido".
   - **Dependências:** outras specs/features das quais esta depende, ou que dependem dela.
   - **Perguntas em aberto:** qualquer ponto que precisa de validação com stakeholder antes do `/plan`.

5. Não inclua decisão de arquitetura/implementação neste arquivo (isso é `/plan`). Requirements.md descreve *comportamento observável*, não *como construir*.

6. Se a etapa 1.1 identificou entidade genuinamente nova, adicione-a a `docs/domain-model.md` (seção Entidades centrais e Glossário) neste momento — não deixe para uma etapa manual futura que pode não acontecer.

7. Ao final, informe explicitamente: "Requirements gerado em specs/<slug>/requirements.md — revisão humana obrigatória antes de rodar /plan." Se domain-model.md foi atualizado, informe isso também.
