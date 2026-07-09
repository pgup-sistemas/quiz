# ADR-0002 — Adição de Composer para SDK EFI Bank

**Data:** 2026-07-09
**Status:** Aceito
**Decisores:** Oézios Normando (PageUp Sistemas)

---

## Contexto

O PageQuiz usa PHP 8.2 vanilla sem Composer. Para integrar pagamentos EFI Bank (PIX, cartão de crédito, assinaturas, link de pagamento), o SDK oficial `efipay/sdk-php-apis-efi` é a opção mais segura pois:
- Gerencia autenticação OAuth2 automaticamente
- Cuida do mTLS com certificado `.p12` exigido pelo PIX (Banco Central)
- Mantido pela EFI Bank com atualizações de segurança

## Opções consideradas

### Opção A — Composer + SDK oficial *(escolhida)*
- `composer require efipay/sdk-php-apis-efi`
- `vendor/autoload.php` incluído apenas em arquivos de pagamento
- Nenhum impacto nos arquivos PHP existentes (não exige namespace nem autoload próprio)

### Opção B — cURL puro sem Composer
- Implementar OAuth2 token refresh manualmente
- Gerenciar mTLS com `CURLOPT_SSLCERT` / `CURLOPT_SSLKEY` diretamente
- Reescrever todos os endpoints EFI manualmente
- Alto risco de erros de segurança e compatibilidade futura

## Decisão

**Opção A.** O Composer é adicionado *exclusivamente* para o SDK EFI. O código PHP próprio continua sem namespace/autoload próprio — apenas `require_once __DIR__ . '/../vendor/autoload.php'` nos arquivos de pagamento.

## Consequências

- `vendor/` adicionado ao `.gitignore`
- `composer.json` e `composer.lock` versionados no repositório
- Certificado EFI (`.p12`) armazenado em `certs/efi.p12`, protegido por `.htaccess`
- Credenciais EFI em `includes/config.php` via constantes (não hardcoded)
- Deploy: `composer install --no-dev` deve ser executado no servidor após pull
