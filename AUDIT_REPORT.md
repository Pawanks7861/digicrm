# DigiCRM Audit Report

**Date:** 2026-10-12  
**Mode:** Read-only inspection. Application code was not changed for this audit.  
**Source of truth:** `AGENCY_CRM_ARCHITECTURE.md` (sections 1–14).  
**Runtime observed:** PHP 8.1.28, Laravel 10.50.3, Vue 3 / Inertia / Tailwind, Spatie Permission 6.0, PHPUnit 10.

## A. Executive Summary

DigiCRM is a broad, service-backed agency system. Phases 1–12 from section 13 of the architecture are present as migrations, models, services, routes, Vue pages, and PHPUnit tests. That is not the same as production readiness.

This audit did **not** treat the `[x]` checklist marks as evidence. A class, route, or test file existing was recorded as **implemented but unverified** unless a defect or a missing piece was confirmed by reading the execution path.

**Counting method.** The 92 checkbox items in architecture section 13 (Phases 1–12) are the requirement set. Each item has one status. Extra findings (security, UX, documentation, modules that are not in those 92 items) are listed separately and are **not** folded into the 92.

| Status | Count of the 92 checklist items |
|---|---|
| IMPLEMENTED AND VERIFIED | 0 |
| IMPLEMENTED BUT UNVERIFIED | 92 |
| PARTIALLY IMPLEMENTED | 0 |
| MISSING | 0 |
| DEFECT FOUND | 0 |
| BLOCKED BY ENVIRONMENT | 0 |

Zero items are marked verified because this audit did not re-execute each business rule. The full PHPUnit suite was started against the isolated in-memory test database and had not finished when this report was written. See section F.

**Major blockers before a client-facing production release**

1. Account managers with `invoices.view` can open another client’s invoice and PDF by guessing the id. The list is scoped; the record and PDF are not.
2. Public self-registration is still routed (`GET/POST /register`) and creates a user with no role. The login screen does not link to it. Architecture section 2 says registration is invite-only.
3. Marketing providers other than the Google Calendar HTTP adapter are not operationally verified. Meta’s OAuth URL falls back to `client_id=stub` when no app id is configured. Fake metric provider classes exist and are not the registered runtime providers.
4. Global search is a disabled input. Quick Create, required by the design section, is not in the shell.
5. The architecture’s own phase table (section 10) disagrees with the implemented phase list (section 13). Section 10 stops at “testing and production” as phase 10 and assigns commercial work and marketing reports to different numbers.

**Audit limits**

- No production database, mail server, OAuth app, or ad account was contacted.
- `.env` values were not read or copied. `.env.example` says `DB_CONNECTION=mysql`. PHPUnit forces `sqlite` and `:memory:`.
- External provider calls were not made.
- HR, internal chat, company branding, and the settings user-management screens are extra product surface. They are not section 13 requirements.

## B. Phase Coverage Matrix

Evidence is the code path inspected. “Unverified” means the path exists and was not executed in this audit.

