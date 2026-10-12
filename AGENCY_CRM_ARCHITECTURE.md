# DigiCRM — Marketing Agency Operating System

**Status:** Greenfield build  
**Stack:** Laravel 10 · PHP 8.1+ · Inertia.js · Vue 3 · Tailwind CSS · Spatie Permission · Sanctum  
**Product name:** DigiCRM

---

## 1. Existing Architecture

This repository started empty. DigiCRM is built from scratch as a Marketing Agency Operating System (not a traditional sales-only CRM).

### Stack decisions

| Layer | Choice | Why |
|-------|--------|-----|
| Backend | Laravel 10 | Matches local PHP 8.1; mature ecosystem |
| SPA bridge | Inertia.js + Vue 3 | Modern SaaS UX without a separate API SPA |
| Styling | Tailwind CSS | Consistent design tokens, responsive |
| Auth | Laravel Breeze (Inertia Vue) | Session auth + CSRF for staff & clients |
| RBAC | Spatie Laravel Permission | Roles + permissions matrix |
| Soft deletes | Eloquent `SoftDeletes` | Preserve business records |
| Tests | PHPUnit | Critical flow coverage |
| DB | MySQL (WAMP) | Production-ready local stack |

### Naming conventions

- Models: singular PascalCase (`Client`, `Campaign`)
- Tables: plural snake_case (`clients`, `client_brand_profiles`)
- Controllers: resource + domain (`ClientController`, `ClientOnboardingController`)
- Services: `App\Services\*` for business rules
- Policies: `App\Policies\*`
- Form requests: `App\Http\Requests\*`
- Vue pages: `resources/js/Pages/{Module}/Index.vue`
- Layouts: `AuthenticatedLayout` (agency), `ClientPortalLayout` (clients)

---

## 2. Reusable Existing Modules

None at start. Everything below is new.

After Phase 1 scaffold, reusable foundations:

- Breeze auth (login, register disabled in prod for clients; invite-only)
- Spatie roles/permissions
- Soft-delete base patterns
- Activity log helper
- Shared UI: AppLayout, sidebar, topbar, badges, empty states, drawers

---

## 3. Role & Permission Matrix

| Capability | Super Admin | Admin | Account Manager | Team Member | Client |
|------------|:-----------:|:-----:|:---------------:|:-----------:|:------:|
| Manage users & roles | ✓ | ✓* | | | |
| Agency settings | ✓ | ✓ | | | |
| All clients | ✓ | ✓ | assigned only | assigned tasks only | own |
| Financial / costing / profitability | ✓ | ✓* | | | |
| Packages & overages | ✓ | ✓ | view assigned | | view own package usage |
| Campaigns / deliverables | ✓ | ✓ | assigned clients | assigned work | exposed only |
| Tasks | ✓ | ✓ | assign | own + collab | no |
| Internal review | ✓ | ✓ | ✓ | submit | no |
| Client approvals | ✓ | ✓ | ✓ | | ✓ |
| Client requests | ✓ | ✓ | ✓ | | create/view own |
| Time tracking | ✓ | ✓ | view team | own | no |
| Workload | ✓ | ✓ | limited | own | no |
| Reports | ✓ | ✓ | client reports | | portal reports |
| Client portal | | | | | ✓ |

\*Admin: operational financials; Super Admin retains payroll/margin sensitivity via `finance.internal` permission.

### Roles (seeded)

1. `super_admin`
2. `admin`
3. `account_manager`
4. `team_member`
5. `client`

Team members may also carry job titles (designer, editor, etc.) as user metadata — not separate RBAC roles.

---

## 4. Core Workflow

```
Lead → Proposal → Client → Contract/Package → Campaign → Deliverables
  → Tasks → Internal Review → Client Approval → Publishing
  → Performance → Monthly Report → Invoice / Renewal
```

Supporting: Client Requests · Client Portal · Content Calendar · Revisions · Files · Comments · Notifications · Activity · Time Tracking · Workload · Scope Tracking · Reports

---

## 5. Database Schema (Phased)

### Phase 1 (now)

- `users` — +`role_meta`, `job_title`, `phone`, `is_active`, soft deletes
- `roles` / `permissions` / pivots (Spatie)
- `clients`
- `client_contacts`
- `client_brand_profiles`
- `client_onboarding_templates`
- `client_onboarding_items`
- `client_files` (minimal folder structure)
- `activity_logs` (foundation)

### Phase 2

- `service_packages`, `service_package_items`
- `client_packages`, `client_package_usages`
- `scope_decisions` (complimentary / charge / reject / quotation)

### Phase 3

