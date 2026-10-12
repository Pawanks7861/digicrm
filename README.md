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
| admin@digicrm.test | Super Admin |
| manager@digicrm.test | Admin |
| am@digicrm.test | Account Manager |
| designer@digicrm.test | Team Member |

## Architecture

See [AGENCY_CRM_ARCHITECTURE.md](AGENCY_CRM_ARCHITECTURE.md) for phases, schema, and permission matrix.

**Phase 1 complete:** RBAC, Clients, Brand, Onboarding, Files shell, Activity log.

## MySQL (optional)

Set `DB_CONNECTION=mysql` and database credentials in `.env`. Permission columns use length 125 for InnoDB index limits. Then:

```bash
php artisan migrate --seed
```

## Tests

```bash
php artisan test --filter=ClientModuleTest
```
