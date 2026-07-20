# Documento de Reconstrução - 2026-05-23

## Objetivo

Registrar o que foi feito até o momento na reconstrução do sistema de votação/pauta, substituindo a base antiga por uma estrutura nova, mais limpa e preparada para evoluir no estilo do SAPL.

## O Que Foi Removido

- MVC antigo completo em `app/Core`, `app/Helpers`, `app/Modules` e `app/Views` antigos
- rotinas antigas de votação, logs, histórico e admin
- arquivos antigos de `database`
- scripts antigos de saneamento
- assets de JavaScript legados e uploads antigos

## O Que Foi Criado

### Base da aplicação

- `app/bootstrap.php`
- `app/Config/config.php` simplificado
- `app/Core/DB.php`
- `app/Core/Auth.php`
- `app/Core/Router.php`
- `app/Core/View.php`
- `app/Helpers/functions.php`

### Estrutura de autenticação e navegação

- `app/Controllers/AuthController.php`
- `app/Controllers/DashboardController.php`
- `app/Controllers/HomeController.php`
- `app/Controllers/PublicoController.php`
- `app/Controllers/SessaoController.php`
- `app/Controllers/PautaController.php`
- `app/Controllers/PresidenciaController.php`

### Modelos

- `app/Models/User.php`
- `app/Models/Sessao.php`
- `app/Models/SessaoCrud.php`
- `app/Models/SessaoItem.php`

### Views

- `app/Views/home.php`
- `app/Views/auth/login.php`
- `app/Views/dashboard/index.php`
- `app/Views/layouts/admin.php`
- `app/Views/publico/sessao.php`
- `app/Views/sessoes/index.php`
- `app/Views/sessoes/form.php`
- `app/Views/sessoes/pauta.php`
- `app/Views/pauta/form.php`
- `app/Views/presidencia/index.php`

### Banco de dados

- `database/schema.sql`
- `database/seed.sql`

### Instalador

- `scripts/install.php`

### Estilo

- `public/assets/css/app.css`

## Situação Atual

O projeto já possui:

- login funcional
- logout funcional
- dashboard administrativo
- painel público de sessão
- cadastro/listagem de sessões
- cadastro/edição/listagem de pauta
- painel da presidência
- layout administrativo com sidebar
- fallback de banco com tentativa de conexão local

## Padrão De Acesso Atual

- Tela pública: `public/?r=publico/index`
- Login: `public/?r=auth/login`
- Dashboard: `public/?r=dashboard/index`
- Admin base: `public/?r=admin`
- Sessões: `public/?r=sessao/index`
- Presidência: `public/?r=presidencia/index`

## Credenciais De Teste

- E-mail: `admin@local.test`
- Senha: `admin123`

## Observações Técnicas

- A porta padrão do banco local foi ajustada para `3307`
- O instalador foi tornado idempotente
- O seed foi simplificado para evitar dependência de funções específicas do MySQL
- A tela pública já está com estilo mais próximo de sistema legislativo institucional
- O `/admin` passou a ser o ponto principal do painel interno

## Próximos Passos Sugeridos

1. criar menu real do `/admin` com páginas específicas
2. expandir a tela de pauta para o padrão visual do SAPL
3. criar votação nominal e apuração
4. adicionar permissões/perfis
5. estruturar histórico e atas