- `campaigns` (+ `CAM-YYYY-####` codes)
- `campaign_deliverables` (package-linked; consumes usage on commit to `ready`)
- `tasks`, `task_assignees`, `task_checklist_items`, `task_comments`, `task_dependencies`
- `internal_reviews` (polymorphic; ends at Ready for Client Review)

**Phase 3 rule:** Campaigns do not consume package usage. Deliverables do (via `PackageScopeService`, polymorphic source = deliverable). Consumption is idempotent (`scope_consumed_at`).

### Phase 4

- `content_items` — creative produced for a deliverable (1:N with deliverables)
- `content_revisions` — immutable version history (file + caption/hashtags/CTA snapshots)
- `client_approvals` — approval of a **specific revision** (pending/approved/changes_requested/cancelled/superseded)
- `content_comments` — `visibility`: `internal` | `client_visible` (portal never receives internal)
- Internal reviews reuse polymorphic `internal_reviews` (+ optional `content_revision_id`)
- Package items gain `included_revisions` (client-requested revision allowance; internal edits do not consume)
- Client portal (authenticated `client` role + `client_id` scoping) — Dashboard, Approvals, Content, Campaigns, Files
- Content calendar (month/week) — schedule after client approval; mark published manually (no social APIs)

**Phase 4 rules:**
1. Deliverables remain the package-usage source. Revisions never consume package allowance.
2. Approval is revision-scoped; a new revision requires a fresh approval request.
3. Client portal payloads filter `client_visible` only — never hide internal data in Vue alone.
4. Package revision overages reuse `PackageOverage` (polymorphic source = ClientApproval / ContentItem).

### Phase 5

- `client_requests` (`REQ-YYYY-####`) — client-submitted work intake
- `client_request_comments` / attachments (via `client_files` + pivot or morph)
- `client_communications` — logged calls/WhatsApp/email/meetings
- `follow_ups` — polymorphic follow-up queue
- `client_portal_settings` — per-client module toggles (enforced server-side)
- Laravel `notifications` table — in-app notification center (no Web Push)
- `client_request_id` on deliverables/tasks for conversion lineage

**Phase 5 rules:**
1. Request scope is a **precheck** only. Package usage is consumed by the Deliverable at `ready` (or by the Request itself only when converting to a standalone Task with no deliverable). Never double-consume.
2. Portal module settings + policies block direct URLs — not sidebar-only.
3. Request/comms comments default internal; portal payloads filter `client_visible` only.
4. Notifications are event-driven database notifications (deduplicated; not created on page render).

### Phase 6

- `time_entries` — timer + manual logging (duration in **seconds**; billable flag; soft deletes)
- `task_allocations` — per-assignee estimated minutes (never multiply full estimate × assignees)
- `user_work_capacities` — hours/day + working days (effective dated)
- `agency_holidays` — non-working capacity days
- `delay_reasons` (seeded) + polymorphic `delay_records` (history preserved)
- Workload / utilization / delay analytics (computed in services, not Vue)

**Phase 6 calculation definitions (source of truth):**

| Metric | Formula |
|--------|---------|
| Available Capacity (period) | Σ working days in range × hours_per_day × 60 minutes, excluding weekends (unless in working_days) and agency holidays |
| Allocated Hours | Σ `task_allocations.allocated_minutes` for incomplete tasks due (or overlapping) in period |
| Remaining Capacity | Available − Allocated (can be negative) |
| Planned Utilization % | Allocated / Available × 100 (0 if Available = 0) |
| Actual Utilization % | Logged productive minutes (non-deleted entries) / Available × 100 |
| Billable Utilization % | Billable logged minutes / Available × 100 |
| Actual Task Time | Σ valid `time_entries.duration_seconds` for task (exclude rejected) |
| Variance | Actual − Estimated |
| Delay Duration | `ended_at ?? now` − `started_at` |
| Overdue | due_date < today AND status ∉ {completed, cancelled} (computed, not stored) |

**Workload status thresholds** (`config/agency.php`): available &lt;60%, normal 60–85%, near_capacity 85–100%, overloaded &gt;100%.

**Phase 6 rules:**
1. One running timer per user (DB lock). Running = `ended_at` null.
2. Task estimate lives on `tasks.estimated_minutes`; actual is aggregated, not duplicated.
3. Blocked / waiting_for_client requires/creates delay records via `DelayService`.
4. Client portal has **no** Phase 6 routes. No salary/cost/profitability.
5. Utilization is operational planning only — never an employee ranking score.

### Phase 7

- Role-specific dashboards (executive / operations / AM / team) — permission-scoped payloads
- `user_cost_rates` — **internal hourly cost** (not salary); historical effective ranges; no overlaps
- `operational_costs` — lightweight direct non-labor costs (freelancer, studio, etc.)
- Campaign `standalone_charge` — when false, `client_charge` is **not** added to operational revenue (prevents package double-count)
- Reports: client/campaign/package profitability, scope impact, complimentary work, planned vs actual, delivery performance
- `Campaign.internal_cost` = **direct non-labor cost** (not labor). Total cost = labor + direct + operational_costs

