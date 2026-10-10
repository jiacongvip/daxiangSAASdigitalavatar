#!/bin/sh
set -eu

SCRIPT_DIR="$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)"
cd "$SCRIPT_DIR"

echo "Starting backend, MySQL and Redis containers..."
docker compose up -d

echo "Waiting for MySQL to be ready..."
count=0
until docker compose exec -T mysql sh -c 'mysqladmin ping -h 127.0.0.1 -uroot -p"$MYSQL_ROOT_PASSWORD" --silent' >/dev/null 2>&1
do
  count=$((count + 1))
  if [ "$count" -ge 60 ]; then
    echo "MySQL did not become ready in time."
    exit 1
  fi
  sleep 2
done

echo "Waiting for backend HTTP to be ready..."
count=0
until curl -sS -o /dev/null http://127.0.0.1/login/index >/dev/null 2>&1
do
  count=$((count + 1))
  if [ "$count" -ge 60 ]; then
    echo "Backend HTTP did not become ready in time."
    exit 1
  fi
  sleep 2
done

echo "Services are ready."
echo "Backend URL: http://localhost"
echo "Admin URL: http://localhost/admin.php"
echo "API URL: http://localhost/adminapi.php"
echo "MySQL Host: 127.0.0.1:3307"
