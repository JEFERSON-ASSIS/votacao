# Deploy do Plenário na VPS (Docker Swarm)

Mesmo modelo do Inclusão 360°: a VPS puxa o código do GitHub (o runner do
GitHub não alcança a porta SSH da VPS) e um serviço Swarm serve a pasta por
bind mount.

| Item | Valor |
|---|---|
| Domínio | https://plenariovera.com.br (Cloudflare: nuvem cinza, só DNS) |
| VPS | 162.141.109.168 |
| Clone do repositório | `/opt/plenario-repo` (único lugar com Git) |
| Pasta servida | `/opt/plenario` → `/var/www/html` no container |
| Imagem | `plenario_app:latest` (construída na VPS, PHP 8.2 + Apache) |
| Stack / serviço | `plenario` / `plenario_plenario_app` |
| Rede | `SGDMNET` (a mesma do Traefik, do MySQL e do Redis) |
| Banco | MySQL 8.0 compartilhado `mysql_mysql`, banco e usuário `plenario` |
| Sessões de login | Redis compartilhado `redis_redis`, banco 2, prefixo `plenario:session:` |
| Script de deploy | `/usr/local/bin/deploy-plenario.sh` |

O Apache aponta direto para `public/`: `.env`, `app/`, `logs/` e `database/`
ficam fora do alcance da web.

## Primeira instalação (uma vez só)

Na VPS, como root:

```bash
# 1. Clonar o repositório (mesmo acesso usado pelo /opt/inclusao-repo)
git clone https://github.com/JEFERSON-ASSIS/votacao.git /opt/plenario-repo
mkdir -p /opt/plenario

# 2. Instalar o script de deploy
cp /opt/plenario-repo/deploy/deploy-plenario.sh /usr/local/bin/deploy-plenario.sh
chmod +x /usr/local/bin/deploy-plenario.sh
```

**3. Criar o banco** no phpMyAdmin (servidor `mysql_mysql`):

```sql
CREATE DATABASE plenario CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'plenario'@'%' IDENTIFIED BY 'TROQUE_POR_UMA_SENHA_FORTE';
GRANT ALL PRIVILEGES ON plenario.* TO 'plenario'@'%';
FLUSH PRIVILEGES;
```

**4. Criar o `.env`:**

```bash
cp /opt/plenario-repo/.env.production.example /opt/plenario/.env
nano /opt/plenario/.env
```

Preencha `APP_KEY` (gere com `openssl rand -hex 32`), `DB_PASS` (a senha do
passo 3), `ADMIN_EMAIL` e `ADMIN_PASSWORD` (10+ caracteres; é o primeiro
login do sistema).

**5. Primeiro deploy** (constrói a imagem, cria a stack e o banco):

```bash
deploy-plenario.sh --build
```

**6. Conferir:** abra https://plenariovera.com.br, entre com o `ADMIN_EMAIL`
e troque a senha. Depois, apague `ADMIN_EMAIL` e `ADMIN_PASSWORD` do `.env`.

O certificado HTTPS é emitido pelo Traefik (Let's Encrypt) no primeiro
acesso. Pode levar até 1 minuto.

## Deploy do dia a dia

No computador:

```bash
git status
git add ARQUIVOS_ALTERADOS
git commit -m "mensagem"
git push origin main
```

Na VPS:

```bash
deploy-plenario.sh            # só código PHP/CSS/JS
deploy-plenario.sh --build    # quando mudar o Dockerfile ou docker/*.ini
```

O script:

1. atualiza `/opt/plenario-repo` com `origin/main`;
2. copia para `/opt/plenario`, preservando `.env`, `logs/` e `public/uploads/`
   (fotos e anexos) e sem levar `tests/` nem `.git`;
3. reconstrói a imagem se pedido (`--build`) ou se ela não existir;
4. recria o serviço (`docker service update --force`, start-first). Como as
   sessões ficam no Redis, ninguém é deslogado;
5. roda `php scripts/migrate.php` dentro do container (só acrescenta
   tabelas e colunas; pode rodar sempre);
6. confere se o site responde 200.

> Evite fazer deploy durante uma sessão plenária em andamento.

## Parar e voltar (manutenção)

```bash
docker service scale plenario_plenario_app=0   # site fora do ar
docker service scale plenario_plenario_app=1   # volta
```

O MySQL, o Redis e o Traefik continuam rodando; os outros sistemas da VPS não
são afetados. Recriar a stack do zero:

```bash
docker stack rm plenario
docker stack deploy -c /opt/plenario/docker-stack.yml plenario
```

## Diagnóstico

```bash
docker service ps plenario_plenario_app --no-trunc
docker service logs plenario_plenario_app --tail 100
docker service inspect plenario_plenario_app --format '{{json .Spec.Labels}}'
tail -n 100 /opt/plenario/logs/app.log
curl -I https://plenariovera.com.br/index.php?r=auth/login
```

## Backup

- **Banco:** o MySQL compartilhado; inclua o banco `plenario` na rotina de backup.
- **Arquivos enviados:** `/opt/plenario/public/uploads` (fotos dos vereadores,
  anexos do roteiro, atas).
- **Configuração:** `/opt/plenario/.env`.

## Testar a imagem no computador (antes de subir)

```bash
docker compose up -d --build
# http://127.0.0.1:8088  (admin@plenario.local / SenhaLocal#2026)
docker compose exec -u www-data plenario_app php scripts/migrate.php
docker compose down
```

O `docker-compose.yml` é só para esse teste: imita a VPS (MySQL 8.0 como
`mysql_mysql`, Redis como `redis_redis`) com senhas de exemplo.