**Financial terminology (no invoices yet):** Contracted Value · Billable/Additional Charge Value · Operational Revenue · Estimated Gross Profit/Margin. Never “cash collected / paid revenue”.

**Phase 7 formulas:**

| Metric | Definition |
|--------|------------|
| Normalized Monthly Package Value | `monthly` = agreed; `quarterly` = agreed/3; `half_yearly` = agreed/6; `yearly` = agreed/12; `one_time` = 0 for retainer normalization |
| Period Package Contracted Value | Months (or fraction) of active package overlapping report period × normalized monthly value (one_time: full agreed if start_date in period) |
| Additional Charge Value | Σ PackageOverage where decision=`chargeable` and billing_status ≠ `waived`/`rejected` in period (`extra_amount`) |
| Complimentary Work Value | Σ overage_quantity × overage_rate where decision=`complimentary` (not revenue) |
| Campaign Charge Value | Σ `client_charge` only when `standalone_charge=true` |
| Operational Revenue | Package period contracted + Additional charges + Campaign standalone charges (same currency only; never mix FX) |
| Labor Cost | Σ (duration_seconds/3600 × applicable `user_cost_rates.hourly_cost` at entry `started_at`) |
| Direct Cost | Σ campaign.internal_cost (prorate/include if campaign overlaps period) + operational_costs.amount |
| Total Cost | Labor + Direct |
| Est. Gross Profit | Operational Revenue − Total Cost |
| Est. Gross Margin % | Profit / Revenue × 100; **N/A** if revenue = 0 |
| Data Completeness | `complete` \| `missing_cost_rates` \| `no_revenue` \| `incomplete` |
| On-Time Delivery % | Completed deliverables with client_due_date that finished on/before due ÷ those with due date |
| Variance Hours | Actual − Estimated; Variance % = N/A if estimate = 0 |

**Phase 7 rules:**
1. Never send cost/margin/profit fields to unauthorized Inertia payloads (omit, don’t hide in Vue).
2. Missing cost rates → profitability `incomplete` (never silent zero cost).
3. AM client-scoped; team sees no financials; portal never sees Phase 7 financials.
4. No employee ranking / “best client” scores — factual states only.

### Phase 8

Commercial transaction layer (alongside Phase 7 operational metrics — do **not** rewrite operational revenue):

- `tax_rates`, `billing_settings` (agency seller snapshot source)
- `quotations` / `quotation_items` — numbered `QUO-YYYY-####`, revisions via `parent_quotation_id` + `revision_number`
- `invoices` / `invoice_items` — numbered `INV-YYYY-####`; seller/buyer billing snapshots on issue
- `payments` — numbered `PAY-YYYY-####`; reversal (not hard-delete) for finalized payments
- Package / overage / renewal billing; AR dashboard + aging
- Portal: quotations, invoices, payments (module flags + route enforcement)
- PDFs: quotation, invoice, payment receipt (authorized download endpoints only)

**Quotation statuses:** draft → sent → viewed → changes_requested → accepted | rejected | expired | converted | cancelled | superseded  
**Invoice statuses:** draft → sent → partially_paid → paid | cancelled | void (overdue = computed when due_date < today && balance_due > 0)  
**Payment methods:** bank_transfer, upi, cheque, cash, credit_card, debit_card, payment_gateway, neft, rtgs, imps, other

**Commercial calculation order (backend source of truth, BCMath scale 2, ROUND_HALF_UP):**
1. Line: `line_subtotal = qty × unit_rate − line_discount`
2. Document subtotal = Σ line_subtotals
3. Document discount (fixed or % of subtotal) → `discount_amount`
4. `taxable_amount = subtotal − discount_amount`
5. Allocate taxable across lines proportionally for tax; apply GST per line using **snapshotted** percents
6. Same state (seller vs buyer billing state): CGST + SGST; different state: IGST; exempt: 0
7. `grand_total = taxable_amount + tax_amount`
8. Payments: `amount_paid` = Σ valid (non-reversed) payments; `balance_due = grand_total − amount_paid`; overpayment blocked

**Phase 7 vs Phase 8 terminology:** Operational Revenue / Contracted Value remain operational. Phase 8 adds Quoted / Invoiced / Paid / Outstanding / Overdue.

**Overage double-bill prevention:** PackageOverage `billing_status` ∈ {added_to_quotation, added_to_invoice, paid, waived}; invoice/quotation items link `source_type`/`source_id`.

**Accounting boundary:** No ledger, payroll, bank reconciliation, Tally, payment gateways, credit notes (schema allows future), FX.

