# CRM × HRMS — Edge Case Matrix

**Date:** 2026-10-12  
**Base commit:** `4330852` (local uncommitted HRMS + QA changes on top)  
**Method:** Automated Feature tests in Docker (`php:8.1-cli`, SQLite `:memory:`, `bcmath`) + static inspection for unimplemented paths.  
**Legend:** Passed · Failed · Blocked · Not Implemented  

> Actual behaviour is what the code does today. Intended behaviour notes ambiguities without inventing statutory/payroll policy.

---

## Auth, roles, company isolation

| ID | Module | Risk | Preconditions / inputs | Role / scope | Action | Expected | Actual | Evidence | Status | Fix / limitation |
|----|--------|------|-------------------------|--------------|--------|----------|--------|----------|--------|------------------|
| AUTH-01 | Auth | High | Guest | none | GET `/hr`, attendance, leave; POST punch | Redirect login | Redirect login | `HrAuthIsolationEdgeTest` | Passed | — |
| AUTH-02 | Isolation | High | Employees A,B same company | team_member A | GET `/hr/employees/{B}` | 403 | 403 | `HrAuthIsolationEdgeTest` | Passed | — |
| AUTH-03 | Isolation | High | Emp reports to Mgr2 | manager Mgr1 | GET emp show | 403 | 403 | same | Passed | — |
| AUTH-04 | Isolation | Med | Emp reports to Mgr | manager | GET emp show | 200 | 200 | same | Passed | — |
| AUTH-05 | Isolation | High | Emp in company OTHER | manager default co | GET foreign emp | 403 | 403 | same | Passed | — |
| AUTH-06 | Isolation | High | Branch of OTHER company | super_admin | Create emp with foreign branch | Validation error | Validation error | `HrFoundationTest` | Passed | — |
| AUTH-07 | Approvals | High | Approver = requester | manager | Approve own approval | Reject self-approve | Rejected | `HrFoundationTest`, expense self-approve | Passed | — |
| AUTH-08 | Access | High | employment_status=exited | team_member | POST check-in | Rejected | Session errors / no punch | `HrAuthIsolationEdgeTest` | Passed | Punch + leave submit blocked when inactive |
| AUTH-09 | Salary | High | Manager without payroll perms | manager | View salary fields | Hidden | `can.view_salary` false on Show | Inspected UI props | Passed* | *Payload omission only; no salary UI yet |
| AUTH-10 | Owner cross-co | High | Two companies | super_admin | Access company B HR data via API | Scoped deny or explicit switch | Gate::before grants super_admin all abilities | Inspected `AuthServiceProvider` | **Not Implemented** | Super admin bypasses policy; multi-company owner UI not built |
| AUTH-11 | Cache leak | Med | Shared cache keys | — | Cross-user data in cache | Isolated | No HR cache layer found | Inspection | Not Implemented | N/A until caching added |
| AUTH-12 | Background jobs | Med | Queue jobs | — | Job uses wrong company_id | Scoped | No HR jobs yet | Inspection | Not Implemented | — |

---

## Attendance

| ID | Module | Risk | Scenario | Expected | Actual | Evidence | Status | Fix / limitation |
|----|--------|------|----------|----------|--------|----------|--------|------------------|
| ATT-01 | Attendance | High | Idempotent check-in retry (same key) | One punch | One punch | `HrAttendanceEdgeTest` | Passed | — |
| ATT-02 | Attendance | High | Checkout without check-in | Reject | Reject | same | Passed | — |
| ATT-03 | Attendance | High | Checkout timestamp before check-in | Reject | Reject | same | Passed | — |
| ATT-04 | Attendance | High | Two intervals same day | Minutes sum; no double-count | 480 min full day | same | Passed | Raw punches retained |
| ATT-05 | Attendance | High | Exactly 240 / 239 minutes | half / exception | half / exception | same | Passed | Thresholds from shift |
| ATT-06 | Attendance | High | Overnight shift midnight cross | Work date = start day; half day | work_date 2026-10-11, present_half | `HrFoundationTest` | Passed | — |
| ATT-07 | Attendance | High | Locked day new punch | Reject | Reject | `HrAttendanceEdgeTest` | Passed | — |
| ATT-08 | Attendance | Med | Punch before joining date | Reject | Reject | same | Passed | **Fixed** this cycle |
| ATT-09 | Attendance | High | Missing checkout | exception; not auto LOP | exception | same | Passed | Exceptions ≠ absent |
| ATT-10 | Attendance | Med | Late at grace boundary | 09:40 not late; 09:41 late | As expected | same | Passed | GEN shift 09:30+10m |
| ATT-11 | Attendance | Med | Concurrent two-device race | One wins / unique | Not load-tested | — | Not Implemented | Needs concurrent process test |
| ATT-12 | Attendance | Med | Break punch types | Validated intervals | Types accepted; no break overlap rules | Inspection | Not Implemented | break_start/end not fully validated |
| ATT-13 | Attendance | Med | DST / client clock far future | Policy reject | Client `punched_at` accepted if ordered | Inspection | **Ambiguity** | No max skew vs server clock |
| ATT-14 | Attendance | Med | Holiday + empty punches | holiday status | Supported when `company_holidays` row exists | Inspection + deriveDay | Passed* | *Seeded from agency holidays when present |
| ATT-15 | Attendance | High | Leave day + later punches | Leave status preserved | leave_request_id preserves status | Inspection `deriveDay` | Passed | worked_minutes may still update |
| ATT-16 | Attendance | Med | Biometric import | Validated import | Missing | — | Not Implemented | Boundary only |
| ATT-17 | Attendance | Med | Correction approval UI | Workflow | Tables exist; no UI controller | Inspection | Not Implemented | — |

