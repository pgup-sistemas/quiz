---
name: laravel-filament-stack
description: Use esta skill sempre que estiver implementando, revisando ou planejando código dentro de um projeto Laravel 11 + Filament 3 + Livewire + MySQL. Cobre convenções de nomenclatura, estrutura de Resources/Policies, padrões de migration, e armadilhas comuns dessa stack. Trigger automático em qualquer tarefa de /implement neste template.
---

# Convenções Laravel 11 + Filament 3 + Livewire + MySQL

## Estrutura de diretórios

- `app/Models/` — um model por tabela, singular, PascalCase.
- `app/Filament/Resources/` — um Resource por entidade gerenciável via admin.
- `app/Filament/Resources/<Entidade>/Pages/` — páginas customizadas do Resource.
- `app/Policies/` — uma Policy por model que tem regra de autorização não trivial (praticamente todo model que envolve dado sensível).
- `app/Jobs/` — jobs assíncronos, nomeados como verbo no infinitivo + entidade (ex.: `NotificarClienteVencimento`, `ProcessarPagamentoPendente`).
- `database/migrations/` — nunca editar migration já aplicada em produção; nova alteração = nova migration.

## Migrations

- Toda FK usa `foreignId('<entidade>_id')->constrained()->cascadeOnDelete()` ou `restrictOnDelete()` — decidir explicitamente por FK, nunca deixar padrão implícito sem pensar no impacto (cascade em dado de auditoria é quase sempre errado).
- Colunas de status usam enum nativo do MySQL ou coluna string com constraint de aplicação documentada no model (`casts`) — nunca número mágico sem mapeamento centralizado.
- Todo campo de data de expiração/vencimento (ex.: validade de credencial, contrato, certificação) é indexado, pois será usado em query de job agendado.

## Filament Resources

- Autorização sempre via Policy, nunca via lógica condicional dentro do Resource (`can()` deve delegar à Policy).
- Campos sensíveis (documento de identificação, dados de contato, credencial) usam `->visible()` condicionado a permissão, não apenas ocultos por convenção de UI.
- Actions que mudam estado crítico de negócio (ex.: aprovar, suspender, reativar um registro) exigem confirmação (`->requiresConfirmation()`) e devem disparar evento/log de auditoria — nunca update silencioso de status.

## Livewire

- Componentes que tocam dado sensível validam autorização no `mount()`, não apenas no Resource que os invoca — Livewire components podem ser acessados fora do fluxo esperado se rota não for protegida corretamente.
- Evitar lógica de negócio dentro do componente Livewire — delegar para Service/Action class testável isoladamente.

## Jobs e Schedule

- Jobs agendados (ex.: rotina de verificação/expiração periódica) devem ser idempotentes — rodar duas vezes no mesmo dia não pode duplicar notificação nem duplicar mudança de status. Usar checagem de estado atual antes de agir, não apenas disparo por tempo decorrido.
- Falha de job deve gerar log/alerta, nunca falhar silenciosamente — usar `failed()` method ou listener de `JobFailed`.

## Testes

- Testes de Policy são obrigatórios para qualquer entidade com dado sensível — testar explicitamente o caso de acesso negado, não apenas o caso de acesso permitido.
- Testes de Job assíncrono devem cobrir o cenário de execução duplicada (idempotência), não apenas o caminho feliz único.

## Armadilhas conhecidas desta stack

- Filament cacheia navegação/permissões em alguns cenários — mudanças em Policy nem sempre refletem sem `php artisan optimize:clear` em ambiente de desenvolvimento.
- Livewire mantém estado de componente entre requests via snapshot — nunca confiar em dado de autorização vindo do snapshot do client sem revalidar server-side.
