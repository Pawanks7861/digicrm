# HRMS setup (DigiCRM)

## Overview

HRMS is added alongside the existing Marketing Agency CRM. CRM time tracking (`time_entries`) and operational cost rates remain unchanged and are **not** attendance or payroll.

## Migrate & seed

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

`DatabaseSeeder` now also runs `HrDemoSeeder`, which:

- Creates the default `Company` (from billing settings / app name)
- Seeds HQ branch, Operations department, designations, general shift, leave types, attendance policy
- Links demo users to employee records with a reporting line
- Allocates casual leave and a sample salary structure

## Demo HR access

Same passwords as CRM (`password`):

| Email | CRM role | HR notes |
|-------|----------|----------|
| admin@digicrm.test | Super Admin (Owner) | Full HR + payroll permissions |
| manager@digicrm.test | Admin + manager | Team approvals |
| am@digicrm.test / designer@digicrm.test | Staff | Own attendance/leave/expenses |

## Routes

- `/hr` — HR dashboard
- `/hr/employees` — employee records
- `/hr/attendance` — check-in/out + daily status
- `/hr/leave` — balances + requests

## Permissions (additive)

Key permissions: `hr.employees.*`, `hr.attendance.*`, `hr.leave.*`, `hr.expenses.*`, `hr.payroll.*`, `hr.documents.*`, `hr.approvals.act_as_owner`, `hr.org.manage`.

New Spatie roles: `manager`, `employee` (optional; demo assigns `manager` to the agency manager user).

## Boundaries

- No invented statutory PF/ESI/TDS rates — payroll snapshot records `statutory: not_configured`.
- No real bank payments; expense “paid” is a status + reference only.
- Employee documents use the private `local` disk; downloads must be authorized.
- Multi-company: schema has `company_id` everywhere; default deployment is single company.

## Tests

```bash
php artisan test --filter=HrFoundationTest
php artisan test --filter=ClientModuleTest
```
