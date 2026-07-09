---
description: Cria ou atualiza CLAUDE.md (constituição do projeto) por entrevista guiada, codificando as convenções e decisões duráveis já existentes ou desejadas para o projeto.
---

Você vai criar ou atualizar o arquivo `CLAUDE.md` na raiz do projeto, que funciona como constituição — regras duráveis que toda spec, plano e implementação futura deve respeitar.

Argumento opcional do usuário: $ARGUMENTS (pode conter respostas já dadas para acelerar a entrevista; se vazio, conduza a entrevista completa)

Passos:

1. Se já existe `CLAUDE.md`, leia-o primeiro. Trate como baseline a ser atualizada, não substituída — preserve decisões existentes que não foram explicitamente contestadas.

2. Se o projeto já tem código (não é greenfield), explore a estrutura real antes de perguntar: leia `composer.json`/`package.json`, liste migrations existentes, identifique padrões de nomenclatura já em uso. Não pergunte o que já é observável no código — apenas confirme.

3. Conduza entrevista cobrindo, na ordem, apenas o que não foi respondido em $ARGUMENTS nem inferido do código:
   - Identidade do projeto (nome, domínio de negócio)
   - Stack técnica fechada (framework, banco, cache/filas, multi-tenancy)
   - Convenções de nomenclatura e estilo de código
   - Requisitos de segurança/compliance específicos do domínio (ex.: LGPD, dados sensíveis)
   - Gate de testes (cobertura mínima, o que é obrigatório vs opcional)
   - Política de ADR (quando uma decisão exige registro formal)

4. Escreva cada decisão em linguagem EARS sempre que for uma regra comportamental (ex.: "O sistema SEMPRE deve registrar auditoria de mudança de status em entidade classificada como crítica"), não como prosa vaga.

5. Gere/atualize `CLAUDE.md` seguindo a estrutura de seções do template padrão (Identidade, Stack, Convenções, Segurança, Testes, ADRs, MCP, Skills, Fluxo SDD).

6. Ao final, liste explicitamente quais seções foram alteradas em relação à versão anterior (se havia uma), para revisão humana antes de commit.

Não prossiga para gerar specs de feature nesta interação — este comando produz apenas a constituição.
