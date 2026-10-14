#!/usr/bin/env bash
set -euo pipefail
cd /app

export DEBIAN_FRONTEND=noninteractive
apt-get update -qq
apt-get install -y -qq libsqlite3-dev git unzip libzip-dev >/dev/null
docker-php-ext-install pdo_sqlite bcmath zip >/dev/null

export APP_ENV=testing
export APP_KEY=base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=
export DB_CONNECTION=sqlite
export DB_DATABASE=/app/database/preflight_test.sqlite
export CACHE_DRIVER=array
export SESSION_DRIVER=array
export QUEUE_CONNECTION=sync

rm -f database/preflight_test.sqlite
touch database/preflight_test.sqlite
php artisan migrate --force >/dev/null

php artisan test --filter=DeployPreflightCommandTest

DISABLED='exec,shell_exec,system,passthru,proc_open,popen'
echo "==> no-exec preflight (disable_functions=${DISABLED})"
set +e
OUT="$(php -d "disable_functions=${DISABLED}" artisan deploy:preflight 2>&1)"
CODE=$?
set -e
echo "$OUT"

if echo "$OUT" | grep -q 'Call to undefined function'; then
  echo "FAIL: undefined function fatal" >&2
  exit 1
fi
if echo "$OUT" | grep -q 'App\\Console\\Commands\\exec'; then
  echo "FAIL: namespaced exec() call" >&2
  exit 1
fi
if [[ -d .git ]]; then
  if echo "$OUT" | grep -q '✓ Git working tree clean'; then
    echo "FAIL: marked git check passed while exec disabled" >&2
    exit 1
  fi
  if ! echo "$OUT" | grep -Eqi 'unavailable|process execution disabled'; then
    echo "FAIL: expected unavailable git-check notice" >&2
    exit 1
  fi
fi

echo "==> mandatory failure still fails without PHP exec (public/hot)"
touch public/hot
set +e
FAIL_OUT="$(php -d "disable_functions=${DISABLED}" artisan deploy:preflight 2>&1)"
FAIL_CODE=$?
set -e
rm -f public/hot
echo "$FAIL_OUT"
if [[ "$FAIL_CODE" -eq 0 ]]; then
  echo "FAIL: preflight succeeded with public/hot present" >&2
  exit 1
fi
if ! echo "$FAIL_OUT" | grep -Eqi 'public/hot'; then
  echo "FAIL: expected public/hot failure" >&2
  exit 1
fi
echo "==> required-check failure stops deploy path (exit ${FAIL_CODE})"

rm -f database/preflight_test.sqlite
echo "NO_EXEC_OK prior_exit=${CODE}"
