# DigiCRM × HRMS — Operations Runbook

## 1. Local setup

```bash
composer install
cp .env.example .env          # set APP_KEY via artisan
php artisan key:generate
touch database/database.sqlite
# Prefer SQLite for local HRMS QA:
#   DB_CONNECTION=sqlite
#   DB_DATABASE=/absolute/path/to/database/database.sqlite
php artisan migrate --seed
npm install
npm run build
php artisan serve
```

Open `http://127.0.0.1:8000/hr` after login.

**Docker test helper (no host PHP):** `bash scripts/docker-hr-test.sh` inside `php:8.1-cli` (see validation report).

### Environment variables (names only — never commit secrets)

| Variable | Purpose |
|----------|---------|
| `APP_KEY` | Encryption |
| `APP_URL` | Links / Ziggy |
| `DB_CONNECTION` / `DB_*` | Database |
| `FILESYSTEM_DISK` | Default disk (`local` for employee docs) |
| `MAIL_*` / VAPID (Phase 9) | Notifications — **not required** for HR commits |
| `QUEUE_CONNECTION` | Use `sync` locally; `database`/`redis` in deployed envs |

## 2. Migration / deploy prerequisites

1. PHP **8.1+** with extensions: `pdo_sqlite` or `pdo_mysql`, **`bcmath`**, `tokenizer`, `mbstring`, `openssl`.  
2. Run `php artisan migrate` (includes HR foundation + `leave_fraction` + decimal LOP).  
3. Reseed permissions if upgrading existing DB: `php artisan db:seed --class=RolePermissionSeeder` then `HrDemoSeeder` as appropriate.  
4. Build frontend assets (`npm run build`) so `/hr` Vue pages load.  
5. Ensure `storage/app` writable — employee documents use the **`local`** disk (not public).

**Data safety:** Migrations are additive (new tables/columns). Do not drop CRM tables. Soft deletes preserve history.

## 3. Background jobs

| Job class | HRMS status |
|-----------|-------------|
| Accrual / leave expiry | **Not implemented** |
| Document expiry reminders | **Not implemented** |
| Biometric import | **Not implemented** |
| Phase 9 notification workers | Optional; HR does not depend on them for commits |

When queueing is enabled for CRM, monitor `failed_jobs` as usual. HR currently executes inline in HTTP requests (transactions in services).

## 4. Monitoring and troubleshooting

| Symptom | Check |
|---------|-------|
| 403 on employee pages | Reporting line + `hr.employees.*` permissions |
| Punch rejected | Employment status, join/exit window, locked day, punch order |
| Leave “insufficient balance” | Ledger sum including reservations (`leave_ledger_entries`) |
| Cannot cancel leave | Attendance day `is_locked` — requires future adjustment process |
| Payroll regen fails | Run already `locked` for period |
| Expense “already paid” | `payment_status` in `via_payroll` / `paid` |
| Document 403 | Not owner/manager/reviewer; wrong company type |

Audit trail: `activity_logs` where `module = 'hr'`.

## 5. Payroll preparation and reconciliation

**Documented procedure (partially automated via readiness widget):**

1. Resolve attendance `exception` days (missing punches).  
2. Clear pending leave/expense approvals.  
3. Ensure active employees have `employee_salary_assignments`.  
4. `PayrollService::generateDraft` → review → approve → lock.  
5. Reconcile: item `calculation` JSON + expense rows `via_payroll`.  
6. **Do not** invent statutory lines; snapshot says `not_configured`.

**Not tested end-to-end in production:** bank file export, payslip PDF distribution, payment gateway.

**Post-lock corrections:** Cancel of approved leave on locked days is **blocked**. Controlled adjustment runs are **not implemented** — treat as release blocker for production payroll ops.

## 6. Recovery

| Failure | Recovery |
|---------|----------|
| HTTP error mid-leave submit | Transaction rollback; retry submit |
| Duplicate punch retry | Same `idempotency_key` returns original punch |
| Upload stored but DB fails | May orphan file under `storage/app/hr/...` — **manual cleanup**; compensating delete not automated |
| Locked payroll wrong inputs | Do **not** unlock in DB ad hoc; wait for adjustment feature or restore from backup |
| Failed notification | Business data remains; retry CRM notification tools if used |

## 7. Backup and restore

| Procedure | Status |
|-----------|--------|
| DB backup via host MySQL/SQLite tooling | **Documented** — not executed in this QA pass |
| `storage/app` backup for private HR docs | **Documented** — required with DB restore |
| Point-in-time restore drill | **Not tested** here |

Restore order: database → `storage/app` → `php artisan config:clear` → verify `/hr` and sample employee document download (once route wired).

## 8. Demo accounts

Password: `password` (local seed only).

| Email | Notes |
|-------|-------|
| admin@digicrm.test | Owner / super_admin |
| manager@digicrm.test | Admin + manager HR role |
| am@digicrm.test / designer@digicrm.test | Employee self-service HR |

See also [`HRMS_SETUP.md`](./HRMS_SETUP.md).