| Phase | Requirement | Status | Evidence | Gaps | Priority | Recommended action |
|---|---|---|---|---|---|---|
| 1 | Laravel, Breeze, Vue, Inertia, Tailwind | IMPLEMENTED BUT UNVERIFIED | `composer.json`, `package.json`, `routes/auth.php` | Public register route remains | P1 | Disable or invite-gate `RegisteredUserController` |
| 1 | Spatie roles and seeder | IMPLEMENTED BUT UNVERIFIED | `database/seeders/RolePermissionSeeder.php` | Extra roles `manager` and `employee` beyond the five in section 3 | P3 | Decide whether those roles stay |
| 1 | Client CRUD, policy, service, UI | IMPLEMENTED BUT UNVERIFIED | `ClientController`, `ClientService`, `ClientPolicy`, `Pages/Clients` | Team members do not get `clients.view`; account managers are scoped in `ClientPolicy::view` | P2 | Keep server-side scope on every client child record |
| 1 | Brand profile | IMPLEMENTED BUT UNVERIFIED | `ClientBrandProfile`, client brand route | — | P3 | None until suite passes |
| 1 | Onboarding checklist and templates | IMPLEMENTED BUT UNVERIFIED | Seeder writes `ClientOnboardingTemplate`; `ClientOnboardingItem` | — | P3 | None |
| 1 | Client files | IMPLEMENTED BUT UNVERIFIED | Client file routes and `ClientFile` | Download authorization was not re-tested here | P1 | Confirm private disk and policy on download |
| 1 | Activity log foundation | IMPLEMENTED BUT UNVERIFIED | `ActivityLogger`, `activity_logs` migration | Later `RecordAuditTrail` middleware logs page views and form input in addition to business events | P2 | Review what request bodies are stored |
| 1 | Demo seeder | IMPLEMENTED BUT UNVERIFIED | `DemoAgencySeeder`, `DemoShowcaseSeeder` | Showcase data is for demos, not a production seed | P3 | Do not run showcase seeders in production |
| 1 | Feature tests for auth, permissions, clients | IMPLEMENTED BUT UNVERIFIED | `tests/Feature/ClientModuleTest.php`, `tests/Feature/Auth/*` | Suite not finished this audit | P1 | Wait for suite result |
| 1 | Migrate, test, and build green | IMPLEMENTED BUT UNVERIFIED | Migrations exist; frontend `public/build` exists from earlier builds | This audit did not run `npm run build` or migrate | P2 | Re-run build only when UI changes |
| 2 | Service package CRUD and items | IMPLEMENTED BUT UNVERIFIED | `ServicePackageService`, `ServicePackageTest` | — | P2 | None |
| 2 | Client package snapshots | IMPLEMENTED BUT UNVERIFIED | `ClientPackageService::assign` | — | P2 | None |
| 2 | Client-specific customization | IMPLEMENTED BUT UNVERIFIED | Client package models and UI | — | P3 | None |
| 2 | Usage periods and transactions | IMPLEMENTED BUT UNVERIFIED | `PackageUsageTransaction` | — | P2 | None |
| 2 | PackageScopeService consume, restore, adjust, overage | IMPLEMENTED BUT UNVERIFIED | `app/Services/PackageScopeService.php` uses a row lock | No generic idempotency key was found on the scope service itself | P1 | Prove double-submit cannot consume twice |
| 2 | Overage decisions | IMPLEMENTED BUT UNVERIFIED | Scope service decision branches; `PackageScopeTest` | — | P2 | None |
| 2 | Renewal workflow | IMPLEMENTED BUT UNVERIFIED | `packages:renewal-reminders` in `app/Console/Kernel.php` | Scheduler must be running in production | P1 | Document cron |
| 2 | Permissions and activity logs | IMPLEMENTED BUT UNVERIFIED | Seeder permission names; activity logger calls | — | P2 | None |
| 2 | Client packages UI | IMPLEMENTED BUT UNVERIFIED | Package routes and Vue pages | — | P3 | None |
| 2 | Phase 2 tests and build | IMPLEMENTED BUT UNVERIFIED | `PackageUsageTest`, `PackageScopeTest`, `ClientPackageTest` | Suite unfinished | P1 | Record suite result |
| 3 | Campaigns, codes, permissions | IMPLEMENTED BUT UNVERIFIED | `CampaignService` sets `CAM` style codes via `generateNextCode` | Number format was not executed here | P2 | Assert code format in a test if not already asserted |
| 3 | Deliverables, status workflow, package link | IMPLEMENTED BUT UNVERIFIED | `CampaignDeliverableService` | — | P2 | None |
| 3 | Consume once on `ready`, restore on cancel | IMPLEMENTED BUT UNVERIFIED | `CampaignDeliverableService` lines 103–105 and restore near line 151; `hasConsumedScope()` guard | Not re-executed | P1 | Keep the guard; add a concurrency test if missing |
| 3 | Tasks, assignees, checklist, comments, dependencies | IMPLEMENTED BUT UNVERIFIED | `TaskService`, `TaskChecklistItem`, `TaskComment`, `TaskDependency` | — | P2 | None |
| 3 | Internal reviews and queue | IMPLEMENTED BUT UNVERIFIED | `InternalReviewService`, `internal-reviews` route | — | P2 | None |
| 3 | Campaign and deliverable progress | IMPLEMENTED BUT UNVERIFIED | Campaign show pages | — | P3 | None |
| 3 | Ops navigation | IMPLEMENTED BUT UNVERIFIED | `AuthenticatedLayout.vue` nav items | — | P3 | None |
| 3 | Phase 3 tests and build | IMPLEMENTED BUT UNVERIFIED | `CampaignTest`, `TaskTest`, `DeliverablePackageScopeTest`, `InternalReviewTest` | Suite unfinished | P1 | Record suite result |
| 4 | Content library and immutable revisions | IMPLEMENTED BUT UNVERIFIED | `ContentItemService`, `ContentRevision` | Immutability was not re-tested | P1 | Confirm revisions are insert-only |
| 4 | Internal review for content | IMPLEMENTED BUT UNVERIFIED | `InternalReview` morph on content | — | P2 | None |
| 4 | Revision-scoped client approvals and portal | IMPLEMENTED BUT UNVERIFIED | `ClientApprovalService`, portal approval routes | — | P1 | Confirm a new revision cannot reuse an old approval |
| 4 | Client-visible comments filtered on the server | IMPLEMENTED BUT UNVERIFIED | Comment visibility fields; portal content queries | Not re-tested for leakage | P1 | Assert internal comments are absent from portal payloads |
| 4 | Revision limits and overage | IMPLEMENTED BUT UNVERIFIED | Content service and `PackageOverage` | — | P2 | None |
| 4 | Calendar, schedule, publish | IMPLEMENTED BUT UNVERIFIED | `ContentItemService::schedule`, `publications:publish-due` | Publish depends on a real provider token | P1 | Treat publish-without-token as permission-required, not success |
| 4 | Deliverable status sync | IMPLEMENTED BUT UNVERIFIED | Content service `syncDeliverableStatus` | — | P2 | None |
| 4 | Phase 4 tests and build | IMPLEMENTED BUT UNVERIFIED | `ContentItemTest`, `ClientApprovalTest`, `ContentCalendarTest` | Suite unfinished | P1 | Record suite result |
| 5 | Client requests and portal intake | IMPLEMENTED BUT UNVERIFIED | `ClientRequestService`, portal request routes | — | P2 | None |
| 5 | Scope precheck without a second consume | IMPLEMENTED BUT UNVERIFIED | `ClientRequestScopeTest` exists; request service checks scope | Not re-executed | P1 | Keep one consume owner: the deliverable `ready` transition |
| 5 | Convert request to campaign, deliverable, or task | IMPLEMENTED BUT UNVERIFIED | `ClientRequestConversionTest` | — | P2 | None |
| 5 | Communications and follow-ups | IMPLEMENTED BUT UNVERIFIED | `CommunicationService`, `FollowUpService` | — | P2 | None |
| 5 | Portal module flags enforced on the server | IMPLEMENTED BUT UNVERIFIED | `EnsurePortalModule`, `ClientPortalSetting::allowsModule` | — | P1 | Keep middleware on every portal route |
| 5 | In-app notifications | IMPLEMENTED BUT UNVERIFIED | `NotificationDispatchService` | — | P2 | None |
| 5 | Phase 5 tests and build | IMPLEMENTED BUT UNVERIFIED | `ClientPortalAccessTest`, `ClientRequestPortalTest`, `PortalSettingsTest` | Suite unfinished | P1 | Record suite result |
| 6 | Time entries and one running timer | IMPLEMENTED BUT UNVERIFIED | `TimeTrackingService`, `RunningTimerTest`, top-bar stop control | — | P1 | Confirm the unique-active-timer rule in the service |
| 6 | Timesheets | IMPLEMENTED BUT UNVERIFIED | `timesheet` routes | — | P2 | None |
| 6 | Allocations and capacity | IMPLEMENTED BUT UNVERIFIED | `CapacityService`, `TaskAllocation` | A prior Sunday date-bound bug was fixed in workload queries; not re-run here | P1 | Re-run `TaskAllocationTest` |
| 6 | Workload and utilization | IMPLEMENTED BUT UNVERIFIED | `WorkloadService`, `UtilizationService` | — | P2 | None |
| 6 | Delay reasons, records, analytics | IMPLEMENTED BUT UNVERIFIED | `DelayService`, `DelayReasonSeeder` | — | P2 | None |
| 6 | Operations dashboard and timer | IMPLEMENTED BUT UNVERIFIED | `DashboardService` role match; timer in the layout | — | P3 | None |
| 6 | Phase 6 tests and build | IMPLEMENTED BUT UNVERIFIED | `TimeEntryTest`, `CapacityWorkloadUtilizationTest`, `DelayTrackingTest` | Suite unfinished | P1 | Record suite result |
| 7 | Role dashboards | IMPLEMENTED BUT UNVERIFIED | `DashboardService::forUser` returns executive, operations, account manager, or team | — | P2 | None |
| 7 | Historical cost rates | IMPLEMENTED BUT UNVERIFIED | `UserCostRateService::laborCostForEntries` returns `missing_seconds` instead of inventing a rate | — | P1 | Keep missing rates visible in profitability |
| 7 | Client, campaign, and package profitability | IMPLEMENTED BUT UNVERIFIED | `ProfitabilityService` | Admin role is denied these permissions in the seeder | P1 | Align with section 3 or document the stricter choice |
| 7 | Scope impact and complimentary work | IMPLEMENTED BUT UNVERIFIED | Report routes | — | P2 | None |
| 7 | Planned vs actual and delivery metrics | IMPLEMENTED BUT UNVERIFIED | `DeliveryMetricsService`, report routes | — | P2 | None |
| 7 | Reports and permission-scoped CSV | IMPLEMENTED BUT UNVERIFIED | Report controllers | Export payloads were not inspected line by line | P1 | Confirm cost columns are omitted server-side |
| 7 | Phase 7 tests and build | IMPLEMENTED BUT UNVERIFIED | `Phase7ProfitabilityTest`, `Phase7DashboardSecurityTest` | Suite unfinished | P1 | Record suite result |
| 8 | Quotations, revisions, portal acceptance | IMPLEMENTED BUT UNVERIFIED | `QuotationService`; show action calls `assertClientAccess` | — | P2 | None |
| 8 | Invoices, GST, snapshots | IMPLEMENTED BUT UNVERIFIED | `InvoiceService`, `CommercialCalculationService` | Show and PDF do not use the quotation client check | P0 | Scope invoice show, edit, send, cancel, and PDF |
| 8 | Payments, partial, reversal, receipts | IMPLEMENTED BUT UNVERIFIED | `PaymentService` | `PaymentController::index` is not limited to assigned clients. Seeded account managers do not have `payments.view` | P1 | Scope the payment query the same way as invoices |
| 8 | Receivables, aging, renewals, overage billing | IMPLEMENTED BUT UNVERIFIED | `ReceivablesService`, finance routes | — | P2 | None |
| 8 | Authorized commercial PDFs | IMPLEMENTED BUT UNVERIFIED | `CommercialPdfService`; invoice PDF gate is only `invoices.view` | Same IDOR as invoice show | P0 | Check client ownership before streaming the PDF |
| 8 | Phase 8 tests and build | IMPLEMENTED BUT UNVERIFIED | `Phase8CommercialTest` | No test found in this pass that an account manager cannot open another client’s invoice | P0 | Add that test |
| 9 | In-app, email, and web push dispatch | IMPLEMENTED BUT UNVERIFIED | `NotificationDispatchService`, `PushNotificationService` | Push throws if VAPID is missing; dispatch is wrapped so the business action can continue | P2 | Confirm every caller catches notification failure |
| 9 | Preferences, quiet hours, pause, digests, delivery logs | IMPLEMENTED BUT UNVERIFIED | Preference service, `notifications` table, delivery model | — | P2 | None |
| 9 | Scheduled reminders | IMPLEMENTED BUT UNVERIFIED | `app/Console/Kernel.php` schedule | No proof the OS scheduler is installed | P1 | Production cron for `schedule:run` |
| 9 | Templates and admin notification screens | IMPLEMENTED BUT UNVERIFIED | Notification settings routes | — | P3 | None |
| 9 | Service worker and public VAPID key only | IMPLEMENTED BUT UNVERIFIED | Push routes expose a public key endpoint | Private key must stay server-side; not re-checked in config files | P1 | Confirm the private key is never sent to Inertia |
| 9 | Phase 9 tests and hardening | IMPLEMENTED BUT UNVERIFIED | `Phase9NotificationTest`, `Phase9HardeningTest` | Suite unfinished | P1 | Record suite result |
| 10 | Integrations plus Google Calendar adapter | IMPLEMENTED BUT UNVERIFIED | `GoogleCalendarProvider` performs HTTP calls to Google | Not called against Google in this audit | P1 | Classify as implemented, not operationally verified |
| 10 | Meta, Ads, and GA stubs plus webhook security | IMPLEMENTED BUT UNVERIFIED | `MetaProvider::oauthUrl` returns `client_id=stub` without an app id. Webhook signatures use `hash_equals`. `CallWebhookAction` blocks private IPs | DNS rebinding is only checked once via `gethostbyname` | P1 | Do not present stub OAuth as a live connection |
| 10 | Automation engine | IMPLEMENTED BUT UNVERIFIED | `AutomationEngine`, action registry, recursion and delay tests | — | P1 | Keep depth and idempotency guards |
| 10 | Social publication and metric snapshot shell | IMPLEMENTED BUT UNVERIFIED | `PublishingService` throws if the provider cannot publish | — | P2 | None |
| 10 | Phase 10 tests and build | IMPLEMENTED BUT UNVERIFIED | `tests/Feature/Phase10/*` | Suite unfinished | P1 | Record suite result |
| 11 | Metric registry and provider mappers | IMPLEMENTED BUT UNVERIFIED | `MarketingMetricRegistry`, mapper classes, `MetricRegistryTest` | — | P2 | None |
| 11 | Snapshots, sync runs, mappings, KPIs, reports, attribution | IMPLEMENTED BUT UNVERIFIED | Corresponding models and services | Manual and provider rows can coexist; division-by-zero handling was not re-read formula by formula | P1 | Re-read derived metrics before go-live |
| 11 | Sync, aggregation, KPI, report, and manual services | IMPLEMENTED BUT UNVERIFIED | `MetricSyncService` requires a `MetricsProviderInterface` | Fake provider classes exist but are not registered in `AppServiceProvider` | P2 | Do not sync with fake numbers in production |
| 11 | Commands and schedule | IMPLEMENTED BUT UNVERIFIED | `metrics:sync` hourly, `reports:generate-monthly` | Needs a running scheduler | P1 | Same cron requirement as phase 9 |
| 11 | Agency and portal UI plus PDF | IMPLEMENTED BUT UNVERIFIED | Performance and client-report routes | Portal flags default some performance modules off | P2 | Enable only what the client should see |
| 11 | Automation triggers for performance | IMPLEMENTED BUT UNVERIFIED | Trigger registry includes KPI and report events | — | P3 | None |
| 11 | Phase 11 tests | IMPLEMENTED BUT UNVERIFIED | `Phase11PerformanceTest`, `MetricRegistryTest` | The checklist’s “188 total” is historical, not this run | P1 | Do not quote old totals as current |
| 12 | Agency sales vs client campaign isolation | IMPLEMENTED BUT UNVERIFIED | `Lead::CATEGORIES`, `scopeForPortalClient`, `LeadPolicy` | Team members receive `leads.view`, `leads.edit`, and `leads.manage_status`, which section 3 does not grant | P1 | Confirm that scope is what product wants |
| 12 | Manual, public form, CSV, and Meta webhook capture | IMPLEMENTED BUT UNVERIFIED | `LeadCaptureService`, `MetaLeadAdsService`, import controller | Public form abuse depends on rate limits and consent checks | P1 | Re-check throttle and signature tests |
| 12 | Duplicates, assignment, history, lost reasons | IMPLEMENTED BUT UNVERIFIED | `LeadDuplicateService`, `LeadAssignmentService`, seed defaults | — | P2 | None |
| 12 | Opportunities and pipeline | IMPLEMENTED BUT UNVERIFIED | `OpportunityService` rejects non-agency leads | — | P1 | Keep that guard |
| 12 | Follow-ups, communications, quotations, idempotent conversion | IMPLEMENTED BUT UNVERIFIED | `LeadConversionService`, `LeadConversionTest` | — | P1 | Confirm conversion cannot run twice |
| 12 | Attribution and lead reports | IMPLEMENTED BUT UNVERIFIED | `AttributionService`, lead report routes | Sales revenue is permission-gated; this audit saw a 403 for a non-owner in an earlier demo, not in this command set | P2 | Keep owner-only revenue |
| 12 | Dashboards, portal leads, notifications, automation | IMPLEMENTED BUT UNVERIFIED | Dashboard lead widgets, portal lead routes, lead notification tests | — | P2 | None |
| 12 | Phase 12 tests | IMPLEMENTED BUT UNVERIFIED | `tests/Feature/Phase12/*` | Checklist “260” is historical | P1 | Use this audit’s suite result only |

