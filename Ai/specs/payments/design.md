# Design — Integração de Pagamentos EFI Bank (PageQuiz)
> **Data:** 2026-07-09

## Modelo de dados

### Tabela nova: `subscriptions`

| Coluna | Tipo | Notas |
|---|---|---|
| `id` | INTEGER PK | |
| `company_id` | INTEGER NOT NULL | FK companies |
| `efi_subscription_id` | TEXT | ID da assinatura EFI (planos recorrentes) |
| `efi_charge_id` | TEXT | ID da cobrança avulsa |
| `type` | TEXT | `pix` \| `card_once` \| `card_recurring` \| `payment_link` \| `manual` |
| `status` | TEXT | `pending` \| `paid` \| `active` \| `overdue` \| `cancelled` \| `expired` |
| `amount` | INTEGER | Valor em centavos |
| `next_billing_at` | TEXT | Próxima cobrança (assinatura) |
| `grace_until` | TEXT | Prazo de regularização em caso de falha |
| `pix_txid` | TEXT | txid do PIX |
| `pix_qrcode` | TEXT | QR Code base64 |
| `pix_copiaecola` | TEXT | Texto "copia e cola" |
| `payment_link_url` | TEXT | URL do link de pagamento EFI |
| `created_at` | TEXT | |
| `updated_at` | TEXT | |

### Tabela nova: `payment_events`

| Coluna | Tipo | Notas |
|---|---|---|
| `id` | INTEGER PK | |
| `company_id` | INTEGER | |
| `subscription_id` | INTEGER | FK subscriptions |
| `efi_notification_id` | TEXT UNIQUE | Garante idempotência |
| `event_type` | TEXT | `pix_paid` \| `charge_paid` \| `charge_failed` \| `subscription_renewed` \| `subscription_cancelled` |
| `raw_payload` | TEXT | JSON raw do webhook EFI |
| `processed` | INTEGER DEFAULT 0 | 0=pendente, 1=processado, 2=falhou |
| `created_at` | TEXT | |

### Adições em `system_settings`

```
('pro_price_monthly',  '4990',   'Preço mensal do Pro em centavos (ex: 4990 = R$49,90)')
('efi_client_id',      '',       'Client ID EFI Bank')
('efi_client_secret',  '',       'Client Secret EFI Bank')
('efi_sandbox',        '1',      '1=sandbox, 0=producao')
('efi_pix_key',        '',       'Chave PIX (e-mail, CPF, CNPJ, aleatória)')
('efi_cert_path',      'certs/efi-sandbox.p12',  'Caminho do certificado .p12 relativo à raiz do projeto')
('efi_cert_password',  '',       'Senha do certificado .p12 (se houver)')
```

---

## Arquitetura

### `includes/efi.php` — Wrapper do SDK EFI

```php
// Funções disponíveis:
efiClient(): EfiPay                              // instância configurada (sandbox/prod)
efiCreatePixCharge(int $cents, string $txid, string $desc, int $expiresIn = 1800): array
efiGetPixCharge(string $txid): array
efiCreateCardCharge(int $cents, string $token, array $customer): array
efiCreateSubscription(int $cents, string $token, array $customer): array
efiCancelSubscription(string $subscriptionId): bool
efiCreatePaymentLink(int $cents, string $desc, int $companyId): array
efiValidateWebhook(string $payload): bool
```

### Fluxo PIX

```
Admin clica "Pagar com PIX"
    ↓
POST /payments/checkout.php {method=pix}
    ↓
efiCreatePixCharge() → txid + QRCode + CopiaECola
    ↓
INSERT subscriptions (type=pix, status=pending, pix_*)
    ↓
Exibir QRCode na página (polling a cada 3s para status)
    ↓
EFI detecta pagamento → POST /payments/webhook.php
    ↓
payment_events INSERT (idempotente)
    ↓
UPDATE subscriptions status=paid
    ↓
UPDATE companies plan=pro, status=active
    ↓
INSERT audit_log (action=payment_confirmed)
    ↓
Admin recarrega → banner "Pro ativado!"
```