### Phase 9

Multi-channel notification automation (In-App · Email · Web Push). Domain events remain source of truth; controllers do not send channels ad-hoc.

**Tables:** `notification_preferences`, `push_subscriptions`, `notification_deliveries`, `notification_templates`, `notification_settings` (global), `notification_dedupe` (idempotency keys)

**Services:** `NotificationDispatchService` · `NotificationPreferenceService` · `NotificationRecipientResolver` · `EmailNotificationService` · `PushNotificationService` · `NotificationTemplateService`

**Flow:** Business action → domain event / dispatch call → resolve recipients → check prefs / quiet hours / digest → write dedupe key → queue channel jobs → delivery log. Business transaction never rolls back on delivery failure.

**Channels:** `database` (in-app), `mail`, `webpush`. Extensible for WhatsApp/SMS later.

**Categories:** task · follow_up · client_request · approval · content · campaign · invoice · quotation · payment · renewal · system

**Priority:** low · normal · high · urgent — quiet hours defer non-urgent email/push; in-app still recorded.

**Web Push:** VAPID keys; multi-device `push_subscriptions`; service worker for closed-tab delivery. Custom sounds only when CRM tab/runtime allows — not guaranteed for OS push when tab closed.

**Reminders (scheduled, queued, deduped):** follow-ups · tasks due · approvals pending · invoices due/overdue · package renewals · daily digests

**Boundary:** No WhatsApp. No offline PWA caching of authenticated CRM. Notifications ≠ ActivityLog.

### Phase 10

Automation Engine · Integration Hub · Phase 9 hardening · provider foundations.

**Phase 9 hardening:** Digest clock gating (`digest_time` + user TZ + once/day dedupe); migrate GenericInApp → CrmNotification (no double in-app); wire Review + DeliverableReady notifications; runtime SMTP from `notification_settings` (encrypted; never returned to browser); delivery retry replays safe stored payload.

**Integration Hub:** `integration_connections` (encrypted tokens/credentials; statuses: not_connected · connected · needs_reauth · error · disabled). Provider adapters via capability interfaces (`calendar`, `social_publish`, `messaging`, `analytics`, `webhooks`, …). Health dashboard + `integration_logs` (no secrets). OAuth state CSRF; tokens never in frontend props.

**Automation Engine:** `automation_rules` / `conditions` / `actions` / `runs` / `action_runs`. Domain events → `AutomationTriggerListener` → evaluate → queue execution. Idempotency key: rule + entity + occurrence + action. Recursion depth guard + rate limits. Delayed actions re-check conditions. Scheduled triggers via `automations:dispatch-scheduled` (not user crontab). Draft → Active activation. Actions: assign, status, task, follow-up, notify, email, comment, note, priority, due date, webhook (SSRF-safe). External failures never roll back business TX.

**Webhooks:** `/webhooks/{provider}/…` — verify signature/challenge → persist `webhook_events` → queue process; idempotent on external_event_id.

**Google Calendar (first real provider):** OAuth → calendar select → CRM→Google create/update/cancel for follow-ups (store `external_event_id`). Inbound sync deferred.

**Social foundation:** `social_accounts`, `content_publications` (revision-scoped; approval-gated; publish via `PublishingProviderInterface`; queue due pubs). Meta / Google Analytics / Ads / WhatsApp Cloud API: connection + capability stubs; no unofficial WhatsApp; no fake metrics.

**Metrics:** `marketing_metric_snapshots` (dated; source tagged; never overwrite manual).

**Boundary:** No scraping; no auto-charging; no full 2-way calendar; no LinkedIn rule-breaking automation.

### Phase 11

Marketing Performance Intelligence · live provider metrics · KPI tracking · automated client reporting.

**Principle:** Never fabricate metrics. Missing provider/permission/data → Not Available / Not Connected / Permission Required / No Data / Sync Failed.

#### Metric taxonomy & registry

Canonical metrics via `MarketingMetricRegistry` (code, label, category, unit, aggregation, %, currency, providers, direction). Families: awareness · engagement · traffic · leads · conversions · paid · social_growth.

**Aggregation:** `sum` (impressions, clicks, spend, leads) · `latest_value` (followers) · `derived` (CTR, CPC, CPL, CPA, ROAS, engagement_rate — never sum %). Zero denominator → N/A.

**Engagement rate formulas (labeled):** by reach · by impressions · by followers — never one ambiguous unlabeled rate.

#### Provider mapping

`MetricsProviderInterface` + mappers: Meta · Google Ads · GA4 · YouTube. Provider field names stay in adapters; UI uses canonical codes only. API version/scopes/capabilities live on provider adapters. Official APIs only — no scraping.

#### Storage & sync

