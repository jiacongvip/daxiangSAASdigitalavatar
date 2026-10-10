#!/bin/sh
set -eu

SCRIPT_DIR="$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)"
SQL_FILE="$(CDPATH= cd -- "$SCRIPT_DIR/../../sql" && pwd)/tpdaxiangshop.sql"

if [ ! -f "$SQL_FILE" ]; then
  echo "SQL file not found: $SQL_FILE"
  exit 1
fi

cd "$SCRIPT_DIR"

echo "Ensuring MySQL container is running..."
docker compose up -d mysql

echo "Importing $SQL_FILE into tpdaxiangshop..."
docker compose exec -T mysql sh -c 'mysql --default-character-set=utf8mb4 -uroot -p"$MYSQL_ROOT_PASSWORD" "$MYSQL_DATABASE"' < "$SQL_FILE"

echo "Database import completed."
