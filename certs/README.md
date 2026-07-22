# Certificados EFI Bank

Coloque aqui o certificado `.p12` fornecido pelo EFI Bank para autenticação da API (PIX, cartão e assinaturas).

## Arquivos esperados

- `efi-sandbox.p12` — certificado de homologação (sandbox)
- `producao-573055-pagequiz.p12` — certificado de produção (nome exato precisa bater com o campo
  `efi_cert_path` em **Super Admin → Configurações → Credenciais EFI**)

## Como obter

1. Acesse o painel EFI Bank → API → Certificados
2. Gere o certificado para "Pix" (produção ou homologação)
3. Baixe o arquivo `.p12` e coloque nesta pasta
4. Configure o caminho em **Super Admin → Configurações → Credenciais EFI** — o caminho deve
   apontar exatamente para o nome do arquivo (cuidado com extensão duplicada `.p12.p12` ao
   renomear o download do navegador)

## Segurança

Este diretório tem `.htaccess` bloqueando qualquer acesso HTTP direto.
O arquivo `.p12` está no `.gitignore` — NUNCA commite certificados.

## Webhook — mTLS obrigatório (configuração de servidor, fora do código PHP)

A EFI exige **mTLS mútuo** para entregar notificações de webhook (ver
https://dev.efipay.com.br/docs/api-pix/webhooks): ela faz uma primeira tentativa **sem**
certificado (o servidor deve rejeitar) e uma segunda **com** o certificado público dela (o
servidor deve aceitar). Isso é configurado no **Apache**, não no PHP — o
`payments/webhook.php` já filtra por IP de origem (`34.193.116.226`) como camada extra, mas
isso não substitui o mTLS.

### Passos (exigem acesso root/vhost — geralmente via suporte da hospedagem, ex.: Locaweb)

1. Baixe o certificado público da EFI:
   - Produção: `https://certificados.efipay.com.br/webhooks/certificate-chain-prod.crt`
   - Homologação: `https://certificados.efipay.com.br/webhooks/certificate-chain-homolog.crt`
2. Salve o arquivo no servidor, fora do webroot (ex.: `/etc/ssl/efi/certificate-chain-prod.crt`).
3. No VirtualHost (SSL) do domínio `quiz.pageup.net.br`, adicione:
   ```apache
   SSLCACertificateFile /etc/ssl/efi/certificate-chain-prod.crt
   SSLVerifyClient       optional
   SSLVerifyDepth        2
   ```
   `optional` é importante — o site continua funcionando normalmente para todo o resto do
   tráfego (usuários comuns não têm certificado de cliente); só a EFI vai apresentar um.
4. Exija TLS 1.2+ (`SSLProtocol all -SSLv3 -TLSv1 -TLSv1.1`), conforme mínimo exigido pela EFI.
5. Reinicie o Apache e teste o registro do webhook no painel EFI — ela envia uma notificação
   de teste na hora do cadastro.

### Ao cadastrar a URL no painel da EFI

Use o parâmetro `?ignorar=` para desativar o sufixo automático `/pix` que a EFI adiciona nas
notificações reais (nosso endpoint é um único arquivo PHP, sem roteamento de sub-caminho):

```
https://quiz.pageup.net.br/payments/webhook.php?ignorar=
```