Extend `marketing_metric_snapshots` with: campaign_id, content_item_id, social_account_id, integration_connection_id, provider, external_entity_type/id, metric_code (alias metric_key), currency, source, sync_run_id, metadata. Upsert uniqueness: provider + external entity + metric_date + metric_code + source (+ client). **Manual never overwritten by API** — coexist; UI labels source.

`metric_sync_runs`: connection, client, provider, sync_type (initial/incremental/backfill/manual), date range, status, counts, errors.

Cadence (admin-configurable defaults): ads several×/day; organic/GA4/YouTube daily; lookback window for delayed provider adjustments. Quotas respected.

#### Mappings

`external_marketing_entities` / campaign mappings: CRM Campaign ↔ Meta/Google Ads campaigns (1:N). Client ↔ GA4 property. ContentItem ↔ external_post_id (publication or explicit map). Suggest mapping ok; never auto-save uncertain.

#### Attribution (foundation)

Provenance only: direct_provider · utm_match · manual · crm_source · unknown. No multi-touch / probability scores. Optional `attributed_conversions` / lead mapping layer if no Lead module — do not rebuild sales CRM.

#### KPIs

`campaign_kpis`: metric_code, target_value, target_type (min/max/reference), period. Progress = actual/target; status On Track / Below Target / Above Maximum / No Data (deterministic rules documented in code).

#### Derived formulas

CTR = clicks/impressions×100 · CPC = spend/clicks · CPL = spend/leads · CPA = spend/conversions · ConvRate = conversions/clicks×100 · ROAS = attributed_revenue/spend. Currency isolation: never mix INR+USD without FX architecture.

#### Freshness & quality

Statuses: Fresh / Delayed / Stale / Sync Failed / Not Connected (thresholds per provider). Coverage: Complete / Partial / Missing Provider / Manual Only / Mixed Sources. Partial → warn; never fill gaps with fake numbers.

#### Reports

`client_performance_reports` (draft → internal_review → approved → published → archived) + `client_report_metrics` **frozen snapshots**. PDF via DomPDF from snapshots only (immutable after publish). Portal: Published only; gated by `show_reports` / `show_performance` / `show_ad_spend` / `show_conversion_value`. No internal labor cost/margin in client views.

#### Automation

Triggers: metric_sync.completed/failed · kpi.below_target · report.generated/approved/published · publication.failed. Actions: generate_performance_report (draft), notify AM, create review task, retry_metric_sync. Seed monthly draft-report rule **disabled**. Never auto-publish; never change ad budgets.

#### Security

Client-scoped metrics/mappings/reports/PDFs; portal never receives tokens/secrets. Permissions: `performance.*`, `campaign_kpis.*`, `client_reports.*`, `metric_sources.manage`.

**Boundary:** No auto budget/bid/pause; no black-box AI strategy; no fake metrics; no multi-touch attribution.

### Phase 12

Lead Management · Sales Pipeline · Opportunities · Lead attribution.

Flow: Marketing → Lead → Qualification → Opportunity → Follow-up → Quotation → Client → Invoice → Payment.

#### Two lead domains (strict isolation)

| | Agency Sales Lead (`agency_sales`) | Client Campaign Lead (`client_campaign`) |
|---|---|---|
| Owner | The agency | An existing agency client (`client_id` required) |
| Example | ABC Developers asks the agency for social media management | ABC Developers' Meta campaign produces 150 property enquiries |
| Pipeline | Opportunities → Quotation → Client conversion | Status workflow only; **never** converts into an agency client |
| Agency visibility | `leads.view_all`, or assigned user | Client's account manager, `leads.view_all`, or assigned user |
| Portal visibility | **Never** | Own client only, when `show_leads` is on |

One `leads` table with a `lead_category` column. Every query goes through a single scoping method (`Lead::scopeVisibleTo`), so the two domains can't leak into each other. Duplicate detection, attribution, CPL and reports are always computed **within** one category (and one owning client for campaign leads).

#### Reuse (no parallel modules)

