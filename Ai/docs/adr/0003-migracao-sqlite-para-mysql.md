# ADR-0003: Migração de SQLite para MySQL

**Status:** Aceita
**Data:** 2026-07-21
**Feature relacionada:** N/A (mudança de infraestrutura, não de feature de produto)

## Contexto

O projeto foi construído com SQLite (arquivo único `data/quiz.db`, PDO, sem servidor de banco separado) desde o início — decisão registrada como "fechada" na Seção 2 do `CLAUDE.md`. Essa escolha funcionou bem durante o desenvolvimento local via XAMPP, mas a hospedagem de produção contratada na Locaweb provê banco de dados como serviço gerenciado MySQL (`quiz_pageup.mysql.dbaas.com.br`), sem suporte a persistência de arquivo SQLite de forma confiável entre deploys/reinícios do processo PHP em ambiente de hospedagem compartilhada.

Além disso, o modelo de concorrência do SQLite (mesmo com `journal_mode=WAL`) é mais frágil sob múltiplos processos PHP concorrentes típicos de hospedagem compartilhada do que o modelo cliente-servidor do MySQL/InnoDB, que já resolve nativamente o caso de uso de leituras concorrentes durante o quiz ao vivo (`api/live-stats.php`).

## Decisão

Migrar toda a camada de persistência de SQLite para MySQL 5.7+ (versão confirmada no servidor da Locaweb: 5.7.32), mantendo:

- A mesma API pública de acesso a dados (`getDB()`, `dbRow()`, `dbRows()`, `dbExec()`, `dbLastId()` em `includes/db.php`) — nenhum dos ~65 arquivos que consomem essas funções precisou ser alterado além de queries com sintaxe de data específica do SQLite.
- O modelo de multi-tenancy existente (`company_id` em todas as tabelas de domínio, ver ADR-0001) sem alterações — apenas a camada de schema/tipos foi adaptada para MySQL (`AUTO_INCREMENT`, `DATETIME`, `VARCHAR`/`TEXT`, `TINYINT(1)` para booleanos, `ENGINE=InnoDB`).
- Credenciais via variáveis de ambiente, lidas por um loader mínimo próprio (`includes/env.php`, ~15 linhas, sem dependência externa) a partir de `.env` (local) / `.env.production` (produção), ambos fora do controle de versão.

Não há suporte dual SQLite/MySQL — MySQL passa a ser o único banco suportado, tanto local quanto em produção, a partir desta mudança. `data/quiz.db` deixa de ser lido pela aplicação; os dados existentes foram migrados uma única vez via `tmp/migrate_sqlite_to_mysql.php`.

## Alternativas consideradas

| Alternativa | Prós | Contras | Motivo de rejeição |
|---|---|---|---|
| Manter SQLite, sincronizar arquivo via deploy manual | Zero mudança de código | Não escala em hosting compartilhado; risco de corrupção/perda de dados entre deploys; sem acesso concorrente real | Rejeitada — incompatível com o ambiente de produção contratado |
| Suporte dual SQLite (dev) / MySQL (prod) via driver abstraído | Mantém SQLite para dev rápido sem dependência externa | Exige abstrair toda sintaxe de schema/migração para os dois motores — dobra a superfície de teste e risco de divergência de comportamento entre ambientes | Rejeitada a pedido explícito — preferência por um único motor, menor risco de bug de compatibilidade dupla |
| PostgreSQL | Tipagem mais rica, melhor para futuro crescimento | Locaweb dbaas.com.br já provê MySQL gerenciado; trocar exigiria contratar outro serviço | Rejeitada — MySQL já está disponível e provisionado |

## Consequências

**Positivas:**
- Concorrência real (MVCC do InnoDB) para o cenário de quiz ao vivo, sem depender de `PRAGMA journal_mode=WAL`.
- Compatível nativamente com o modelo de hospedagem da Locaweb.
- Todas as migrações de schema "recriar tabela" que o SQLite exigia (para adicionar `UNIQUE` ou remover `NOT NULL`) viram `ALTER TABLE` diretos no MySQL — schema mais simples de evoluir no futuro.

**Negativas / débito assumido:**
- `includes/db.php` teve toda sua lógica de `initDB()` reescrita — o histórico incremental de migrações SQLite (adicionar coluna X, depois Y, depois recriar tabela Z) foi colapsado num schema MySQL final único, já que a base MySQL nasceu vazia. Não há mais rastro incremental de "como o schema evoluiu" no código — só o estado final. Aceito porque o dado histórico real já foi migrado com sucesso (contagens de linha validadas tabela a tabela).
- Coluna `key` da tabela `system_settings` é palavra reservada no MySQL — todas as ocorrências em SQL precisaram de escape com crase (`` `key` ``). Um novo uso futuro dessa coluna sem escape vai falhar high-visibility (erro de sintaxe SQL), não silenciosamente.
- Dois bugs pré-existentes foram descobertos e corrigidos durante a migração (não relacionados à troca de banco em si, já quebrados no SQLite): `superadmin/users.php` referenciava uma coluna inexistente `finished_at` em duas queries (corrigido para `completed_at`).
- Local (XAMPP) e produção usam o **mesmo banco MySQL remoto da Locaweb** temporariamente — a senha do root do MySQL local (porta 3306) não estava disponível no momento da migração. Isso é um débito técnico: qualquer teste local escreve no banco de produção. Ação de acompanhamento: isolar um MySQL local (XAMPP ou standalone) assim que a credencial do root estiver disponível, e apontar `.env` para ele.

**Impacto em CLAUDE.md:** Sim — Seção 2 ("Stack") precisa ser atualizada: "Banco de dados: SQLite via PDO" → "Banco de dados: MySQL 8 via PDO — credenciais via `.env`/`.env.production` (`includes/env.php`), schema em `includes/db.php → initDB()`". Seção 5 (Segurança) também deve remover a menção a "`data/quiz.db` protegido por `.htaccess`" como proteção ativa, já que o arquivo não é mais a fonte de dados em produção.
