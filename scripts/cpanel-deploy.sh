#!/usr/bin/env bash
# DigiCRM — MilesWeb cPanel deploy (bash / SSH Terminal)
# Usage (from project root, after uploading/pulling code):
#   bash scripts/cpanel-deploy.sh
#   bash scripts/cpanel-deploy.sh --first-install
set -euo pipefail

PROJECT_DIR="${PROJECT_DIR:-/home/happyk682/digicrm}"
PHP_BIN="${PHP_BIN:-/opt/cpanel/ea-php83/root/usr/bin/php}"
COMPOSER_BIN="${COMPOSER_BIN:-/usr/local/bin/composer}"
NODE_DIR="${NODE_DIR:-/opt/alt/alt-nodejs22/root/usr/bin}"
APP_URL_DEFAULT="https://demo.buildify360.com"

FIRST_INSTALL=0
SKIP_PULL=0
SKIP_MIGRATE=0

for arg in "$@"; do
  case "$arg" in
    --first-install) FIRST_INSTALL=1 ;;
    --skip-pull) SKIP_PULL=1 ;;
    --skip-migrate) SKIP_MIGRATE=1 ;;
    -h|--help)
      sed -n '1,20p' "$0"
      exit 0
      ;;
  esac
done

export PATH="${NODE_DIR}:${PATH}"

cd "$PROJECT_DIR"

echo "==> DigiCRM deploy in $(pwd)"
echo "==> PHP: $($PHP_BIN -v | head -n1)"
echo "==> Node: $(command -v node && node -v)"
echo "==> npm: $(command -v npm && npm -v)"

if [[ ! -f artisan ]]; then
  echo "ERROR: artisan not found. Wrong PROJECT_DIR?" >&2
  exit 1
fi

if [[ $SKIP_PULL -eq 0 ]] && [[ -d .git ]]; then
  if [[ -n "$(git status --porcelain)" ]]; then
    echo "ERROR: dirty working tree. Commit/stash before pull." >&2
    exit 1
  fi
  git pull --ff-only
fi

if [[ ! -f .env ]]; then
  if [[ $FIRST_INSTALL -eq 1 ]]; then
    cp .env.example .env
    echo "Created .env from .env.example — edit DB_* and APP_URL=${APP_URL_DEFAULT} before migrate."
  else
    echo "ERROR: .env missing. Re-run with --first-install only for brand-new installs." >&2
    exit 1
  fi
fi

# Never overwrite an existing APP_KEY
if ! grep -qE '^APP_KEY=base64:.+' .env; then
  if [[ $FIRST_INSTALL -eq 1 ]]; then
    $PHP_BIN artisan key:generate --force
  else
    echo "ERROR: APP_KEY missing in .env. Restore the existing key; do not generate a new one on upgrades." >&2
    exit 1
  fi
else
  echo "==> APP_KEY present (unchanged)"
fi

echo "==> Composer install (no-dev)"
$PHP_BIN "$COMPOSER_BIN" install --no-dev --optimize-autoloader --no-interaction

echo "==> Frontend build (include devDependencies)"
# NODE_ENV=production would skip devDependencies on some npm versions; force include.
npm ci --include=dev
npm run build

if [[ -f public/hot ]]; then
  rm -f public/hot
  echo "==> Removed public/hot"
fi

echo "==> Preflight"
$PHP_BIN artisan deploy:preflight --expect-dir="$PROJECT_DIR" || {
  echo "Preflight reported failures. Fix before migrate." >&2
  exit 1
}

if [[ $SKIP_MIGRATE -eq 0 ]]; then
  echo "==> IMPORTANT: ensure a MySQL dump was taken before schema changes."
  $PHP_BIN artisan migrate --force
fi

echo "==> Caches"
$PHP_BIN artisan config:clear
$PHP_BIN artisan route:clear
$PHP_BIN artisan view:clear
$PHP_BIN artisan config:cache
$PHP_BIN artisan route:cache
$PHP_BIN artisan view:cache

if [[ ! -L public/storage ]] && [[ ! -e public/storage ]]; then
  $PHP_BIN artisan storage:link
else
  echo "==> storage link already present"
fi

# Queue is sync by default; restart only if using a real queue worker.
if grep -qE '^QUEUE_CONNECTION=(database|redis|beanstalkd|sqs)' .env 2>/dev/null; then
  $PHP_BIN artisan queue:restart || true
fi

echo "==> Done. Verify: ${APP_URL_DEFAULT}/login and built assets under /build/assets/"
echo "    Document root must be ${PROJECT_DIR}/public"
