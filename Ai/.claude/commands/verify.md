---
description: Valida cada critério de aceite EARS de requirements.md contra o código efetivamente implementado, produzindo relatório de conformidade.
---

Você vai auditar se a implementação de uma feature satisfaz sua especificação original.

Argumento do usuário (slug da feature): $ARGUMENTS

Passos:

1. Leia `specs/<slug>/requirements.md` (critérios de aceite EARS) e o código real implementado (não confie em `tasks.md` marcado como concluído — verifique o código).

2. Para cada critério de aceite EARS, verifique:
   - Existe implementação que satisfaz o critério? Onde (arquivo/linha)?
   - Existe teste automatizado cobrindo especificamente esse critério? Qual?
   - O comportamento observado no código diverge do critério de alguma forma (mesmo que sutil, ex.: validação client-side sem validação server-side equivalente)?

3. Produza relatório em tabela, usando OBRIGATORIAMENTE um destes 4 status por critério (nunca omitir status):

   | Critério EARS | Status | Evidência (arquivo/teste) | Observação |
   |---|---|---|---|
   | REQ-1 | `ATENDIDO` / `PARCIAL` / `NÃO ATENDIDO` / `DIVERGÊNCIA` | | |

   Use `DIVERGÊNCIA` (não `PARCIAL`) quando o código implementa comportamento diferente do que o critério descreve — não apenas incompleto, mas contraditório. Isso é distinto de `NÃO ATENDIDO` (não implementado) e exige destaque separado porque significa que a spec está desatualizada ou o código foi alterado sem passar pelo fluxo `/specify → /plan → /implement`.

4. **Se houver qualquer item com status `DIVERGÊNCIA`, o relatório é bloqueante:** abra a resposta com um alerta explícito, antes da tabela, no formato:

   > ⚠️ **DIVERGÊNCIA DETECTADA:** N critério(s) de `specs/<slug>/requirements.md` não correspondem ao código atual. A spec está desatualizada em relação à implementação real, ou a implementação desviou da spec sem registro. Ação recomendada: rode `/adr` se o desvio foi uma decisão válida a posteriori, ou corrija o código para aderir à spec se o desvio foi não intencional.

   Não subordine este alerta ao final do relatório — ele precisa aparecer antes de qualquer outra informação, para não ser perdido em relatórios longos.

4. Sinalize separadamente:
   - Critérios sem nenhum teste automatizado associado (risco de regressão silenciosa).
   - Comportamento implementado que não corresponde a nenhum critério do requirements.md (scope creep não documentado — deve ser retroativamente incorporado à spec ou removido).

5. Não corrija automaticamente os gaps encontrados nesta fase — reporte para decisão humana sobre priorização, a menos que explicitamente instruído a corrigir.
