# Plenario

Sistema de votação e condução de sessões plenárias: pauta, roteiro do presidente, votação por rodada, painel público (telão), ata e auditoria.

## Requisitos

- PHP 8.1 ou superior, com as extensões `pdo_mysql`, `mbstring` e `fileinfo`
- MySQL 8 ou MariaDB 10.3+
- Apache com `mod_rewrite` (o XAMPP já vem com tudo isso)

## Estrutura

```
app/                 código da aplicação (Controllers, Models, Views, Helpers)
public/              ponto de entrada (index.php), CSS, JS e imagens
public/uploads/      arquivos enviados pelo sistema (fotos, anexos)
database/schema.sql  estrutura completa do banco e dados iniciais
scripts/migrate.php  instala ou atualiza o banco
logs/                log da aplicação (app.log)
```

---

## Rodando no localhost (XAMPP)

### 1. Colocar o projeto no htdocs

Copie ou clone o projeto dentro de `C:\xampp\htdocs\`. Exemplo:

```
C:\xampp\htdocs\votacao
```

### 2. Iniciar o Apache e o MySQL

Abra o **XAMPP Control Panel** e clique em **Start** no **Apache** e no **MySQL**.

> Veja a porta do MySQL na coluna *Port(s)* do painel. O padrão do XAMPP é `3306`; se você mudou, use a sua (ex.: `3307`).

### 3. Criar o banco de dados

Abra o phpMyAdmin em `http://localhost/phpmyadmin`, vá em **Novo** e crie um banco chamado `plenario` com o agrupamento `utf8mb4_unicode_ci`.

Ou pelo terminal:

```bash
C:\xampp\mysql\bin\mysql -u root -P 3306 -e "CREATE DATABASE plenario CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
```

### 4. Configurar o `.env`

Na raiz do projeto, copie `.env.example` para `.env` e ajuste:

```ini
APP_ENV=local
APP_BASE_URL=http://localhost/votacao/public
APP_DEBUG=1
APP_KEY=qualquer-texto-local

DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=plenario
DB_USER=root
DB_PASS=
```

- `APP_BASE_URL` é o endereço da pasta `public/` no navegador. Ajuste se o projeto estiver em outro caminho.
- `DB_PORT` é a porta do MySQL que aparece no XAMPP.

### 5. Criar as tabelas

No terminal, dentro da pasta do projeto:

```bash
C:\xampp\php\php.exe scripts/migrate.php
```

Se o `php` já estiver no PATH, basta `php scripts/migrate.php`. O resultado esperado termina com:

```
Tabelas verificadas.
Usuario admin@local.test criado.
Migracao concluida.
```

### 6. Acessar

Abra `http://localhost/votacao/public` (o mesmo endereço do `APP_BASE_URL`) e entre com:

| E-mail             | Senha      |
|--------------------|------------|
| `admin@local.test` | `admin123` |

**Troque essa senha no primeiro acesso.**

---

## Publicando em produção (hospedagem compartilhada / VPS)

1. **Envie os arquivos** para a pasta do site (via Git, FTP ou gerenciador de arquivos). Não envie o `.env` local.
2. **Crie o banco** no painel da hospedagem (hPanel, cPanel etc.) com um usuário e senha, dando todas as permissões nesse banco.
3. **Crie o `.env`** na raiz do projeto no servidor:

   ```ini
   APP_ENV=production
   APP_BASE_URL=https://seu-dominio.com.br/public
   APP_PROD_DOMAIN=seu-dominio.com.br
   APP_DEBUG=0
   APP_KEY=uma-chave-longa-e-aleatoria

   DB_HOST=localhost
   DB_PORT=3306
   DB_NAME=nome_do_banco
   DB_USER=usuario_do_banco
   DB_PASS=senha_do_banco
   ```

4. **Rode a migração** pelo SSH, na pasta do projeto:

   ```bash
   php scripts/migrate.php
   ```

   Sem SSH, importe o `database/schema.sql` pelo phpMyAdmin do servidor. Nesse caso, o usuário admin não é criado automaticamente.

5. **Aponte o domínio** para a raiz do projeto. O `.htaccess` da raiz encaminha tudo para `public/`. Se puder escolher a pasta pública, aponte direto para `public/`.
6. **Dê permissão de escrita** ao servidor web nas pastas `public/uploads/` e `logs/`.

---

## Atualizando o sistema

Depois de atualizar o código (local ou produção), rode de novo:

```bash
php scripts/migrate.php
```

O script pode ser executado quantas vezes for preciso. Ele só cria as tabelas, colunas e índices que faltarem, sem apagar dados.

Para mudar a estrutura do banco, altere `database/schema.sql` e rode o script.

---

## Variáveis do `.env`

| Variável                  | Descrição                                                           |
|---------------------------|---------------------------------------------------------------------|
| `APP_ENV`                 | `local` ou `production`                                             |
| `APP_NAME`                | Nome exibido no sistema                                             |
| `APP_BASE_URL`            | Endereço da pasta `public/` (ex.: `http://localhost/votacao/public`) |
| `APP_PROD_DOMAIN`         | Domínio de produção, sem `http(s)://`                               |
| `APP_TIMEZONE`            | Fuso horário (padrão `America/Cuiaba`)                              |
| `APP_DEBUG`               | `1` mostra os erros na tela; use `0` em produção                    |
| `APP_KEY`                 | Chave secreta da aplicação                                          |
| `DB_HOST` / `DB_PORT`     | Servidor e porta do MySQL                                           |
| `DB_NAME`                 | Nome do banco                                                       |
| `DB_USER` / `DB_PASS`     | Usuário e senha do banco                                            |
| `DB_REMOTE_HOST`          | Host do banco de produção ao acessar de fora do servidor            |
| `SESSION_TIMEOUT_MINUTES` | Tempo de sessão do login, em minutos                                |

---

## Problemas comuns

| Problema                                  | Solução                                                                                         |
|-------------------------------------------|-------------------------------------------------------------------------------------------------|
| `Falha ao conectar no banco configurado`  | Confira se o MySQL está ligado e se `DB_PORT`, `DB_NAME`, `DB_USER` e `DB_PASS` no `.env` estão certos. |
| Página sem estilo ou links quebrados      | O `APP_BASE_URL` não bate com o endereço aberto no navegador.                                   |
| Erro 404 em todas as páginas              | Ative o `mod_rewrite` do Apache e confira se o `.htaccess` foi enviado.                          |
| `php` não é reconhecido no terminal       | Use o caminho completo: `C:\xampp\php\php.exe scripts/migrate.php`.                             |
| Erro no upload de anexos                  | Dê permissão de escrita em `public/uploads/`. O limite do sistema é 15 MB por arquivo.           |

Os erros da aplicação ficam em `logs/app.log`.
