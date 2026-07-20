#!/bin/bash
# Importa dump SQL no banco de producao (Hostinger).
# Uso no SSH:
#   cd ~/votacao.agendaclique.com.br
#   bash scripts/import_on_server.sh plenario_local_export.sql
#
# ATENCAO: substitui os dados atuais do banco de producao.

set -e
DUMP="${1:-plenario_local_export.sql}"
if [ ! -f "$DUMP" ]; then
  echo "Arquivo nao encontrado: $DUMP"
  echo "Envie o .sql para esta pasta (ex.: via FTP para ~/votacao.agendaclique.com.br/)"
  exit 1
fi

DB_NAME="${DB_NAME:-jefe0292_plenario}"
DB_USER="${DB_USER:-jefe0292_user_plenario}"
DB_PASS="${DB_PASS:-jefe0292_user_plenario}"

echo "Backup rapido antes de importar..."
BACKUP="backup_antes_import_$(date +%Y%m%d_%H%M%S).sql"
mysqldump -h localhost -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" > "$BACKUP" 2>/dev/null || true
echo "Backup (se ok): $BACKUP"

echo "Importando $DUMP em $DB_NAME ..."
mysql -h localhost -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" < "$DUMP"

echo "Rodando migracoes (colunas/tabelas novas)..."
php scripts/migrate_all.php

echo "Concluido. Teste o login e as sessoes no site."
