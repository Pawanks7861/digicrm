# DigiCRM × HRMS — Gap Analysis

**Date:** 2026-10-12  
**Repository:** [Pawanks7861/digicrm](https://github.com/Pawanks7861/digicrm)  
**Method:** Static code inspection (migrations, models, services, routes, Vue pages, seeders, tests, architecture docs).  
**Runtime testing:** Not executed for this assessment unless noted under Validation Results.

> **Critical finding:** This repository is a Marketing Agency Operating System (DigiCRM Phases 1–13). There is **no HRMS module** (no employee/attendance/leave/payroll/expense tables, controllers, pages, or tests). Claims of “partial HRMS” or a separate “influencer CRM” are **not supported by this codebase**. Influencer appears only as campaign/deliverable *types* in `config/agency.php`.

---

## 1. Evidence summary

| Area | Evidence | Classification |
|------|----------|----------------|
| Architecture | `AGENCY_CRM_ARCHITECTURE.md`, `README.md` — agency CRM phases 1–13; payroll explicitly out of commercial boundary | CRM implemented (by design) |
| Stack | `composer.json` Laravel 10 + Inertia + Spatie Permission; `package.json` Vue 3 + Tailwind | Verified |
| Models | 85 models under `app/Models/` — clients, campaigns, tasks, time_entries, leads, invoices, chat, etc. **Zero** `Employee`, `Attendance*`, `Leave*`, `Payroll*`, `ExpenseClaim*` | HRMS missing |
| Migrations | 27 migrations through Phase 13 (`2026_10_04_…chat…`). No HR org/attendance/leave/payroll migrations | HRMS missing |
| Routes | `routes/web.php` — agency CRM + portal + webhooks. No `/hr/*`, `/attendance`, `/leave`, `/payroll` | HRMS missing |
| Permissions | `database/seeders/RolePermissionSeeder.php` — CRM roles only (`super_admin`, `admin`, `account_manager`, `team_member`, `client`) | HR roles missing |
| Tests | ~37 Feature suites under `tests/Feature` — CRM modules; no HRMS tests | HRMS missing |
| “Company” | `BillingSetting` + `CompanySettingsController` = **single-agency branding/billing**, not multi-tenant company isolation | Needs clarification for multi-company HR |
| Time | `time_entries` = billable/productivity timers (`TimeTrackingService`), not attendance punches | Related but not attendance |
| Capacity | `user_work_capacities`, `agency_holidays` = workload planning | Partially reusable for leave calendars / weekly offs |
| Cost | `user_cost_rates` = operational hourly cost; UI states “not salary or payroll” (`CostRates.vue`) | Must not conflate with payroll |
| Tasks | Full CRM task module with assignees/reviewer roles, statuses, comments, delays | Partially reusable |
| Files | `client_files` scoped to clients; authorized download routes | Pattern reusable; employee docs missing |
| Approvals | `client_approvals`, `internal_reviews` — content/creative workflows | Pattern inspiration only; not HR approval engine |
| Notifications | Phase 9 dispatch/prefs/templates | Reusable channels |
| Audit | `ActivityLogger` + `activity_logs` | Reusable |
| Influencers | No influencer entity/table; only type strings | Missing as CRM domain object |

### Verified vs assumed

| Statement | Status |
|-----------|--------|
| No HRMS tables/models/routes exist | **Verified** (filesystem + grep) |
| CRM phases 1–13 code and tests exist | **Verified** (files present) |
| CRM features work end-to-end in this environment | **Assumed until tests run** — inspect-only unless Validation Results say otherwise |
| Product will remain single-agency initially with `company_id` for future multi-tenant | **Safe assumption** (recorded for implementation) |
| Map Owner→`super_admin` (+ HR permissions), Manager→reporting line + `manager` role, Employee→linked `employees` + existing staff roles | **Safe assumption** |
| Statutory payroll (PF/ESI/TDS) rates not inventable | **Verified** by architecture boundary + product scope |

---

## 2. Existing architecture & reusable components

### 2.1 Stack & patterns (reuse)

- **Inertia controllers + domain services** (`App\Services\*`) — source of truth for business rules.
- **Spatie Permission** + policies (`App\Policies\*`) — server-side enforcement.
- **Soft deletes** on core business tables.
- **ActivityLogger** for audit trails.
- **NotificationDispatchService** + categories for domain events.
- **BCMath money** pattern in Phase 8 commercial services — reuse for payroll/expenses.
- **Effective-dated ranges** pattern in `user_work_capacities` / `user_cost_rates` — reuse for salary/shift/policy assignments.
- **Authorized file download** pattern (`clients.files.download`, chat attachments).
- **Automation engine** (Phase 10) — optional later for HR reminders; not required for MVP core.

### 2.2 CRM modules (preserve)

Clients, brand, onboarding, packages, campaigns, deliverables, tasks, content/approvals, requests, time/workload, profitability (operational), commercial (quotes/invoices/payments), notifications, integrations/automation, performance/leads, company branding, chat.

**Regression requirement:** Existing CRM Feature tests remain green; no destructive schema changes to CRM tables.

### 2.3 Near-HR building blocks (do not over-reuse)

| Existing | Use for HRMS? |
|----------|---------------|
| `User` | Login identity only; link via `employees.user_id` |
| `user_work_capacities` | Inform shift defaults / capacity; **not** attendance |
| `agency_holidays` | Seed/migrate into company holiday calendars |
| `time_entries` | Keep for CRM costing; **separate** from attendance punches |
| `user_cost_rates` | Keep operational; **separate** from salary structures |
| `Task` | Extend optional HR personal/team views; keep CRM links |
| `ClientFile` | Do **not** store employee documents here |
| `BillingSetting` | Agency seller identity; link as default `Company` record |

---

## 3. Feature assessment matrix

Legend: **IV** = Implemented & verified (tests/runtime) · **II** = Implemented (inspected, not runtime-verified) · **PI** = Partially implemented · **M** = Missing · **B** = Broken · **NC** = Needs clarification

| Feature | Status | Evidence / notes |
|---------|--------|------------------|
| Multi-company tenant model | M / NC | Only single `billing_settings`; no `companies` tenant table |
| Branch / department / designation | M | No tables |
| Employee profile ≠ login user | M | `users.job_title` only; no employment record |
| Employment type/status/exit | M | — |
| Reporting manager | M | — |
| Employee policy assignments | M | — |
| Effective-dated salary/shift/policy | PI (pattern only) | Capacity/cost rates exist for ops, not HR |
| Roles Employee / Manager / Owner | M | CRM roles differ; need mapping + new permissions |
| Granular HR permissions + scopes | M | — |
| No self-approval / escalation | M | No HR approval engine |
| Attendance punches (raw) | M | — |
| Derived daily attendance | M | — |
| Shifts (fixed/flex/overnight) | M | — |
| Attendance rules & lock | M | — |
| Biometric integration | M | No adapter; import boundary required |
| Leave types (configurable) | M | — |
| Leave ledger | M | — |
| Leave request/approve/cancel | M | — |
| Comp-off linkage | M | — |
| Payroll structures & runs | M | Architecture forbids inventing statutory rates |
| Payslips / bank export | M | — |
| Payroll lock & adjustments | M | — |
| Tasks (CRM) | II | `Task`/`TaskService`/`TaskPolicy` + Feature tests exist |
| Recurring tasks | M | No recurrence fields/jobs found |
| Task ↔ leave coverage conflicts | M | — |
| Employee documents | M | Client files only |
| Employee expenses / reimbursement | M | `operational_costs` / campaign costs are not claims |
| Onboarding (employee) | M | Client onboarding only |
| HR dashboards | M | CRM dashboards only |
| Imports/exports (HR) | M | Lead CSV import exists as pattern |

---

## 4. Security & data-integrity gaps

| Gap | Risk | Mitigation plan |
|-----|------|-----------------|
| No tenant/`company_id` on HR entities | Cross-company leakage if multi-company added later | Add `company_id` on all HR tables from day one; scope every query |
| Salary/bank in shared user payloads | Exposure via Inertia share | Never put sensitive HR fields in `HandleInertiaRequests`; permission-gate payloads |
| Self-approval | Fraud | ApprovalService rejects actor == requester; manager own requests escalate |
| Public URLs for employee docs | Data leak | Private disk + authorized download routes + audit |
| Conflating `time_entries` with attendance | Wrong payroll inputs | Separate `attendance_punches` / `attendance_days` |
| Conflating `user_cost_rates` with salary | Wrong costing/payroll | Separate `salary_structures` / assignments |
| Duplicate leave spend / LOP | Balance & pay errors | Ledger + reservations + unique constraints + tests |
| Arbitrary payroll formulas | RCE / wrong pay | Whitelisted expression evaluator only |
| Real bank payments | Out of scope | Export files only; no payment initiation |
| Historical mutation after payroll lock | Compliance | Immutable snapshots; correction via adjustment runs |

---

## 5. Dependencies & implementation priorities

```
1. Company + org units + Employee (+ link User) + HR permissions
2. Policy config + ApprovalRequest engine (no self-approve, delegate, escalate)
3. Shifts + Attendance punches/days + rules + leave integration hooks
4. Leave types + ledger + requests + attendance day updates
5. Task extensions (reviewer/personal views/recurrence) + Documents + Expenses
6. Payroll structures → draft/review/approve/lock + reimbursements link
7. Dashboards/reporting + payroll readiness blockers
8. Regression CRM tests + HR Feature tests + docs
```

### Blocking business decisions (ask only if they block correctness)

1. **Multi-company now?** Default: single company seeded from `BillingSetting`, schema ready for N companies.
2. **India statutory payroll?** Default: jurisdiction boundary stub; no invented PF/ESI/TDS rates.
3. **Manager role:** new Spatie role `manager` vs capability via `employees.reporting_manager_employee_id` only? Default: both — reporting line for scope + optional `manager` role for elevated team tools.
4. **Currency:** Default INR from `BillingSetting.default_currency`; no silent FX.

---

## 6. Acceptance criteria (MVP)

### Foundation
- [ ] Company, branch, department, designation CRUD (permissioned).
- [ ] Employee record linked optionally to `users.id`; employment fields persist.
- [ ] All HR queries scoped by `company_id` (+ branch where configured).
- [ ] Permissions enforced in policies/services (not Vue alone).

### Approvals
- [ ] Requests cannot be self-approved.
- [ ] Manager’s own request escalates to next approver / owner.
- [ ] Approve/reject/cancel audited.

### Attendance
- [ ] Check-in/out creates raw punches; daily attendance derived idempotently.
- [ ] Overnight punches assign to correct workday.
- [ ] Duplicate/overlapping punch intervals rejected.
- [ ] Unresolved exceptions do not auto-deduct pay.

### Leave
- [ ] Configurable leave types; ledger for allocate/accrue/use/reverse.
- [ ] Overlap blocked; concurrent overspend prevented (reservation or lock).
- [ ] Approve updates attendance once; cancel reverses once.

### Payroll
- [ ] Structure assignment; draft→review→approved→locked run.
- [ ] Decimal-safe calc; mid-month join/exit proration; no duplicate LOP.
- [ ] Post-lock correction via adjustment only.
- [ ] No statutory invention; compliance gaps documented.

### Tasks / docs / expenses
- [ ] CRM tasks preserved; HR views do not break CRM.
- [ ] Employee docs private + authorized download.
- [ ] Expense approval ≠ payment; payroll reimbursement de-duped.

### Quality
- [ ] Feature tests listed in product Validation section.
- [ ] CRM regression suite still passes (or failures documented as env blockers).

---

## 7. Phased implementation checklist

### Phase A — Shared foundation
- [x] Migration: `companies`, `branches`, `departments`, `designations`, `employees`
- [x] Models, factories, `EmployeeService`, policies
- [x] HR permissions + role grants (`manager`, `employee` roles)
- [x] UI: Employees index/show/create/edit; HR nav entry
- [x] Seed default company + link demo users (`HrDemoSeeder`)
- [x] Tests: company isolation, employee index auth

### Phase B — Policies & approvals
- [x] `hr_policies`, assignments (effective-dated table)
- [x] `approval_requests` + `approval_actions`
- [x] No self-approval test
- [ ] Delegated approver UI + amount-limit escalation matrix (engine fields exist)

### Phase C — Attendance & leave
- [x] Shifts, punches, daily attendance, derive rules
- [x] Leave types, ledger, request/approve/reject/cancel
- [x] Approved leave → attendance day (once); cancel reverses
- [x] Tests: half-day, overnight, cancel reverse, concurrent balance
- [ ] Attendance correction UI + cutoff locking UI
- [ ] Biometric import adapter (boundary only — not built)
- [ ] Weekly-off calendar configuration UI

### Phase D — Tasks, documents, expenses
- [x] Employee document model + private upload/download service
- [x] Expense claims service (draft→submit→approve→schedule/pay) + payroll de-dupe
- [x] Tests: private doc access, expense payment dedupe
- [ ] Document/expense Inertia pages
- [ ] Task recurrence + leave coverage conflict surfacing

### Phase E — Payroll
- [x] Structures, components, assignments, draft/review/approve/lock runs
- [x] LOP from attendance; scheduled expense → via_payroll link
- [x] Tests: mid-month joining payable days, LOP count, lock immutability
- [ ] Payslip PDF + bank export UI
- [ ] Post-lock adjustment runs
- [ ] Whitelisted formula evaluator beyond fixed/percentage components
- [ ] Jurisdiction statutory plugins (explicitly not configured)

### Phase F — Dashboards & polish
- [x] HR dashboard with payroll readiness blockers
- [x] Setup docs (`docs/HRMS_SETUP.md`)
- [ ] Manager team availability / overdue HR widgets polish
- [ ] Owner trend charts (MoM payroll cost)

### Phase G — Validation
- [x] Run HR + CRM filtered tests (Docker)
- [x] Record results and env blockers
- [ ] Host PHP toolchain / full suite / `npm run build` / pint

---

## 8. Safe assumptions (recorded)

1. Single default `Company` created from `BillingSetting` / app name; `company_id` on all HR rows.
2. Existing CRM roles retained; HR permissions additive. `super_admin` = Owner for HR config/payroll.
3. `employees.user_id` nullable (employment without login) and unique when set.
4. Attendance and CRM time tracking remain separate domains.
5. Statutory calculations are stubs behind `App\Services\Payroll\Statutory\*` with explicit “not configured” behaviour.
6. Demo HR seed data only via seeders; never mixed into production migrations as silent data.

---

## 9. Outstanding compliance / product notes

- No PF, ESI, professional tax, TDS, gratuity, or labour-law schedules are present or will be invented.
- Bank export = file generation only.
- Biometric: provide CSV/API import interface; no vendor SDK assumed.
- Multi-branch data scopes need product rules for “manager of branch A seeing branch B” — default deny cross-branch unless `hr.view_all` / owner.

---

## 10. Exploration cross-check

Independent codebase exploration ([Explore CRM HRMS foundations](7b2471d7-c256-4711-9b98-1ca7f90e4207)) confirmed the pre-implementation baseline: single-tenant agency CRM; HRMS entirely missing; influencers as type enums only; reusable foundations = Spatie RBAC, `InternalReview` morph, `ActivityLogger`, Phase 9 notifications, capacity/`AgencyHoliday` calendars (not leave balances).

Additional CRM nuances (not HRMS blockers, recorded for honesty):

| Item | Status |
|------|--------|
| `time_entries.approve` / `timesheets.approve` permissions | Seeded; approve UX not wired in `TimeEntryController` (CRM timesheet gap — keep separate from attendance) |
| Architecture doc “Phase 13” | Branding + chat exist in code (`2026_10_04_*`); not fully numbered in `AGENCY_CRM_ARCHITECTURE.md` body |

Post-implementation note: this repo now **does** have `company_id` on HR tables and HR routes/UI — the exploration snapshot was pre-HRMS.

## 11. Checklist progress log

| Date | Update |
|------|--------|
| 2026-10-12 | Gap analysis authored from inspection. HRMS classified **Missing**. Implementation starting at Phase A. |
| 2026-10-12 | Phase A–C core + payroll/expense/document services implemented. UI for HR dashboard, employees, attendance, leave. Tests added in `tests/Feature/Hr/HrFoundationTest.php` (runtime results below). |
| 2026-10-12 | Exploration subagent findings cross-checked into §10; no further code changes required from that report. |

## 12. Validation results

Executed in Docker (`php:8.1-cli` + `pdo_sqlite` + `bcmath`) because host PATH has no PHP/Composer.

| Check | Result |
|-------|--------|
| `php artisan test tests/Feature/Hr` | **PASS** — 42 tests, 89 assertions (2026-10-12 QA pass) |
| `php artisan test --filter=ClientModuleTest` | **PASS** — 7 tests, 28 assertions (CRM regression sample) |
| Host `php` / `composer` | **Blocked** — not on PATH |
| `npm run build` | **PASS** (Vite build succeeded on host) |

Helper script: `scripts/docker-hr-test.sh`  
Edge matrix: `docs/testing/CRM_HRMS_EDGE_CASE_MATRIX.md` · Validation: `docs/testing/CRM_HRMS_VALIDATION_REPORT.md`
