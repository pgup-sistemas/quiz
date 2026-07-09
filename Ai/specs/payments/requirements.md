# Requirements — Integração de Pagamentos EFI Bank (PageQuiz)
> **Data:** 2026-07-09 | **Status:** Rascunho inicial

## Contexto

O plano Pro do PageQuiz era ativado manualmente pelo super-admin. Com a integração EFI Bank, a empresa pode assinar o Pro de forma self-service por cartão de crédito, PIX, cartão recorrente, link de pagamento ou assinatura recorrente. O super-admin ainda pode ativar Pro manualmente (isenção, parceria, etc.).

**Preço do Pro:** configurável pelo super-admin via `system_settings.pro_price_monthly` (padrão definido na primeira configuração). Suporte a cobranças mensais (recorrentes) e únicas (avulsas).

---

## Histórias de usuário

### US-P1: Assinar Pro por PIX
Como admin de empresa Free, eu quero pagar o plano Pro via PIX para ativar imediatamente após confirmação do pagamento.

**Critérios de aceite:**
- [REQ-P1] O sistema DEVE gerar um QR Code PIX com valor do Pro mensal + vencimento de 30 minutos.
- [REQ-P2] QUANDO o PIX for confirmado via webhook EFI, o sistema DEVE atualizar `companies.plan='pro', status='active'` automaticamente sem intervenção do super-admin.
- [REQ-P3] O sistema DEVE exibir status de pagamento em tempo real (aguardando → pago → ativado).
- [REQ-P4] SE o PIX não for pago em 30 minutos, ENTÃO o pedido expira e o QR Code fica inválido.

### US-P2: Assinar Pro por cartão de crédito (cobrança única)
Como admin de empresa Free, eu quero pagar o Pro com cartão de crédito para ativar imediatamente.

**Critérios de aceite:**
- [REQ-P5] O sistema DEVE tokenizar o cartão via SDK EFI (dados nunca passam pelo servidor PageQuiz — apenas o token).
- [REQ-P6] QUANDO a cobrança for aprovada, o sistema DEVE ativar o Pro automaticamente.
- [REQ-P7] SE a cobrança for recusada, o sistema DEVE exibir o motivo e permitir nova tentativa.

### US-P3: Assinar Pro com cartão recorrente (assinatura)
Como admin de empresa Free, eu quero assinar o Pro com débito automático mensal no cartão.

**Critérios de aceite:**
- [REQ-P8] O sistema DEVE criar uma assinatura (subscription) EFI com recorrência mensal.
- [REQ-P9] QUANDO a primeira parcela for aprovada, o Pro é ativado imediatamente.
- [REQ-P10] QUANDO a EFI cobrar automaticamente no mês seguinte, o Pro permanece ativo.
- [REQ-P11] SE uma cobrança recorrente falhar (cartão expirado, limite), o sistema DEVE notificar o admin via banner no painel e definir prazo de regularização (7 dias).
- [REQ-P12] SE o prazo de regularização expirar sem pagamento, o Pro é rebaixado para Free automaticamente.
- [REQ-P13] O admin DEVE poder cancelar a assinatura a qualquer momento pelo painel.

### US-P4: Pagar via link de pagamento
Como super-admin, eu quero gerar um link de pagamento EFI para enviar ao cliente sem ele passar pelo checkout do PageQuiz.

**Critérios de aceite:**
- [REQ-P14] O super-admin DEVE poder gerar um link de pagamento EFI para qualquer empresa, com valor customizável.
- [REQ-P15] O link deve aceitar PIX e cartão.
- [REQ-P16] QUANDO o pagamento do link for confirmado, o Pro da empresa é ativado automaticamente.

### US-P5: Histórico e gestão de pagamentos
Como admin de empresa, eu quero ver meu histórico de pagamentos e status da assinatura.

**Critérios de aceite:**
- [REQ-P17] O painel admin DEVE exibir: plano atual, próxima cobrança (se assinatura), método de pagamento, histórico de faturas.
- [REQ-P18] O super-admin DEVE ver o histórico de pagamentos de todas as empresas com status (pago, pendente, falhou, reembolsado).

### US-P6: Webhooks e confiabilidade
Como sistema, eu devo processar eventos de pagamento EFI de forma idempotente e auditável.

**Critérios de aceite:**
- [REQ-P19] O endpoint de webhook (`/payments/webhook.php`) DEVE validar a notificação EFI antes de processar.
- [REQ-P20] Toda notificação webhook DEVE ser registrada em `payment_events` antes de processar, garantindo idempotência.
- [REQ-P21] SE o webhook falhar ao processar, o sistema DEVE salvar o evento como `failed` para reprocessamento manual pelo super-admin.

---

## Requisitos não-funcionais

- **[REQ-NFR-P1] PCI DSS:** dados de cartão nunca transitam pelo servidor PageQuiz — apenas token gerado pelo SDK EFI.
- **[REQ-NFR-P2] mTLS PIX:** certificado EFI `.p12` armazenado em `certs/` protegido por `.htaccess`, nunca no `public/`.
- **[REQ-NFR-P3] Idempotência:** webhook reprocessado duas vezes não altera o estado da empresa duas vezes.
- **[REQ-NFR-P4] Sandbox:** todas as credenciais de desenvolvimento usam ambiente sandbox EFI.
- **[REQ-NFR-P5] Configurabilidade:** preço do Pro (`pro_price_monthly`) armazenado em `system_settings`, alterável pelo super-admin sem deploy.
- **[REQ-NFR-P6] Auditoria:** todo evento de pagamento (criação, confirmação, falha, cancelamento) registrado em `payment_events` + `audit_log`.

---

## Fora de escopo (nesta fase)

- Boleto bancário
- Split de pagamento
- Emissão de NFe/NFS-e
- Parcelamento de cartão em múltiplas vezes
- App mobile de pagamento
- Reembolso automático (reembolso manual pelo super-admin via painel EFI)