### Fluxo Cartão Único

```
Admin preenche dados do cartão no form
    ↓
JS SDK EFI tokeniza cartão (dados NUNCA chegam ao PHP)
    ↓
POST /payments/checkout.php {method=card_once, token=...}
    ↓
efiCreateCardCharge(token) → charge_id + status
    ↓
Se aprovado → UPDATE companies pro/active + INSERT subscriptions
Se recusado → exibir motivo da recusa
```

### Fluxo Assinatura Recorrente

```
Admin tokeniza cartão via JS SDK EFI
    ↓
POST /payments/checkout.php {method=card_recurring, token=...}
    ↓
efiCreateSubscription() → subscription_id + primeiro pagamento
    ↓
INSERT subscriptions (type=card_recurring, efi_subscription_id, next_billing_at)
    ↓
UPDATE companies pro/active
    ↓
EFI cobra mensalmente → webhook subscription_renewed
    ↓
UPDATE subscriptions next_billing_at + INSERT payment_events
    ↓
Se falha → banner no painel + grace_until = +7 dias
    ↓
Se 7 dias sem pagar → downgrade Free automático
```

### Fluxo Link de Pagamento

```
Super-admin acessa superadmin/company-edit.php
    ↓
Clica "Gerar Link de Pagamento"
    ↓
POST /superadmin/payment-link.php {company_id, amount_cents}
    ↓
efiCreatePaymentLink() → URL
    ↓
INSERT subscriptions (type=payment_link, payment_link_url)
    ↓
Exibir URL + copiar para enviar ao cliente
    ↓
Webhook confirma → Pro ativado automaticamente
```

---

## Arquivos novos

| Arquivo | Responsabilidade |
|---|---|
| `composer.json` | Dependência `efipay/sdk-php-apis-efi` |
| `includes/efi.php` | Wrapper do SDK EFI Bank |
| `certs/.htaccess` | Bloquear acesso direto aos certificados |
| `certs/README.md` | Instruções para colocar o .p12 |
| `payments/checkout.php` | Página de checkout (PIX/cartão/link) |
| `payments/webhook.php` | Endpoint de notificações EFI (POST) |
| `payments/status.php` | Polling de status de pagamento (JSON) |
| `payments/cancel.php` | Cancelar assinatura |
| `admin/billing.php` | Histórico de faturas + status assinatura |
| `superadmin/payment-link.php` | Gerar link de pagamento para empresa |
| `superadmin/payments.php` | Histórico global de pagamentos |

## Arquivos modificados

| Arquivo | Mudança |
|---|---|
| `includes/db.php` | Novas tabelas `subscriptions`, `payment_events`; seeds em `system_settings` |
| `includes/config.php` | Constantes EFI (lidas de `system_settings`) |
| `superadmin/settings.php` | Configuração de credenciais EFI + preço Pro |
| `superadmin/company-edit.php` | Botão "Gerar Link de Pagamento" |
| `admin/upgrade.php` | Checkout integrado (PIX + cartão + assinatura) |
| `admin/layout.php` | Banner de inadimplência + prazo |
| `.htaccess` | Bloquear `payments/webhook.php` para GETs (apenas POST) |
| `.gitignore` | `vendor/`, `certs/*.p12`, `certs/*.pem` |

---

## Segurança

- Certificado `.p12` em `certs/` com `Deny from all` no `.htaccess`
- Credenciais EFI nunca em código — apenas em `system_settings` (banco SQLite protegido)
- Token de cartão gerado pelo JS SDK EFI — PHP nunca toca o número do cartão
- Webhook: validar IP de origem EFI Bank (range documentado na API)
- `payment_events.efi_notification_id` UNIQUE garante idempotência
