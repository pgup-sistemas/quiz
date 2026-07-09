# Requirements — [Nome da feature]

## Contexto

[2-3 frases situando a feature no domínio do produto. O que existe hoje, o que muda.]

## Histórias de usuário

### US-1: [Título curto]
Como [perfil], eu quero [capacidade], para que [valor de negócio].

**Critérios de aceite:**
- [REQ-1] O sistema SEMPRE deve [comportamento ubíquo]
- [REQ-2] QUANDO [evento] ocorrer, o sistema deve [comportamento]
- [REQ-3] SE [condição de erro/borda] ocorrer, ENTÃO o sistema deve [comportamento]

### US-2: [Título curto]
Como [perfil], eu quero [capacidade], para que [valor de negócio].

**Critérios de aceite:**
- [REQ-4] ...

## Requisitos não-funcionais (NFR)

<!-- Preencher apenas o que se aplica; não deixar em branco sem justificativa se a feature tem impacto de performance, disponibilidade ou segurança -->

- **Performance:** [ex.: QUANDO a listagem retornar mais de N registros, o sistema deve paginar e responder em até Xms — REQ-NFR-1]
- **Disponibilidade/confiabilidade:** [ex.: SE o job de notificação falhar, ENTÃO o sistema deve reenfileirar com backoff e alertar após N tentativas — REQ-NFR-2]
- **Segurança específica da feature:** [ex.: O sistema SEMPRE deve mascarar o campo [X] em logs — REQ-NFR-3]
- **Volumetria esperada:** [ordem de grandeza de registros/requisições que a feature precisa suportar, se relevante para decisão de design]

## Fora de escopo

- [O que esta feature explicitamente não cobre, para evitar scope creep]

## Dependências

- [Outras specs/features das quais esta depende ou que dependem dela]

## Perguntas em aberto

- [ ] [Qualquer ponto que precisa de validação com stakeholder antes de /plan]