---

## Leave

| ID | Module | Risk | Scenario | Expected | Actual | Evidence | Status | Fix / limitation |
|----|--------|------|----------|----------|--------|----------|--------|------------------|
| LV-01 | Leave | High | Exact balance then overspend | First OK; second fail | OK | `HrLeaveEdgeTest` | Passed | Reservation in ledger |
| LV-02 | Leave | High | Concurrent balance competition | Second fails | Second fails | `HrFoundationTest` | Passed | Not true parallel TX race |
| LV-03 | Leave | High | Complementary halves same day | Both allowed | Allowed | `HrLeaveEdgeTest` | Passed | **Fixed** overlap logic |
| LV-04 | Leave | High | Same half twice | Reject | Reject | same | Passed | **Fixed** |
| LV-05 | Leave | High | Approve → attendance once; cancel reverse | Balance restore | Restore | `HrFoundationTest` | Passed | — |
| LV-06 | Leave | High | Cancel after attendance lock | Block | Block | `HrLeaveEdgeTest` | Passed | **Fixed**; requires adjustment path |
| LV-07 | Leave | High | Repeated approve | Reject | Reject | same | Passed | — |
| LV-08 | Leave | High | Comp-off same day twice | Reject | Reject | same | Passed | — |
| LV-09 | Leave | High | Unpaid half → leave_fraction 0.5 | 0.5 on day | 0.5 | same | Passed | **Fixed** + migration |
| LV-10 | Leave | Med | Negative balance when forbidden | Reject | Reject | LV-01 | Passed | allow_negative_balance flag |
| LV-11 | Leave | Med | Accrual/carry-forward/expiry jobs | Scheduled | Not built | — | Not Implemented | — |
| LV-12 | Leave | Med | Holiday counting policies | Per leave type flags | Flags exist; not applied in day calc | Inspection | Not Implemented | counts_holidays unused in calculateDays |
| LV-13 | Leave | Med | Supporting document required | Enforce | Flag only | Inspection | Not Implemented | — |
| LV-14 | Leave | Med | Delegation / unavailable manager | Escalate | Manager null → owner queue fields only | Inspection | Partial | No delegate UI |
| LV-15 | Leave | High | Exited employee request | Reject | Reject | Code path | Passed | **Fixed** |

---

## Payroll

| ID | Module | Risk | Scenario | Expected | Actual | Evidence | Status | Fix / limitation |
|----|--------|------|----------|----------|--------|----------|--------|------------------|
| PAY-01 | Payroll | High | Mid-month join payable days | Prorate calendar days | 16 days Oct 16–31 | `HrFoundationTest` | Passed | Calendar-day basis |
| PAY-02 | Payroll | High | Absent + unpaid = LOP once each | Sum fractions | 2.00 | same | Passed | — |
| PAY-03 | Payroll | High | Half-day unpaid LOP | 0.50 | 0.50 | `HrPayrollExpenseDocEdgeTest` | Passed | **Fixed** decimal lop_days |
| PAY-04 | Payroll | High | Paid leave no LOP | 0.00 | 0.00 | same + connected | Passed | — |
| PAY-05 | Payroll | High | Lock then regenerate | Block; nets unchanged | Blocked | same | Passed | — |
| PAY-06 | Payroll | High | Expense via_payroll then markPaid | Block duplicate | Blocked | Foundation + connected | Passed | — |
| PAY-07 | Payroll | High | Statutory PF/ESI/TDS | Configured rates | `statutory: not_configured` | Snapshot | Not Implemented | **By design** — no invented rates |
| PAY-08 | Payroll | Med | Formula components / cycles | Safe eval | formula_key column only | Inspection | Not Implemented | No evaluator |
| PAY-09 | Payroll | Med | Post-lock adjustment run | Controlled correction | Missing | — | Not Implemented | Documented blocker for LV-06 recovery |
| PAY-10 | Payroll | Med | Payslip PDF / bank export | Generated | Missing | — | Not Implemented | — |
| PAY-11 | Payroll | Med | Concurrent generateDraft | One draft | Service lockForUpdate on existing | Inspection | Partial | Not multi-process tested |
| PAY-12 | Payroll | Med | Zero/negative net handling | Policy | Net can be expense-inflated; no floor | Inspection | **Ambiguity** | No configured floor |