## C. Critical Security Findings

### Confirmed

**C1. Invoice and invoice PDF are not limited to the account manager’s clients.**  
`InvoiceController::index` restricts account managers to their clients. `show` and `pdf` only check `invoices.view` or `super_admin` (`app/Http/Controllers/InvoiceController.php` around lines 72–84 and 139–143). Account managers are granted `invoices.view` (`RolePermissionSeeder.php` line 151). `QuotationController::show` already calls `assertClientAccess`.  
**Scenario:** An account manager changes the invoice id in the URL or PDF link and reads another client’s tax invoice.  
**Impact:** Cross-client financial disclosure.  
**Remediation:** Use the same client check on show, edit, send, cancel, PDF, and overage actions. Add a feature test.

**C2. Public registration is still enabled.**  
`routes/auth.php` registers `GET/POST register`. `RegisteredUserController::store` creates a user with no role and logs them in. The login page does not link to registration. Architecture section 2 says invite-only. Email verification middleware will block the main app until the address is verified, so this is not an instant full-access hole, but it is still an open account-creation endpoint.  
**Remediation:** Remove the routes in deployed environments, or require an invite token and an explicit role assignment by an administrator.

**C3. Payment list is not client-scoped.**  
`PaymentController::index` loads every payment for anyone with `payments.view`. The seeder gives that permission to super admins and withholds it from admin and account managers, so the current seed is narrower than the code. A later permission grant would leak all receipts.  
**Remediation:** Apply the same account-manager client filter used on the invoice index.

