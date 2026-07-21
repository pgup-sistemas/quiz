# Page Quiz · Sistema de Quiz Interativo

## Visão Geral

Plataforma completa de questionários para empresas, Rh, palestras e testes de treinamentos em geral, desenvolvida em **PHP + SQLite**.
Setores podem criar quizzes interativos para apresentações, treinamentos e auditorias (ONA).

---

## Requisitos do Servidor

| Requisito     | Versão Mínima |
|---------------|---------------|
| PHP           | 7.4+          |
| SQLite        | 3.x (via PDO) |
| Servidor Web  | Apache / Nginx|

### Extensões PHP necessárias
- `pdo_sqlite`
- `fileinfo`
- `mbstring`

---

## Instalação

### 1. Upload dos arquivos

Envie todos os arquivos para a pasta desejada no servidor. Ex.: `/var/www/html/quiz/`

### 2. Permissões

O servidor web precisa ter permissão de **escrita** na pasta `data/`:

```bash
chmod 775 data/
chown www-data:www-data data/
```

### 3. Banco de Dados

O banco SQLite é **criado automaticamente** na primeira visita ao site. Nenhuma configuração de banco necessária.

### 4. Acesso

- **Site público:** `https://quiz.alphaclinmais.net.br`
- **Painel admin:** `https://quiz.alphaclinmais.net.br/admin/`

### 5. Credenciais padrão

- **Usuário:** `admin`
- **Senha:** `alphaclin2025`

> ⚠️ **Altere a senha imediatamente após o primeiro acesso!**
> Acesse Admin > ⚙️ Configurações > Alterar Senha

---

## Estrutura de Arquivos

```
alphaclin-quiz/
├── index.php              ← Página pública (lista de quizzes)
├── quiz.php               ← Página do quiz (participante)
├── .htaccess              ← Configuração Apache
│
├── api/
│   ├── quiz.php           ← API: carrega questões
│   └── result.php         ← API: salva resultado
│
├── admin/
│   ├── index.php          ← Dashboard
│   ├── login.php          ← Login admin
│   ├── quizzes.php        ← Gerenciar quizzes
│   ├── quiz-edit.php      ← Criar/editar quiz e questões
│   ├── results.php        ← Ver resultados + exportar CSV
│   ├── participant.php    ← Detalhes de um participante
│   ├── import.php         ← Importar questões via CSV
│   ├── csv-template.php   ← Download do modelo CSV
│   ├── settings.php       ← Configurações e usuários admin
│   └── layout.php         ← Layout compartilhado
│
├── includes/
│   ├── config.php         ← Constantes e configurações
│   ├── db.php             ← Banco de dados (SQLite + seed)
│   └── auth.php           ← Autenticação de sessão
│
├── assets/
│   ├── style.css          ← CSS global
│   ├── logo.svg           ← Logo PageQuiz colorida
│   ├── logo-white.svg     ← Logo PageQuiz branca
│   └── fonts/
│       ├── SancoaleBold.otf
│       ├── SancoaleMedium.otf
│       └── SancoaleRegular.otf
│
└── data/
    ├── quiz.db            ← Banco SQLite (gerado automaticamente)
    └── .htaccess          ← Protege o banco de acesso direto
```

---

## Como Usar

### Criar um Quiz (Admin)

1. Acesse `admin/` e faça login
2. Clique em **+ Novo Quiz**
3. Preencha título, setor, tempo por questão e % de aprovação
4. Salve e clique em **➕ Adicionar Questão** para criar questões
5. Ou use **📥 Importar CSV** para cadastrar em lote

### Importar Questões por CSV

Baixe o modelo em **Admin > Quiz > Importar CSV > Baixar Modelo**.

Formato das colunas (separador `;`):

| Col | Campo          | Obrigatório |
|-----|----------------|-------------|
| 1   | Pergunta       | ✅ Sim      |
| 2   | Categoria      | Não         |
| 3   | Opção A        | ✅ Sim      |
| 4   | Opção B        | ✅ Sim      |
| 5   | Opção C        | Não         |
| 6   | Opção D        | Não         |
| 7   | Correta (A/B/C/D ou 0/1/2/3) | ✅ Sim |
| 8   | Explicação     | Não         |

### Exportar Resultados

Em **Admin > Resultados**, use filtros e clique em **⬇ Exportar CSV** para baixar um relatório completo com todos os participantes.

---

## Funcionalidades

### Para Participantes
- ✅ Identificação (nome, setor, e-mail)
- ✅ Quiz cronometrado com timer visual
- ✅ Feedback imediato com explicação
- ✅ Revisão completa das respostas
- ✅ Certificado de conclusão (para aprovados)
- ✅ Impressão do certificado em PDF

### Para Administradores
- ✅ Dashboard com estatísticas em tempo real
- ✅ Criar e gerenciar múltiplos quizzes
- ✅ Configurar timer, aprovação, feedback, randomização
- ✅ Cadastro de questões com até 4 opções
- ✅ Importação de questões via CSV
- ✅ Visualizar resultados detalhados por participante
- ✅ Filtrar e exportar resultados em CSV
- ✅ Gerenciar múltiplos usuários admin
- ✅ Ativar/desativar quizzes

---

## Configuração Avançada

Edite `includes/config.php` para alterar:

```php
define('DEFAULT_TIMER',    30);   // segundos por questão
define('DEFAULT_PASS_PCT', 70);   // % mínima para aprovação
```

---

## Segurança

- Senhas armazenadas com `password_hash()` (bcrypt)
- Banco SQLite protegido via `.htaccess`
- Sessões PHP com nome customizado
- Dados de entrada sanitizados via `htmlspecialchars()`

---

## Suporte

Desenvolvido por PageUp Sistemas  
Sistema versão 1.0.0