---

## Expenses & documents

| ID | Module | Risk | Scenario | Expected | Actual | Evidence | Status | Fix / limitation |
|----|--------|------|----------|----------|--------|----------|--------|------------------|
| EXP-01 | Expense | High | Zero / negative amount | Reject | Reject | `HrPayrollExpenseDocEdgeTest` | Passed | **Fixed** |
| EXP-02 | Expense | High | Over category max | Reject | Reject | same | Passed | — |
| EXP-03 | Expense | High | Self-approval | Reject | Reject | same | Passed | — |
| EXP-04 | Expense | High | Duplicate hash claim | Reject | Reject | Service | Passed | Title/date/amount hash |
| EXP-05 | Expense | Med | Mixed currency in payroll | Policy | Same-currency filter only | PayrollService | Partial | No FX |
| EXP-06 | Expense | Med | Expense UI pages | CRUD UI | Service-only | Inspection | Not Implemented | — |
| DOC-01 | Documents | High | Other employee download | 403 | 403 | Foundation | Passed | — |
| DOC-02 | Documents | High | Disallowed MIME | Reject | Reject | Edge test | Passed | — |
| DOC-03 | Documents | High | `../` filename | Sanitized | `evil.pdf` | same | Passed | **Fixed** |
| DOC-04 | Documents | High | Cross-company doc type | Reject | Reject | same | Passed | — |
| DOC-05 | Documents | Med | Download route / UI | Authorized endpoint | Service only; no route | Inspection | Not Implemented | Wire controller route |
| DOC-06 | Documents | Med | Upload DB fail after storage | Orphan cleanup | No compensating delete | Inspection | Not Implemented | — |

---

## Tasks (CRM)

| ID | Module | Risk | Scenario | Expected | Actual | Evidence | Status | Fix / limitation |
|----|--------|------|----------|----------|--------|----------|--------|------------------|
| TSK-01 | Tasks | Med | Task completion ≠ attendance | No attendance write | No HR coupling | Inspection | Passed* | *By architecture; no auto link |
| TSK-02 | Tasks | Med | Recurring duplication | Idempotent | Recurrence missing | Inspection | Not Implemented | — |
| TSK-03 | Tasks | Med | Leave vs due-date conflict | Surface warning | Missing | — | Not Implemented | — |
| TSK-04 | Tasks | Med | CRM regression clients | Still green | 7/7 pass | `ClientModuleTest` | Passed | Sample regression |

---

## Connected journeys

| ID | Journey | Expected | Actual | Evidence | Status | Limitation |
|----|---------|----------|--------|----------|--------|------------|
| E2E-A | Onboarding docs/tasks | Full checklist | Org seed + employee link only | Inspection | Partial | No employee onboarding checklist UI |
| E2E-B | Paid leave → payroll | No LOP | No LOP | `HrConnectedWorkflowTest` | Passed | — |
| E2E-C | Unpaid half-day → payroll | 0.5 LOP once | 0.5 | same | Passed | — |
| E2E-D | Campaign expense → payroll | Reimburse once | via_payroll; markPaid blocked | same | Passed | No campaign reporting link UI |
| E2E-E | Post-lock correction | Adjustment run | Blocked cancel/regen only | Tests + gap | Partial | Adjustment module missing |
| E2E-F | Team transfer | Scope updates; history kept | Scope updates | Connected test | Passed | Pending approvals not reassigned |
| E2E-G | Exit | Access revoked; reassignment | Punch/leave blocked | Auth test | Partial | No CRM task reassignment automation |

---

## Failure / concurrency (summary)

| ID | Scenario | Status | Notes |
|----|----------|--------|-------|
| FAIL-01 | Idempotent punch retry | Passed | ATT-01 |
| FAIL-02 | Duplicate leave approve | Passed | LV-07 |
| FAIL-03 | Notification unavailable | Not Implemented | Leave/expense don’t require notifications to commit |
| FAIL-04 | Storage unavailable on upload | Not Implemented | No fault injection |
| FAIL-05 | True parallel leave double-spend | Partial | Sequential reservation; DB race not proven |
| FAIL-06 | Payroll + expense race | Partial | markPaid locked; multi-worker not proven |

---

## Ambiguities recorded (no invented rules)

1. **Proration basis:** Payable days use **calendar days** in month, not working days.  
2. **Client clock skew:** `punched_at` from client trusted if sequence-valid.  
3. **Negative net pay:** No floor/clamp policy.  
4. **Holiday/weekly-off leave counting:** Flags exist; calculator ignores them.  
5. **Super-admin multi-company:** Global Gate bypass; not a tenant switcher.
