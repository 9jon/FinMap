# FinMap

![PHP](https://img.shields.io/badge/PHP-777BB4?style=flat&logo=php&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-4479A1?style=flat&logo=mysql&logoColor=white)
![JavaScript](https://img.shields.io/badge/JavaScript-F7DF1E?style=flat&logo=javascript&logoColor=black)
![Licença MIT](https://img.shields.io/badge/licença-MIT-green.svg)

Aplicação web de **gestão financeira pessoal**: o usuário registra ou importa suas movimentações, acompanha orçamento e metas, e recebe alertas e insights para antecipar problemas e economizar.

Projeto Integrado do curso **Técnico em Informática do Senac**, desenvolvido ao longo de um ano de curso e apresentado ao final do período.

---

## Funcionalidades

**Conta e acesso**
- Cadastro e login de usuários, com login pelo Google (OAuth 2.0)
- Recuperação de senha por e-mail (PHPMailer)
- Perfil com foto, preferências e configurações da conta
- Onboarding inicial, com configuração de renda

**Controle financeiro**
- Dashboard com visão geral das movimentações
- Registro de transações e categorias padrão por usuário
- Orçamento mensal e metas financeiras
- Notificações dentro da plataforma

**Importação de dados**
- Importação de extratos nos formatos **CSV, OFX/QFX e XLSX**
- Etapa de revisão e aprovação dos lançamentos antes de salvar
- Aprendizado a partir das revisões anteriores do usuário (regras de revisão)

**Análises e inteligência**
- **Alertas preditivos:** avisos quando o mês começa a sair da faixa ideal, detecção de desvios por categoria e simulação de como uma redução de gastos mudaria o cenário
- **Poupança invisível:** estimativa da economia potencial, a partir da descrição, da categoria e do valor de cada lançamento
- **Insights semanais** gerados a partir das movimentações
- **Assistente de chat com IA**, integrado ao [Ollama](https://ollama.com) (modelo configurável)

## Tecnologias

| Camada | Tecnologia |
|---|---|
| Back-end | PHP |
| Banco de dados | MySQL (extensão `mysqli`) |
| Front-end | HTML, CSS, JavaScript e Bootstrap 5 |
| E-mail | PHPMailer (via Composer) |
| Autenticação social | Google OAuth 2.0 |
| IA | Ollama (modelo local, configurável) |
| Versionamento | Git e GitHub |

## Estrutura do projeto

```text
FinMap/
├── assets/              # CSS, JavaScript e imagens
├── components/          # componentes reutilizáveis (topbar, navegação, assistente de IA...)
├── config/              # conexão com o banco, e-mail, OAuth do Google e IA
├── database/migrations/ # migrations SQL do banco
├── includes/            # regras de negócio (importador, analisador de poupança, insights...)
├── login/               # cadastro, login, logout, recuperação de senha e login com Google
├── pages/               # páginas e endpoints da aplicação
├── tests/               # testes em PHP
├── uploads/perfis/      # fotos de perfil enviadas pelos usuários
├── composer.json        # dependências PHP
└── index.php            # página inicial
```

## Banco de dados

O banco é MySQL e se chama `finmap`. A pasta `database/migrations` guarda as alterações de estrutura em ordem cronológica, entre elas:

| Tabela | Finalidade |
|---|---|
| `usuarios` | dados da conta, foto de perfil e telefone |
| `transacoes` | movimentações financeiras |
| `importacoes` | histórico das importações de extratos |
| `preferencias_usuario` | preferências e configurações de cada usuário |
| `assistente_conversas` | conversas com o assistente de IA |
| `assistente_mensagens` | mensagens de cada conversa |

## Como executar

### Pré-requisitos

- PHP 8.1 ou superior, com as extensões `mysqli` e `curl`
- Composer
- MySQL
- Servidor local, como o Apache do XAMPP
- (Opcional) Ollama, para o assistente de IA

### Passo a passo

```bash
# 1. Clonar o repositório dentro da pasta do servidor local (ex.: htdocs do XAMPP)
git clone https://github.com/9jon/FinMap.git
cd FinMap

# 2. Instalar as dependências PHP
composer install
```

3. **Banco de dados:** crie um banco chamado `finmap` no MySQL, importe a estrutura base (`database/schema.sql`) e execute os arquivos de `database/migrations` em ordem de data.
4. **Conexão:** confira usuário e senha do MySQL em `config/conn.php`. O padrão é `localhost`, usuário `root` e senha vazia.
5. **E-mail (opcional):** informe suas credenciais SMTP em `config/email.php` para habilitar a recuperação de senha.
6. **Login com Google (opcional):** copie `config/oauth-google.example.php` para `config/oauth-google.local.php` e preencha com as credenciais do seu projeto no Google Cloud. Também é possível usar variáveis de ambiente.
7. **Assistente de IA (opcional):** copie `config/ai.example.php` para `config/ai.local.php`. Por padrão ele usa o Ollama em `http://127.0.0.1:11434` com o modelo `qwen3:4b-instruct`, ajustáveis pelas variáveis `FINMAP_OLLAMA_URL` e `FINMAP_OLLAMA_MODEL`.
8. Inicie o Apache e o MySQL e acesse `http://localhost/FinMap`.

> **Segurança:** nunca envie senhas ou chaves para o repositório. Os arquivos `*.local.php` já estão no `.gitignore`.

## Testes

Os testes ficam em `tests/` e são scripts PHP executados pela linha de comando:

```bash
php tests/autenticacao.php
php tests/importacao-csv.php
php tests/importacao-aprovacao.php
php tests/periodos-financeiros.php
php tests/oauth-google.php
```

Cobrem autenticação, leitura e aprovação de importações, cálculo de períodos financeiros e configuração do login com Google. Parte deles precisa do banco de dados configurado.

## Destaques técnicos

- Modelagem relacional e evolução do banco por **migrations** versionadas
- Autenticação completa: cadastro, login, recuperação de senha por e-mail e **OAuth 2.0 com Google**
- Leitura e tratamento de arquivos **CSV, OFX/QFX e XLSX**, com detecção de separador, fluxo de revisão e aprovação
- Regras de negócio separadas das páginas (`includes/`) e **testes automatizados** em PHP
- Integração com um modelo de linguagem local (Ollama) para o assistente de chat
- Organização do código em componentes reutilizáveis e controle de versão com Git

## Autor

**Jonathan Marques**
Estudante de Técnico em Informática no Senac
GitHub: [github.com/9jon](https://github.com/9jon)

## Licença

Distribuído sob a licença MIT. Consulte o arquivo [LICENSE](LICENSE) para mais detalhes.