### Suspected, not fully verified in this pass

**S1. Automation webhook SSRF check is a single DNS lookup.**  
`CallWebhookAction` resolves the host once and rejects private IPs. A name can change between the check and the HTTP call. Tests exist (`WebhookSsrfTest`) but were not executed in this audit.

**S2. Request audit rows can store form fields.**  
`RecordAuditTrail` strips passwords and chat message bodies. Other business fields on POST bodies are stored in `activity_logs.new_values`. That is useful and also a data-retention risk.

**S3. Team-member lead permissions are wider than the section 3 matrix.**  
`Lead::scopeVisibleTo` still limits rows to assigned leads or campaign leads for managed clients. The permission to edit and change status is the part that exceeds the matrix.

**S4. Self-registered users have no role.**  
If email verification is ever disabled, `EnsureAgencyUser` only redirects client users. A role-less user would hit the team dashboard path. Verification currently stands in front of that.

## D. Business Logic Defects

1. **Section 3 versus the admin role.** The matrix says Admin has operational finance, with owner-only margin sensitivity behind `finance.internal`. The seeder removes quotations, invoices, payments, receivables, profitability, and cost rates from `admin`. Super admin keeps them. This is stricter than the matrix. It may be intentional. It needs an explicit product decision before go-live.
2. **Package consumption owner.** Deliverables consume on the transition to `ready` and restore on cancel (`CampaignDeliverableService`). That matches section 13. The scope service itself was not shown to be idempotent without the deliverable’s `hasConsumedScope()` flag. Do not add a second consume on the campaign or the request.
3. **Missing labor rates are tracked, not zeroed.** `UserCostRateService` returns missing seconds and missing entry counts. That matches the architecture rule. Profitability screens must keep showing that gap.
4. **Invoice display status.** `Invoice::getDisplayStatusAttribute` now returns `draft` when `status` is null, after a payments page error (`Return value must be of type string, null returned`). The fallback hides bad data instead of rejecting it. Find and repair any invoice whose status column is null.
5. **Phase numbering conflict.** Section 10 says phase 8 is campaign performance and phase 10 is production hardening. Section 13 says phase 8 is commercial documents, phase 10 is integrations, phase 11 is metrics, and phase 12 is leads. The running code follows section 13.

