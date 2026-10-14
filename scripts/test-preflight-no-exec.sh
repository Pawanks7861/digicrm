#!/usr/bin/env bash
# Prove deploy:preflight survives disable_functions for process helpers.
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

PHP_BIN="${PHP_BIN:-php}"
DISABLED='exec,shell_exec,system,passthru,proc_open,popen'

echo "==> Preflight with disable_functions=${DISABLED}"
set +e
OUT="$($PHP_BIN -d "disable_functions=${DISABLED}" artisan deploy:preflight 2>&1)"
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

# Unavailable git check must not be reported as a green pass.
if echo "$OUT" | grep -q 'Git working tree clean'; then
  echo "FAIL: marked git check passed while exec disabled" >&2
  exit 1
fi

if ! echo "$OUT" | grep -Eqi 'Git working-tree check unavailable|process execution disabled'; then
  # Only required when .git exists
  if [[ -d .git ]]; then
    echo "FAIL: expected unavailable git-check warning when .git present" >&2
    exit 1
  fi
fi

# Mandatory failure path: missing vendor should still fail (simulate via expect if needed).
# Here we only assert the command did not fatal; exit 0 or 1 both OK if output clean.
echo "==> no-exec preflight completed (exit ${CODE})"
exit 0
