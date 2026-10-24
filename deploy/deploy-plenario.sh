#!/usr/bin/env bash
# Deploy do Plenário na VPS (copiar para /usr/local/bin/deploy-plenario.sh).
#   deploy-plenario.sh           -> atualiza o código, migra o banco e recria o serviço
#   deploy-plenario.sh --build   -> idem, reconstruindo a imagem (quando mudar o Dockerfile)
set -euo pipefail

REPO_DIR="/opt/plenario-repo"
APP_DIR="/opt/plenario"
STACK="plenario"
SERVICO="plenario_plenario_app"
IMAGEM="plenario_app:latest"
DOMINIO="plenariovera.com.br"

REBUILD=0
if [ "${1:-}" = "--build" ]; then
  REBUILD=1
fi

echo "==> Atualizando o código (origin/main)"
cd "$REPO_DIR"
git fetch origin main
git reset --hard origin/main

echo "==> Copiando para $APP_DIR (preserva .env, logs e uploads)"
mkdir -p "$APP_DIR"
rsync -a --delete \
  --exclude='/.git' \
  --exclude='/.github' \
  --exclude='/.vscode' \
  --exclude='/.claude' \
  --exclude='/.env' \
  --exclude='/.env.local' \
  --exclude='/logs' \
  --exclude='/public/uploads' \
  --exclude='/tests' \
  --exclude='/img' \
  "$REPO_DIR"/ "$APP_DIR"/

if [ ! -f "$APP_DIR/.env" ]; then
  echo "ERRO: falta $APP_DIR/.env. Copie de $APP_DIR/.env.production.example e preencha." >&2
  exit 1
fi

mkdir -p "$APP_DIR/logs" "$APP_DIR/public/uploads"
chown -R 33:33 "$APP_DIR/logs" "$APP_DIR/public/uploads"   # 33 = www-data no container
chmod 600 "$APP_DIR/.env"
chown 33:33 "$APP_DIR/.env"

if [ "$REBUILD" = "1" ] || ! docker image inspect "$IMAGEM" >/dev/null 2>&1; then
  echo "==> Construindo a imagem $IMAGEM"
  docker build --pull -t "$IMAGEM" "$REPO_DIR"
fi

if docker service inspect "$SERVICO" >/dev/null 2>&1; then
  echo "==> Recriando o serviço $SERVICO"
  docker service update --force --detach=false "$SERVICO"
else
  echo "==> Criando a stack $STACK"
  docker stack deploy -c "$APP_DIR/docker-stack.yml" "$STACK"
fi

echo "==> Aguardando o container"
CID=""
for _ in $(seq 1 30); do
  CID=$(docker ps -q -f "name=${SERVICO}." -f "status=running" | head -1)
  if [ -n "$CID" ]; then
    break
  fi
  sleep 2
done
if [ -z "$CID" ]; then
  echo "ERRO: o container não subiu. Veja: docker service ps $SERVICO --no-trunc" >&2
  exit 1
fi

echo "==> Migrando o banco (só acrescenta tabelas e colunas)"
docker exec -u www-data "$CID" php scripts/migrate.php

echo "==> Conferindo o site"
codigo=$(curl -s -o /dev/null -w "%{http_code}" "https://${DOMINIO}/index.php?r=auth/login" || true)
echo "https://${DOMINIO} respondeu HTTP ${codigo}"
if [ "$codigo" != "200" ]; then
  echo "Atenção: esperado 200. Logs: docker service logs ${SERVICO} --tail 50" >&2
fi