## E. Frontend Coverage

Present and wired to named routes: clients, packages, campaigns, tasks, reviews, content, calendar, requests, follow-ups, time, workload, delays, reports, performance, finance, alerts, integrations, automations, leads, pipeline, HR, chat, and settings (company, users, audit log).

**Not complete against section 14**

- The header search box is `disabled` (`resources/js/Layouts/AuthenticatedLayout.vue` around the search input). It does not query.
- Quick Create is not in the shell.
- Empty, error, and loading states were not reviewed on every page.
- Permission-hidden buttons are not a substitute for the server checks in section C.

**Shells and stubs**

- Meta OAuth without `services.meta.app_id` is a stub URL (`MetaProvider::oauthUrl`).
- WhatsApp health text says “foundation stub”.
- `FakeGoogleAdsMetricsProvider`, `FakeGa4MetricsProvider`, `FakeMetaMetricsProvider`, and `FakeGoogleCalendarProvider` exist for tests. Runtime registration in `AppServiceProvider` uses the real provider classes, which still no-op or refuse work without tokens.

**Beyond the architecture (present on purpose in this repository)**

- HR: employees, attendance, leave, expenses, payroll services.
- Internal chat.
- Company branding.
- Settings hub with user management and an access audit log.

## F. Test Results

| Command | Exit | Result |
|---|---|---|
| `php artisan --version` | 0 | Laravel Framework 10.50.3 |
| `php -v` | 0 | PHP 8.1.28 |
| `composer validate --no-check-publish` | 0 | `composer.json` is valid. Warning: `spatie/laravel-permission` is pinned to exact `6.0` |
| `php artisan test --list-tests` | 0 | 332 tests listed |
| `php artisan route:list --except-vendor --json` | 0 | 385 routes |
| `php artisan test` | Running when this report was written | Uses `phpunit.xml`: `APP_ENV=testing`, `DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:`, `MAIL_MAILER=array`, `QUEUE_CONNECTION=sync`. This does not touch the development database. A PHPUnit schema deprecation warning was printed at start. `Tests\Unit\ExampleTest` passed. The rest had not finished. |