- **Follow-ups:** existing `follow_ups` via `followupable` (morph → Lead). `follow_ups.client_id` becomes nullable; lead follow-ups carry the lead's client when one exists. Lead `last_contacted_at` / `next_follow_up_at` are recomputed from follow-ups and communications.
- **Communications:** existing `client_communications` gains nullable `client_id` + `communicable` morph, so calls/WhatsApp/email are logged against a lead with the same service.
- **Quotations:** existing `QuotationService`. A prospect is quoted through a **prospect Client record** (`clients.status = prospect`, which already exists for pre-sale), created idempotently from the lead and linked by `leads.prospect_client_id`. This gives GST place-of-supply and billing identity without making the prospect an active client. `quotations.opportunity_id` / `quotations.lead_id` keep the chain traceable.
- **Conversion:** "Convert to Client" reuses the prospect Client if one exists (status → `onboarding`) or creates one through `ClientService::create`. Guarded by a row lock and `leads.converted_client_id`, so repeated clicks return the same client. Follow-ups, communications, quotations, opportunities and attribution stay linked.
- **Numbering:** `LEAD-YYYY-000001`, `OPP-YYYY-000001` through the shared number service (no fifth copy of numbering logic).
- **Automation:** new lead/opportunity triggers in `AutomationTriggerRegistry`; new `assign_lead` action. Existing `assign_user` null-assignee bug fixed.
- **Notifications:** new `lead` category in Phase 9 preferences; dispatch via `NotificationDispatchService` with dedupe keys.
- **Webhooks:** Phase 10 `WebhookController` + `webhook_events`; `ProcessWebhookEvent` gains a per-provider handler registry. Meta event identity uses `leadgen_id` (not the page ID).

#### Lead lifecycle

Statuses: new · unassigned · assigned · contacted · qualified · unqualified · follow_up · opportunity · proposal_sent · negotiation · won · lost · on_hold. Allowed transitions are a map on the model and validated in `LeadService`; every change writes `lead_status_histories` (from, to, actor, reason, timestamp). `lost` requires a lost reason (`lost_reasons` table, admin-managed). `won` / conversion stages apply only to agency sales leads.

#### Capture channels

Manual · public capture forms · landing pages · CSV import · Meta Lead Ads webhook · Google (integration-ready only) · generic signed webhook · WhatsApp (future, via official provider).

Public capture (`POST /lead-capture/{form_key}`): per-form key + optional HMAC secret, allowed-origin check, dedicated rate limiter, honeypot field, minimum-fill-time check, consent flag + consent text snapshot. Forms (`lead_capture_forms`) fix the category/owning client/default source server-side — the browser can't choose which client owns a lead.

#### Duplicate control

1. Same provider + provider lead ID → hard dedupe (return the existing lead; no new row).
2. Same email or normalized mobile **within the same category and owning client** → create the lead flagged `possible_duplicate_of_id`.
3. Review actions: Merge (target keeps both source histories), Keep Separate, Mark Duplicate. Original source data is never discarded.

Mobile normalization uses the lead's country (no India default for non-Indian leads).

#### Assignment

`lead_assignment_rules` (ordered): match on category, source, form, campaign, owning client → strategy `specific_user` · `round_robin` (user pool + stored pointer) · `client_account_manager` · `default_account_manager`. `LeadAssignmentService` evaluates them synchronously on creation, so new leads are assigned immediately. The automation engine's `assign_lead` action delegates to the same service — no second rules engine.

#### Attribution

First-touch fields on the lead are written once and never overwritten (source, source detail, UTM set, gclid, fbclid, external campaign/form/lead IDs, landing page, referrer, source timestamp). Later touches go to `lead_touchpoints`. Models: first touch · last touch · direct campaign mapping · manual (manual edits are recorded as a new touch with provenance, not by overwriting). Only data actually received is stored.

Each lead has exactly one attributed source for totals, so source reports never double-count.

#### Lead metrics (documented denominators)

- Lead-to-qualified rate = leads that ever reached `qualified` ÷ total leads in period
- Lead-to-conversion rate = converted leads ÷ total leads in period
- Average first response = mean(first_contacted_at − created_at) over leads with a human contact
- CRM CPL = campaign spend (Phase 11 snapshots, same currency) ÷ CRM leads for that campaign
- Cost per qualified lead = spend ÷ qualified CRM leads; cost per conversion = spend ÷ converted CRM leads
- Zero denominator or mixed currency → N/A

CRM lead counts are **not** written into `marketing_metric_snapshots`; provider-reported leads and CRM leads are shown side by side with their source labels.

`first_contacted_at` is set only by a human action (a communication logged by a user, or a user moving the lead to `contacted`) — never by automated notifications.

#### Sales revenue (never merged into one total)

Estimated pipeline value (open opportunities) · accepted quotation value · contracted value · invoiced · collected · outstanding. Each is shown separately and per currency.

#### Pipeline & opportunities

