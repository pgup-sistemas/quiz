# Certificados EFI Bank

Coloque aqui o certificado `.p12` fornecido pelo EFI Bank para autenticação mTLS do PIX.

## Arquivos esperados

- `efi-sandbox.p12` — certificado de homologação (sandbox)
- `efi-producao.p12` — certificado de produção

## Como obter

1. Acesse o painel EFI Bank → API → Certificados
2. Gere o certificado para "Pix" (produção ou homologação)
3. Baixe o arquivo `.p12` e coloque nesta pasta
4. Configure o caminho em **Super Admin → Configurações → Credenciais EFI**

## Segurança

Este diretório tem `.htaccess` bloqueando qualquer acesso HTTP direto.
O arquivo `.p12` está no `.gitignore` — NUNCA commite certificados.