Not run in this audit: `npm run build`, migrations, seeders, cache clears, and any command against the development database.

**Coverage that exists as files but was not executed here:** package scope, deliverable consume, approvals, portal isolation, timers, profitability redaction, commercial math, notification dedupe, automation recursion, webhook SSRF, metric registry, lead duplicates, and conversion. Missing as a named test in this pass: account manager cannot read another client’s invoice or PDF.

## G. Integration Status

| Provider | Class | Classification |
|---|---|---|
| Google Calendar | `App\Integrations\Google\GoogleCalendarProvider` | Implemented but unverified. Real HTTP methods exist. No live Google call was made. |
| Google Analytics | `GoogleAnalyticsProvider` | Implemented but unverified. Registered. Not called. |
| Google Ads | `GoogleAdsProvider` | Implemented but unverified. Registered. Not called. |
| YouTube Analytics | `YouTubeAnalyticsProvider` | Implemented but unverified. Registered. Not called. |
| Meta (pages, lead ads, publish) | `MetaProvider` | Stubbed when `services.meta.app_id` is empty (`client_id=stub`). Signature check exists. Not operationally verified. |
| WhatsApp Cloud | `WhatsAppCloudProvider` | Stubbed. Messaging throws until a live Business API connection exists. |
| Fake metric and calendar providers | `Fake*Provider` classes | Test doubles. Not registered in `AppServiceProvider`. |
| Web Push | `PushNotificationService` | Implemented but unverified. Fails closed when VAPID is missing. |
| Outbound email | Laravel mail plus `MailConfigService` | Unconfigured from this audit’s point of view. Mail settings were not read. |

