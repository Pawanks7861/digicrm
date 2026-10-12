#!/usr/bin/env bash
set -euo pipefail
export DEBIAN_FRONTEND=noninteractive
apt-get update -qq
apt-get install -y -qq libsqlite3-dev >/dev/null
docker-php-ext-install pdo_sqlite bcmath >/dev/null
php -m | grep -E 'bcmath|pdo_sqlite'
php artisan test tests/Feature/Hr
php artisan test --filter=ClientModuleTest
