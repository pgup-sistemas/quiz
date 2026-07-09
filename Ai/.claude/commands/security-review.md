---
description: Checklist de revisão de segurança para uma feature, focado em dado sensível, autorização e dependências novas. Não substitui pentest ou revisão de segurança formal em domínio de alta criticidade — é gate mínimo de engenharia.
---

Você vai auditar uma feature implementada sob a ótica de segurança, antes de considerá-la pronta para merge.

Argumento do usuário (slug da feature): $ARGUMENTS

Passos:

1. Leia `CLAUDE.md` Seção 4 (classificação de dado sensível do projeto) e `specs/<slug>/requirements.md`/`design.md`.

2. **Autorização:** para cada rota/endpoint/action introduzido pela feature que muta estado ou lê dado sensível, confirme que existe Policy/Guard explícita — não middleware de autenticação genérico. Liste qualquer rota sem essa cobertura como achado bloqueante.

3. **Exposição de dado sensível:**
   - Campos classificados como sensíveis em `CLAUDE.md`/`docs/domain-model.md` aparecem em algum log, mensagem de erro, ou payload de API sem necessidade explícita?
   - Existe mascaramento/redação onde aplicável (ex.: exibição parcial de documento de identificação)?

4. **Validação de entrada:** toda entrada de usuário nesta feature é validada server-side, independentemente de validação client-side já existir? Sinalize qualquer campo que dependa só de validação client-side.

5. **Dependências novas:** se a feature introduziu biblioteca/pacote novo, rode a ferramenta de auditoria de vulnerabilidade da stack (`composer audit`, `npm audit`, ou equivalente) e reporte qualquer CVE de severidade alta/crítica.

6. **Segredos:** confirme que nenhuma credencial, chave de API, ou token foi commitado em código, migration, seed, ou nos próprios arquivos de spec desta feature.

7. **Idempotência e replay (se a feature envolve job assíncrono ou webhook):** confirme que execução duplicada não causa efeito colateral indevido (double-charge, notificação duplicada, mudança de estado inconsistente).

8. Produza relatório:

   | Item | Status | Evidência | Severidade se falho |
   |---|---|---|---|

   Itens com status `FALHO` e severidade Alta/Crítica bloqueiam o merge — reporte isso explicitamente, não apenas como observação.

9. Este checklist é gate mínimo de engenharia, não substitui revisão de segurança formal/pentest exigida por regulação setorial aplicável ao domínio do projeto (definir em `CLAUDE.md` se este projeto exige processo adicional).