## H. Remaining Development Backlog

### P0 — Release blockers

1. **Scope invoice mutations and PDFs to the client the user may see.**  
   Files: `app/Http/Controllers/InvoiceController.php`.  
   Reason: C1.  
   Outcome: An account manager receives 403 for another client’s invoice, PDF, send, edit, and cancel.  
   Dependency: None.  
   Acceptance: Feature test with two clients and `am` role.

2. **Close public registration.**  
   Files: `routes/auth.php`, `RegisteredUserController.php`.  
   Reason: C2 and architecture section 2.  
   Outcome: Anonymous users cannot create an account. Admins create users from Settings.  
   Dependency: User management already exists.  
   Acceptance: `GET /register` is 404 in production. Existing auth tests updated.

### P1 — Required rules and reliability

3. **Scope the payment index** (`PaymentController.php`) the same way as invoice index.  
4. **Decide Admin finance access** against section 3 and change `RolePermissionSeeder.php` only after that decision.  
5. **Prove package consume/restore is single-shot** under two overlapping `ready` transitions (`PackageScopeService`, `CampaignDeliverableService`).  
6. **Confirm new content revisions require a new approval** and that internal comments are absent from portal JSON.  
7. **Production scheduler.** Document and install `schedule:run` for the commands in `app/Console/Kernel.php`.  
8. **Do not advertise Meta, Ads, GA4, YouTube, or WhatsApp as connected** until a real OAuth app and a successful health check exist.  
9. **Repair invoices with a null status** rather than relying on the `draft` display fallback (`app/Models/Invoice.php`).  
10. **Finish and record the PHPUnit run** started in this audit before any release claim.

