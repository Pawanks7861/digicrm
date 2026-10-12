# CRM × HRMS — Validation Report

**Date:** 2026-10-12  
**Base git commit:** `4330852` (first commit)  
**Code under test:** local working tree with HRMS implementation + QA fixes (uncommitted at validation time)  
**Environment:** Docker `php:8.1-cli`, extensions `pdo_sqlite` + `bcmath`, PHPUnit SQLite `:memory:` (`phpunit.xml`)  
**Host note:** Windows host PATH had no `php`/`composer`; validation used Docker bind-mount.

## Commands executed

```bash
docker run --rm -v "${PWD}:/app" -w /app php:8.1-cli bash ./scripts/docker-hr-test.sh
# internally:
#   php artisan test tests/Feature/Hr
#   php artisan test --filter=ClientModuleTest

npm run build   # host Node.js — PASS
```

## Results summary

| Suite | Result |
|-------|--------|
| `tests/Feature/Hr` | **42 passed** (89 assertions) |
| `ClientModuleTest` | **7 passed** (28 assertions) |
| `npm run build` | **PASS** |
| Host PHPUnit without Docker | **Blocked** — PHP/Composer not on PATH |
| Full CRM Feature suite | **Not run** this pass (sample regression only) |
| Laravel Pint | **Not run** |
| Real email/push/bank/biometric | **Not verified** (mocked/absent by design) |

## Passed (high-risk automated)

- Company org FK isolation; employee show scope (self/team/other/cross-company)
- Unauthenticated HR route redirect
- Self-approval blocked (leave approval + expense)
- Attendance: idempotency, ordering, multi-interval sum, thresholds, overnight, lock, join-date, missing checkout ≠ LOP, grace late flag
- Leave: reservation/overspend, complementary halves, same-half conflict, lock cancel block, comp-off once, repeated approve
- Payroll: mid-month join, LOP fractions, paid leave no LOP, lock immutability, expense via_payroll de-dupe
- Documents: peer 403, MIME reject, filename sanitize, cross-company type reject
- Connected: paid leave journey, unpaid half LOP, team transfer scope, expense once

## Failed then fixed (this cycle)

| Issue | Root cause | Fix | Revalidation |
|-------|------------|-----|--------------|
| Same half-day leave allowed twice | Weak date/unit overlap query | `whereDate` overlap + half occupancy | `HrLeaveEdgeTest` PASS |
| Half-day unpaid counted as 1 LOP | Integer day count | `leave_fraction` + decimal `lop_days` | Payroll edge PASS |
| Zero/negative expenses accepted | Missing validation | Amount must be &gt; 0 | Expense edge PASS |
| Punch after exit / before join | No employment window check | Active + join/exit guards | Auth/attendance PASS |
| Cancel leave after lock | Balance reversed while day locked | Block cancel if any locked day | Leave edge PASS |
| Path traversal in download name | Raw original filename | `basename` + sanitize | Doc edge PASS |
| Leave overlap complementary halves | Over-broad conflict | Half occupancy intersect | Leave edge PASS |

## Blocked / not implemented (explicit)

| Item | Requirement to unblock |
|------|------------------------|
| Post-lock payroll adjustment runs | Product rules for arrears/off-cycle + UI/service |
| Statutory calculations | Jurisdiction rates supplied explicitly (do not invent) |
| Super-admin multi-company isolation | Tenant context switch; reconsider Gate::before |
| Expense/document HTTP pages & download routes | Controllers + Vue (services exist) |
| Leave holiday/weekly-off counting | Apply `counts_holidays` / `counts_weekly_offs` in calculator |
| Accrual/expiry/reminder jobs | Scheduler + idempotent job design |
| Biometric import | Validated CSV/API adapter |
| True parallel race proofs | Multi-process / stress harness |
| Full CRM suite + Pint on host | Install PHP 8.1+ and Composer on PATH |

## Automated vs manual

| Coverage type | Scope |
|---------------|-------|
| Automated | Service + HTTP Feature tests listed above |
| Manual UI | Not executed this pass |
| External integrations | None verified live (no bank, SMTP, biometric) |

## Remaining release blockers (HRMS MVP)

1. No controlled **post-lock adjustment** path (operations cannot correct locked periods safely).  
2. **Document download route** and expense UI not exposed (services only).  
3. **Statutory** payroll explicitly not configured — do not claim compliance.  
4. Run **full** CRM Feature suite before production cutover.  
5. Confirm host/production PHP has **bcmath** enabled.

## Matrix

Detailed scenario statuses: [`CRM_HRMS_EDGE_CASE_MATRIX.md`](./CRM_HRMS_EDGE_CASE_MATRIX.md).
