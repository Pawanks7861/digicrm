# DigiCRM

Marketing Agency Operating System (greenfield).

## Stack

Laravel 10 · Inertia · Vue 3 · Tailwind · Spatie Permission · SQLite (local default)

## Quick start

```bash
composer install
cp .env.example .env   # if needed
php artisan key:generate
touch database/database.sqlite
php artisan migrate --seed
npm install
npm run build
php artisan serve
```

Open `http://127.0.0.1:8000`

### Demo logins (password: `password`)

| Email | Role |
|-------|------|
| superadmin@digicrm.test | Super Admin (Owner) |
| admin@digicrm.test | Admin |
| manager@digicrm.test | Admin + Manager (HR) |
| am@digicrm.test | Account Manager |
| employee@digicrm.test | Team Member / Employee |
| designer@digicrm.test | Team Member |

## Architecture

See [AGENCY_CRM_ARCHITECTURE.md](AGENCY_CRM_ARCHITECTURE.md) for phases, schema, and permission matrix.

**Phase 1 complete:** RBAC, Clients, Brand, Onboarding, Files shell, Activity log.

## HRMS (integrated)

People/HR modules (employees, attendance, leave, approvals, payroll foundations). Open `/hr` after migrate --seed. CRM time tracking remains separate from attendance.

## Documentation index

| Doc | Purpose |
|-----|---------|
| [AGENCY_CRM_ARCHITECTURE.md](AGENCY_CRM_ARCHITECTURE.md) | CRM phases, schema, permissions |
| [docs/CRM_HRMS_SYSTEM_GUIDE.md](docs/CRM_HRMS_SYSTEM_GUIDE.md) | How HRMS actually works (boundaries, workflows) |
| [docs/CRM_HRMS_GAP_ANALYSIS.md](docs/CRM_HRMS_GAP_ANALYSIS.md) | Implementation gap analysis + checklist |
| [docs/HRMS_SETUP.md](docs/HRMS_SETUP.md) | HRMS quick setup |
| [docs/CRM_HRMS_OPERATIONS_RUNBOOK.md](docs/CRM_HRMS_OPERATIONS_RUNBOOK.md) | Ops, payroll prep, recovery |
| [docs/CPANEL_DEPLOYMENT.md](docs/CPANEL_DEPLOYMENT.md) | MilesWeb/cPanel deploy, preflight, migration recovery |
| [docs/testing/CRM_HRMS_EDGE_CASE_MATRIX.md](docs/testing/CRM_HRMS_EDGE_CASE_MATRIX.md) | Edge-case matrix with evidence |
| [docs/testing/CRM_HRMS_VALIDATION_REPORT.md](docs/testing/CRM_HRMS_VALIDATION_REPORT.md) | QA commands and results |

## Production (cPanel)

See [docs/CPANEL_DEPLOYMENT.md](docs/CPANEL_DEPLOYMENT.md). Document root must be `public/`. Upgrade one-liner:

```bash
cd /home/happyk682/digicrm && export PATH="/opt/alt/alt-nodejs22/root/usr/bin:$PATH" && bash scripts/cpanel-deploy.sh
```

## MySQL (optional)

Set `DB_CONNECTION=mysql` and database credentials in `.env`. Permission columns use length 125 for InnoDB index limits. Then:

```bash
php artisan migrate --seed
```

## Tests

```bash
php artisan test tests/Feature/Hr
php artisan test --filter=ClientModuleTest
# Without host PHP: docker run --rm -v "$PWD:/app" -w /app php:8.1-cli bash ./scripts/docker-hr-test.sh
```