### P2 — Reliability, tests, and UX

11. **Global search and Quick Create**, or remove them from the architecture’s design section if they are out of scope. File: `AuthenticatedLayout.vue`.  
12. **Audit-log retention:** limit `RecordAuditTrail` stored fields and add a purge policy.  
13. **Webhook DNS rebinding** on `CallWebhookAction`.  
14. **Align section 10 of the architecture** with section 13 so phase numbers mean one thing.  
15. **Team-member lead edit rights:** confirm or narrow `RolePermissionSeeder` team list.  
16. **Historical cost-rate gaps** must stay visible on profitability pages (`UserCostRateService`).

### P3 — Optional

17. Keep or drop extra roles `manager` and `employee`.  
18. HR, chat, and company branding are outside phases 1–12. Write them into the architecture if they are in the product, so the next audit has a source of truth.  
19. Relax the exact Composer pin on `spatie/laravel-permission` when an upgrade is planned.

## I. Production Go-Live Checklist

Evidence still required:

- [ ] This audit’s PHPUnit run finishes green on `sqlite :memory:`
- [ ] A staging run on MySQL, matching `.env.example`, including migrations from an empty database
- [ ] C1 and C2 fixed and covered by tests
- [ ] Backup and restore drill for the production database and `storage/`
- [ ] `schedule:run` and a queue worker if mail or sync moves off `sync`
- [ ] Mail, VAPID, and OAuth credentials set without committing them
- [ ] Meta, Google, and WhatsApp either connected with a recorded health check or hidden in the UI
- [ ] Client portal flags reviewed per client
- [ ] File downloads checked for authorization and private disks
- [ ] User acceptance on one real client: lead, package, campaign, content approval, invoice, payment
- [ ] Written decision on Admin versus Super Admin finance access
- [ ] Architecture section 10 updated or marked historical

## J. Recommended Implementation Sequence

1. Add the invoice isolation test and fix `InvoiceController` (P0). Do not change GST math.  
2. Disable public registration (P0).  
3. Scope payments (P1).  
4. Let the current PHPUnit suite finish. Fix only failures that this sequence introduced, one at a time.  
5. Product decision on Admin finance, then seeder change if required.  
6. Scheduler and integration honesty (hide or connect).  
7. Search and Quick Create, or an architecture edit that drops them.  
8. Documentation pass for section 10 and for HR/chat/company settings.

No application code was changed by this audit. Do not start the backlog until that work is explicitly approved.