`sales_pipeline_stages` (seeded defaults, admin-editable) drive the opportunity Kanban. Stage changes go through `OpportunityService` (closed opportunities can't move; `lost` needs a reason; won/lost timestamps recorded). Kanban uses native drag and drop with a select-box fallback; the backend is always the source of truth.

#### Portal

`client_portal_settings`: `show_leads`, `show_lead_contact_details`, `show_lead_source`, `show_lead_reports`, `allow_lead_updates`. Portal only ever queries `client_campaign` leads for the logged-in user's client, enforced in the backend.

#### Permissions

`leads.view`, `leads.view_all`, `leads.create`, `leads.edit`, `leads.assign`, `leads.import`, `leads.merge`, `leads.convert`, `leads.manage_status`, `opportunities.view`, `opportunities.create`, `opportunities.edit`, `opportunities.manage`, `lead_reports.view`, `lead_sources.manage`, `lead_assignment_rules.manage`.

**Boundary:** No multi-touch attribution; no unofficial scraping or WhatsApp; Meta/Google lead intake is integration-ready but not called operational without real credentials; campaign leads never become agency clients.

---


## 6. Clients Module (Phase 1 detail)

### Fields

Company Name, Contact Person, Email, Mobile, Alternate Mobile, Website, Industry, Address, City, State, Country, GST, PAN, Social URLs (JSON), Account Manager, Start Date, Renewal Date, Status, Notes, Logo path.

**Statuses:** prospect · onboarding · active · on_hold · completed · lost · cancelled

### Profile tabs (UI shell in Phase 1; modules fill later)

Dashboard · Campaigns · Content · Requests · Approvals · Tasks · Files · Packages · Invoices · Reports · Credentials · Activity · Notes · Brand · Onboarding

### Onboarding

Template checklist → per-client items · completed/total · progress % · custom items allowed.

### Brand profile

Description, audience, tone, colors, fonts, dos/don'ts, competitors, hashtags, preferences, URLs, reference accounts, logo, guidelines path, assets.

---

## 7. Routes (Phase 1)

| Method | URI | Name |
|--------|-----|------|
| GET | `/dashboard` | dashboard |
| GET/POST | `/clients` | clients.index/store |
| GET | `/clients/create` | clients.create |
| GET/PUT | `/clients/{client}` | clients.show/update |
| GET | `/clients/{client}/edit` | clients.edit |
| DELETE | `/clients/{client}` | clients.destroy |
| PUT | `/clients/{client}/brand` | clients.brand.update |
| POST/PUT | `/clients/{client}/onboarding/{item}` | clients.onboarding.* |
| POST | `/clients/{client}/files` | clients.files.store |

Client portal routes deferred to Phase 5 (`/portal/*`).

---

## 8. APIs / Services

Prefer Inertia controllers + services (not a public JSON API for Phase 1).

- `ClientService` — create/update, assign AM, soft delete
- `ClientOnboardingService` — seed from template, toggle items, progress
- `ClientBrandService` — upsert brand profile
- `ActivityLogger` — append activity_logs
- `PermissionService` / gates via Spatie + policies

---

## 9. Pages (Phase 1)

- `Dashboard.vue` — role-aware skeleton
- `Clients/Index.vue`, `Create.vue`, `Edit.vue`, `Show.vue` (tabs)
- `Clients/Partials/BrandTab.vue`, `OnboardingTab.vue`, `FilesTab.vue`, `ActivityTab.vue`
- Shared: status badges, empty states, progress bar

---

## 10. Implementation Phases

| Phase | Focus |
|-------|--------|
| **1** | Scaffold, RBAC, Clients, Brand, Onboarding, Files shell, Activity foundation |
| **2** | Packages, usage, scope creep |
| **3** | Campaigns, deliverables, tasks, internal review |
| **4** | Content calendar/library, revisions, client approvals |
| **5** | Client portal, requests, visibility rules |
| **6** | Time tracking, workload, delays |
| **7** | Role dashboards, profitability |
| **8** | Campaign performance, monthly reports |
| **9** | Notifications, follow-ups, full audit |
| **10** | Testing, security audit, performance, production |

---

## 11. Potential Breaking Changes

Greenfield — no production data yet. Future risks to document when migrating:

- Role renames / permission slug changes
- Client status enum expansion
- Soft-delete cascades vs orphaned portal users
- File storage disk path changes

---

## 12. Security Rules (always)

1. Policy + permission on every mutating action
2. Account managers: assigned clients only (unless `clients.view_all`)
3. Client users: own `client_id` only (Phase 5)
4. Internal vs client visibility never leaks
5. Financial permissions isolated
6. Soft delete for business records
7. Validate & store uploads securely
8. CSRF on all session routes

---

## 13. Implementation Checklist

### Phase 1

- [x] Architecture document
- [x] Laravel + Breeze Vue Inertia + Tailwind
- [x] Spatie Permission + role seeder
- [x] Clients migrations/models/policies/services/UI
- [x] Brand profile
- [x] Onboarding checklist + templates
- [x] Client files shell
- [x] Activity log foundation
- [x] Demo seeder (optional)
- [x] Feature tests (auth, permissions, client CRUD, visibility)
- [x] Migrate + test + build green

### Phase 2

- [x] Service Package CRUD + items
- [x] Client package assignment with snapshots
- [x] Client-specific customization
- [x] Usage periods + transactions
- [x] PackageScopeService (consume/restore/adjust/overage)
- [x] Overage decisions (charge / complimentary / reject / quotation)
- [x] Renewal workflow
- [x] Permissions + activity logs
- [x] Client Packages tab + UI
- [x] Phase 2 feature tests + build green

### Phase 3

- [x] Campaigns CRUD + codes (`CAM-YYYY-####`) + permissions
- [x] Campaign deliverables + status workflow + package linking
- [x] PackageScopeService reuse (consume once on `ready`, restore on cancel)
- [x] Tasks + assignees + checklist + comments + dependencies
- [x] Internal reviews (polymorphic) + review queue
- [x] Campaign / deliverable progress
- [x] Ops nav (Campaigns / Tasks / Internal Reviews)
- [x] Phase 3 feature tests + build green

### Phase 4

- [x] Content library + revisions (immutable)
- [x] Internal review for ContentItem
- [x] Client approvals (revision-scoped) + portal
- [x] Client-visible comments (backend-filtered)
- [x] Revision limits / overage via PackageOverage
- [x] Content calendar + schedule/publish
- [x] Deliverable waiting-for-client / completion sync
- [x] Phase 4 feature tests + build green

### Phase 5

- [x] Client requests + portal intake
- [x] Scope precheck (no double package consume)
- [x] Convert request → campaign / deliverable / task
- [x] Communications + follow-ups
- [x] Portal visibility settings (server-enforced)
- [x] In-app notifications
- [x] Phase 5 feature tests + build green

### Phase 6

- [x] Time entries + running timer (one active)
- [x] Timesheets (day/week/month)
- [x] Task allocations + capacity
- [x] Workload + utilization (planned/actual/billable)
- [x] Delay reasons + records + analytics
- [x] Operations dashboard + topbar timer
- [x] Phase 6 feature tests + build green

### Phase 7

- [x] Role dashboards (exec / ops / AM / team)
- [x] Internal cost rates (historical)
- [x] Client / campaign / package profitability
- [x] Scope impact + complimentary work
- [x] Planned vs actual + delivery metrics
- [x] Reports + CSV exports (permission-scoped)
- [x] Phase 7 feature tests + build green

### Phase 8

- [x] Quotations + revisions + portal acceptance
- [x] Invoices + GST (CGST/SGST/IGST) + snapshots
- [x] Payments + partial/full/reversal + receipts
- [x] Receivables / aging / renewals / overage billing
- [x] Commercial PDFs (authorized downloads)
- [x] Phase 8 feature tests + build green

### Phase 9

- [x] Multi-channel dispatch (in-app / email / web push) via NotificationDispatchService
- [x] Preferences, quiet hours, pause, digests, delivery logs
- [x] Scheduled reminders (follow-ups, tasks, invoices, renewals, digests)
- [x] Templates + admin email/push/defaults/health UI
- [x] Service worker push + VAPID (public only to frontend)
- [x] Phase 9 feature tests + build green
- [x] Hardening (P10)

### Phase 10

- [x] Integrations foundation + Google Calendar real adapter
- [x] Meta / Ads / GA stubs + webhooks security
- [x] Automations engine (WHEN/IF/THEN)
- [x] Social publications + marketing_metric_snapshots shell
- [x] Phase 10 feature tests + build green

### Phase 11

- [x] Metric registry + Meta/Ads/GA4/YouTube mappers
- [x] Extend snapshots + sync runs + mappings + KPIs + reports + attributions
- [x] MetricSync / Aggregation / Performance / KPI / Attribution / Report / Manual services
- [x] Commands, jobs, schedule (hourly sync, monthly draft reports)
- [x] Agency + portal controllers/UI + DomPDF snapshot PDF
- [x] Automation triggers/actions + disabled monthly template
- [x] Phase 11 feature tests (188 total) + migrate + build green

### Phase 12

- [x] Leads (agency sales + client campaign) with strict isolation
- [x] Capture: manual, public forms, CSV, Meta Lead Ads webhook
- [x] Duplicates, assignment rules, status history, lost reasons
- [x] Opportunities + pipeline Kanban
- [x] Follow-ups / communications / quotations reuse; idempotent client conversion
- [x] Attribution, lead analytics, campaign lead reports, sales revenue measures
- [x] Dashboards, portal leads, notifications, automation triggers
- [x] Phase 12 feature tests (72) + full suite (260) + build green

---

## 14. Design Tokens (UI)

Premium SaaS: clean, professional, high density without clutter.

- Sidebar collapsible
- Top bar: Search · Notifications · Quick Create · Profile
- Components: cards, tables, tabs, modals, drawers, badges, skeletons, empty states
- Responsive: desktop → mobile

(Full Quick Create + global search wired as modules land.)
