# DigiCRM deployment verification log

Date: 2026-10-14  
Scope: MilesWeb/cPanel readiness (migrations, build, preflight).  
Production host was **not** contacted.

## Tested (disposable MySQL 8.0.46 in Docker)

Command pattern: PHP 8.3 CLI container sharing network with `digicrm-mysql-test`, running `php scripts/mysql-migration-verify.php`.

| Scenario | Result |
|----------|--------|
| A. Fresh `migrate --force` + immediate rerun | **PASS** |
| B. Empty MyISAM `permissions` leftover, then migrate | **PASS** (recreated InnoDB + unique + sibling tables) |
| C. `users` converted to MyISAM before clients migration | **PASS** (converted to InnoDB; `clients` created) |
| D. Exact partial phase11 (extension columns + FKs present, `mms_unique` still present, sample snapshot row) then migrate | **PASS** (row preserved; `mms_unique` dropped; phase11 completed) |
| E. Migrate with nothing pending | **PASS** |
| F. Populated incompatible `permissions` | **PASS** (refused with actionable `Migration blocked` error; no silent completion) |

Also fixed during verification (fresh MySQL was failing before phase11 completed):

- Index name `client_report_metrics_client_performance_report_id_metric_code_index` (>64 chars)
- Three HRMS composite indexes over 64 characters

## Tested (SQLite)

| Check | Result |
|-------|--------|
| Full `migrate --force` on SQLite | **PASS** |
| Second migrate (nothing pending) | **PASS** |
| `deploy:preflight` against SQLite | Runs; warns on non-mysql DB / localhost APP_URL (expected for local) |
| `php artisan test --filter=ClientModuleTest` | **PASS** (7 tests) |

## Tested (frontend)

| Check | Result |
|-------|--------|
| `npm ci --include=dev` (Node 24 locally; host target Node 22) | **PASS** |
| `npm run build` | **PASS** (`public/build/manifest.json` written) |
| `public/hot` absent after build | **PASS** |

`npm audit` reported 11 vulnerabilities (7 high). **Not** auto-fixed with `--force` (risk of breaking Vite 4 toolchain). Treat as residual risk; review separately.

## Tested (Composer / platform)

| Check | Result |
|-------|--------|
| `doctrine/dbal` in `require` (not require-dev) | Present (`~3.7`) |
| `composer install --no-dev` on host | **Not re-run in this session** (vendor already present); script uses ea-php83 + composer on server |
| PHP `^8.1` / target 8.3 | Compatible; verified migrate under PHP 8.3 container |

## Not tested on MilesWeb (hosting-only)

Must verify on `https://demo.buildify360.com` after deploy:

1. Web PHP 8.3 extensions match CLI (`pdo_mysql`, `bcmath`, …)
2. Document root = `/home/happyk682/digicrm/public`
3. Existing `APP_KEY` unchanged after upgrade
4. Login page + `/build/assets/*` over HTTPS
5. Live partial phase11 schema matches Scenario D assumptions (`SHOW INDEX FROM marketing_metric_snapshots`)
6. Cron `schedule:run` if automations/reminders are required
7. Storage permissions without `chmod 777`

## Verdict

Application migration/build defects identified for shared-hosting MySQL are **fixed and verified in disposable MySQL**.  
The production demo is **not** declared fully clean until hosting-only checks above succeed after deploying this revision.
