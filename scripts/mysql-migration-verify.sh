#!/usr/bin/env bash
# Thin wrapper: start disposable MySQL if needed, then run PHP verifier.
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
CONTAINER="${DIGICRM_MYSQL_CONTAINER:-digicrm-mysql-test}"

if ! docker ps --format '{{.Names}}' | grep -qx "$CONTAINER"; then
  docker rm -f "$CONTAINER" >/dev/null 2>&1 || true
  docker run -d --name "$CONTAINER" \
    -e MYSQL_ROOT_PASSWORD=secret \
    -e MYSQL_DATABASE=digicrm_bootstrap \
    mysql:8.0 \
    --default-storage-engine=InnoDB \
    --character-set-server=utf8mb4 \
    --collation-server=utf8mb4_unicode_ci
fi

for i in $(seq 1 60); do
  if docker exec "$CONTAINER" mysqladmin ping -uroot -psecret --silent 2>/dev/null; then
    break
  fi
  sleep 2
done

docker run --rm --network "container:$CONTAINER" \
  -v "$ROOT:/app" -w /app \
  -e APP_ENV=testing \
  -e APP_KEY=base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA= \
  -e DB_CONNECTION=mysql \
  -e DB_HOST=127.0.0.1 \
  -e DB_PORT=3306 \
  -e DB_DATABASE=digicrm_bootstrap \
  -e DB_USERNAME=root \
  -e DB_PASSWORD=secret \
  -e DB_ENGINE=InnoDB \
  -e CACHE_DRIVER=array \
  -e SESSION_DRIVER=array \
  -e QUEUE_CONNECTION=sync \
  php:8.3-cli bash -lc '
    set -e
    export DEBIAN_FRONTEND=noninteractive
    apt-get update -qq
    apt-get install -y -qq git unzip libzip-dev default-mysql-client >/dev/null
    docker-php-ext-install pdo_mysql bcmath zip >/dev/null
    mysql -h127.0.0.1 -uroot -psecret -e "CREATE DATABASE IF NOT EXISTS digicrm_bootstrap CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
    php scripts/mysql-migration-verify.php
  '
