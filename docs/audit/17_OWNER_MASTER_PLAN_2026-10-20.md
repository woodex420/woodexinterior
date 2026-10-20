# Woodex OS Master Upgrade Plan

## Convert Woodex v2.1 Admin into the Laravel Woodex OS

**Source systems**

- Legacy system: Woodex Admin v2.1 Pro — vanilla PHP/JSON/MySQL admin, public website, builder and integrations.
- Target system: Woodex OS — Laravel 13, Livewire 4, Blade, Tailwind 4, Preline 5, relational MySQL architecture.
- Reference branch: `arena/8be318a2-woodexinterior`.
- Upgrade rule: preserve business behavior, URLs, SEO value, data and operational safety while replacing fragile implementation layers.

---

## 1. Executive goal

Build one coherent Woodex operating system in Laravel that absorbs the useful capabilities of the v2.1 admin while upgrading the navigation and backend into the Woodex OS workflow:

```text
Old v2.1 Admin                         Woodex OS
────────────────────────────────────────────────────────────────
Home / Dashboard                 →     Overview / Dashboard / My Day
CRM                              →     CRM / Leads / Contacts / Tasks
Sales                            →     Sales / Estimations / Quotes / Invoices / Payments
Transactions / money             →     Accounts / Ledger / Periods / Payment methods
Projects                         →     Projects / Board / Milestones / Handover
Inbox / Chat / WhatsApp          →     Inbox / Conversations / Chat / Templates
Automation                       →     Automation / Sequences / Alert rules
Social / Marketing               →     Marketing / Social planner / Campaign controls
Content                          →     Website / Content library / Blog / Portfolio
Media                            →     Website / Media library / private media service
Pages                            →     Website / Pages / templates / sections
Builder                          →     Website / Page builder / structured CMS
SEO                              →     Website / SEO overview / redirects / sitemap
SEO agent                        →     Website / SEO agent / audited proposals
Speed                            →     Website / Speed / performance history
Site health                      →     Website / Health / broken links / assets / drift
Theme                            →     Website / Theme builder / runtime tokens
Admin / Users / Settings / Me    →     System / Users / Settings / Health / Profile security
```

### Primary outcome

Woodex staff should be able to move through the complete business lifecycle without leaving Woodex OS:

```text
Website visitor
  → enquiry / chat / WhatsApp
  → CRM lead + contact
  → assignment + follow-up + site visit
  → estimate
  → quotation + approval + signed client portal
  → invoice + payment proof + verification
  → accounting ledger
  → project board + updates + handover
  → review + reporting + automation
```

### Non-negotiable principles

1. **Laravel becomes the backend source of truth.** The v2.1 PHP/JSON layer is a migration source and compatibility bridge, not the long-term architecture.
2. **Do not rewrite working business rules blindly.** Port, test and improve them domain by domain.
3. **Money is server-calculated.** Store PKR in integer paisa/minor units; never trust browser totals or JSON money blobs.
4. **Every client-facing URL is protected.** Preserve the live URL map and trailing-slash canonical behavior.
5. **No silent data loss.** Every import is idempotent, checksummed, logged and resumable.
6. **No unsafe automation.** Client messages require consent, money actions require explicit staff actions, and agents remain owner-controlled.
7. **Hostinger is a constraint.** PHP 8.4, MySQL, Composer, cron, database queue and prebuilt assets; no Node server or long-running daemon.
8. **Ship in reversible waves.** Each wave has migrations, tests, browser evidence, rollback instructions and owner sign-off.

---

## 2. Current-state assessment

### 2.1 Legacy v2.1 Admin strengths to preserve

- Mature CRM behavior: leads, contacts, pipeline, notes, follow-ups and assignments.
- Quotation and invoice workflows with PDF/share-link behavior.
- Booking/site-visit handling.
- WhatsApp and Telegram integration concepts.
- Website page/content/media/SEO management.
- Builder templates and visual editing experience.
- Reports, health checks, redirects and content operations.
- Real Woodex business vocabulary, stages, services, cities, pricing and payment terms.

### 2.2 Legacy v2.1 implementation problems to retire

- Vanilla JavaScript modules with load-order dependencies.
- JSON side stores for settings, credentials and business state.
- Money-bearing `LONGTEXT` JSON records.
- No reliable relational foreign-key model.
- Separate authentication/token patterns in admin and builder.
- Static files and CMS content competing for the same public URLs.
- Browser-side money calculations.
- Public installer/demo/diagnostic attack surface.
- Hard-to-reproduce deploys without a dependency lockfile.

### 2.3 Current Woodex OS foundation

Woodex OS already provides the target foundation:

- Laravel 13 + Livewire 4 + Blade.
- Tailwind 4 + Preline 5 + Vite.
- Ten bounded domains: Accounts, Automation, Bot, Conversations, CRM, Marketing, Projects, Sales, System and Website.
- Relational schema with approximately 95 tables in the documented sandbox state.
- Role/permission model and policy layer.
- Signed portals and provider webhooks.
- Database queue and Hostinger cron model.
- CMS, page builder, theme tokens, media and website health capabilities.
- Tests for core CRM, sales, portals, payments, integrations, CMS and builder behavior.

### 2.4 Current known gaps to close

- Complete v2.1 data import and reconciliation on a staging database.
- Confirm every old v2.1 screen has a clear OS destination or a documented retirement decision.
- Finish remaining website parity and static-to-CMS conversions.
- Complete SEO agent, speed and health depth where still partial.
- Finish media/content/team/template parity where required by business users.
- Prove production MySQL behavior, cron, queue, email, backups and external providers.
- Establish reproducible dependencies with `composer.lock` and active CI.

---

## 3. Target Woodex OS information architecture

The new navigation is workflow-first. It replaces a long list of technical screens with business areas.

### 3.1 Overview

**Purpose:** give the owner and managers a single operational view.

Tabs/screens:

- Overview dashboard.
- My Day.
- KPI cards: new leads, overdue follow-ups, quotes awaiting response, due payments, active projects, unread conversations.
- Recent activity and alerts.
- Sales funnel and revenue snapshots.
- Website health summary.
- Automation health summary.
- Pending approvals.
- Live website-chat indicator and drawer.

**Legacy mapping:** Home, dashboard, reports summary, notifications and header alerts.

### 3.2 CRM

**Purpose:** manage every person, company, enquiry and next action.

Tabs/screens:

- Leads: list.
- Leads: pipeline.
- Leads: tracking board — uncontacted, due today, overdue, stale, snoozed.
- Contact and company directory.
- Contact 360 view.
- Tasks.
- Site visits.
- Assignment rules.
- Activity timeline.
- Recycle bin and data export.

**Legacy mapping:** CRM, leads, clients, pipeline, follow-ups, bookings, notes and enquiry management.

### 3.3 Sales

**Purpose:** move an opportunity from estimate to approved quote, invoice and collection.

Tabs/screens:

- Estimations.
- Quotations: all.
- Quotations: tracking.
- Quotations: templates.
- Quote builder.
- Approvals.
- Invoices: all.
- Invoices: tracking.
- Payments: all.
- Payments: dues.
- Payments: receivables.
- Documents: descriptions, document design and rate book.
- Quote/invoice PDF and signed-link delivery.

**Legacy mapping:** Sales, quotations, quote templates, invoices, payments, transactions, estimator and print tools.

### 3.4 Accounts

**Purpose:** provide trustworthy financial records behind Sales.

Screens:

- Chart of accounts.
- Journals.
- Accounting periods.
- Payment methods and account mappings.
- Ledger reports.
- Revenue and collection reports.
- Reconciliation/exception views.

**Legacy mapping:** Transactions and financial summaries, upgraded from loose records into balanced journal entries.

### 3.5 Projects

**Purpose:** deliver accepted work and manage client visibility.

Screens:

- Projects list.
- Project board.
- Project detail.
- Milestones.
- Updates/photos.
- Project costs and expenses.
- Billing links.
- Handover checklist.
- Client project portal.

**Legacy mapping:** Project records, milestones, photos, billing and client updates.

### 3.6 Inbox

**Purpose:** unify website chat, WhatsApp and future channels.

Screens/components:

- Conversation list.
- Conversation detail.
- Assignment and internal notes.
- Canned replies.
- WhatsApp templates.
- Media sending.
- Consent and opt-out state.
- Reply SLA.
- Header live-chat drawer.
- Human handoff and bot context.

**Legacy mapping:** Inbox, chats, chatbot, WhatsApp hub and notification workflows.

### 3.7 Automation

**Purpose:** make repeatable follow-up safe and observable.

Screens:

- Automation control dashboard.
- Follow-up sequences.
- Step-run history.
- Alert rules.
- Quiet-hours and daily caps.
- Monthly reports and digest switches.
- SEO/social agent switches.
- Automation health.

**Legacy mapping:** Automation, cron jobs, WhatsApp campaigns, reminders, alerts and agent controls.

### 3.8 Marketing

**Purpose:** manage social and campaign distribution without mixing it into CRM operations.

Screens:

- Social planner.
- Posts and drafts.
- Campaign calendar.
- Lead-source attribution.
- Content handoff queue.
- Future Meta/LinkedIn/Messenger integrations.

**Legacy mapping:** Social media, campaigns, posts and marketing integrations.

### 3.9 Website

**Purpose:** operate the public website as a governed CMS and design system.

Screens:

- All pages.
- Page builder.
- Page templates.
- Section library.
- Header and footer.
- Menus.
- Content library.
- Blog/insights.
- Portfolio/projects.
- Hero slides.
- Team.
- Media library.
- Forms builder.
- SEO overview.
- SEO agent.
- Redirects.
- Speed.
- Site health.
- Theme builder.
- Website settings.

**Legacy mapping:** Home, Content, Pages, Builder, Media, SEO, SEO agent, Speed, Site health and Theme.

### 3.10 System

**Purpose:** protect, configure and maintain the platform.

Screens:

- Users and roles.
- Profile / Me / security.
- Settings.
- Integrations.
- System health.
- Audit log.
- Database updates.
- Data import.
- Change requests.
- Recycle bin.
- Backups and restore status.
- Privacy and retention.

**Legacy mapping:** Admin, Settings, Users, Security, Diagnostics, Backups, System and Me.

---

## 4. Target backend architecture

### 4.1 Layering

```text
HTTP route / webhook / Livewire action
        ↓
Authorization + validation + throttling
        ↓
Domain Action / Application Service
        ↓
Domain Model + transaction + event/activity record
        ↓
Notification / queue job / integration gateway
        ↓
Relational database + private storage
```

### 4.2 Laravel application boundaries

| Layer          | Responsibility                                             | Rule                                          |
| -------------- | ---------------------------------------------------------- | --------------------------------------------- |
| Routes         | URL, middleware and controller/component selection         | No business calculations                      |
| Middleware     | Auth, active user, 2FA, permissions, signatures, throttles | Fail closed                                   |
| Livewire       | Form state, screen composition and user feedback           | No core money/CRM rules                       |
| Actions        | One business mutation or workflow step                     | Transactional and testable                    |
| Services       | Reusable calculations, rendering, gateways or reports      | Typed interfaces                              |
| Models         | Relationships, casts, scopes and local invariants          | No UI-only behavior                           |
| Policies       | Record-level authorization                                 | Applied in lists and detail screens           |
| Jobs           | Durable asynchronous work                                  | Idempotent and retry-safe                     |
| Integrations   | WhatsApp, Telegram, Google, AI, email, Turnstile           | Credentials encrypted/configured outside code |
| Settings       | Business-controlled values and feature switches            | Secrets never returned to browser             |
| Views/sections | Presentation only                                          | Escape user content by default                |

### 4.3 Shared backend services to standardize

- `Money` and `QuoteCalculator` for all financial calculations.
- `NumberSequence` for quote, invoice, receipt and lead references.
- `Phone` for E.164 normalization and dedupe.
- `CaptureLead` as the only public lead creation path.
- `PageRenderer`, `SiteRouter` and `PageCache` for public pages.
- `SavePage` and `PageValidator` for CMS writes/publishing.
- `QuoteEditor` and quote actions for quote mutations.
- `QuoteLinks` for signed/expiring portals.
- `PostJournalEntry` for ledger mutations.
- `AutomatedSendGuard` for consent, quiet hours and daily caps.
- `SystemHealthReport` for operational health.
- `AuditLog`/activity events for traceability.
- `LegacyRef`/import services for idempotent v2.1 migration.

### 4.4 Authentication and permissions

Target controls:

- Laravel Fortify authentication.
- bcrypt/Argon password hashing according to Laravel configuration.
- database-backed sessions with secure HttpOnly/SameSite cookies.
- mandatory owner/admin 2FA policy for production.
- roles: owner, admin, sales manager, sales, accountant, designer/PM, support and editor.
- route-level permissions for hubs.
- policies for record-level visibility.
- signed portals with expiration.
- one-time portal codes with bcrypt hashing and throttling.
- audit records for every sensitive mutation.
- owner-only raw HTML/advanced builder capabilities.

### 4.5 Hostinger runtime design

- PHP 8.4.
- MySQL 8/MariaDB 10.6+.
- Composer production install from a committed lockfile.
- `platform/public` as the only web-visible application folder.
- `.env` and `storage/` outside the deployed folder where supported.
- database queue.
- one-minute `schedule:run` cron.
- `queue:work --stop-when-empty` rather than a daemon.
- prebuilt `public/build` committed by CI.
- private media served through authorized Laravel routes.
- scheduled local and off-site backups.

---

## 5. v2.1-to-OS data migration plan

### 5.1 Migration strategy

The migration must be one-way, repeatable and non-destructive:

```text
v2.1 MySQL / JSON / files
          ↓ read-only connection or export
Legacy staging tables / import readers
          ↓ normalize + validate + checksum
Woodex OS relational tables
          ↓ reconciliation report
Owner approval
          ↓ cutover
Production OS database
```

Do not connect the first importer directly to production writes. Use a staging database and keep the legacy source read-only.

### 5.2 Import control tables

Use or extend:

- `legacy_refs` — source table, legacy ID, target model/type, target ID, checksum and import time.
- `import_runs` — run ID, source, started/finished state, counts, errors and operator.
- `import_errors` — row-level error, source identifier, reason and retry state.
- `import_batches` — resumable batch boundaries.

Every importer must support:

- dry-run mode.
- preview counts.
- checksum comparison.
- idempotent rerun.
- row-level error reporting.
- rollback only for the current import batch.
- no deletion of existing OS records unless explicitly approved.

### 5.3 Core mapping

| v2.1 source               | Woodex OS destination                             | Migration rule                                                                                        |
| ------------------------- | ------------------------------------------------- | ----------------------------------------------------------------------------------------------------- |
| `wx_users`                | `users` + Spatie roles                            | Normalize email, map legacy roles, preserve active state, force password reset if hash policy differs |
| `wx_leads`                | `contacts` + `leads`                              | Deduplicate by normalized phone/email, preserve source/reference and stage history                    |
| `wx_clients`              | `contacts` + `companies`                          | Merge by normalized identity, retain legacy ID and original fields in metadata                        |
| `wx_lead_notes`           | `activities`                                      | Preserve timestamps, author name and system/user distinction                                          |
| `wx_bookings`             | `site_visits`                                     | Map date/time/status, connect lead/contact and preserve address/notes                                 |
| `wx_quotes`               | `quotes`, sections, items, milestones             | Parse JSON only through a versioned adapter; calculate/check totals server-side                       |
| quote revision/history    | `quote_versions` + `quote_events`                 | Freeze sent versions and preserve event timestamps                                                    |
| `wx_invoices`             | `invoices`, invoice items and milestones          | Validate quote/project linkage and totals before import                                               |
| payments/transactions     | `payments`, allocations and ledger entries        | Do not infer missing verification; import as pending/unverified where evidence is absent              |
| `wx_projects`             | `projects`, milestones, updates and expenses      | Parse JSON into normalized records; retain unknown fields in metadata                                 |
| `wx_chats`                | `conversations`                                   | Map channel/status/unread/lead link and preserve external token as legacy metadata only               |
| `wx_chat_msgs`            | `messages`                                        | Preserve author/channel/time and sanitize/render safely                                               |
| `wx_templates`            | quote templates/canned replies/WhatsApp templates | Classify by content type; do not mix customer messages with quote templates                           |
| `_private/content.json`   | CMS pages/templates/settings                      | Import only known non-secret content fields                                                           |
| `_private/redirects.json` | `redirects`                                       | Normalize source/target and detect chains                                                             |
| `_private/company.json`   | business/site settings                            | Review against owner-approved canonical NAP values                                                    |
| `_private/config.json`    | no direct import                                  | Recreate secrets/configuration through OS installer/settings                                          |
| `_private/security.json`  | no direct import                                  | Do not import sessions, tokens or auth state                                                          |
| media/uploads             | `website_media` or private `media`                | Check MIME/type, checksum, dimensions, alt text and ownership                                         |
| public HTML pages         | CMS page import                                   | Use the legacy importer; preserve path, title, SEO and content provenance                             |

### 5.4 Money migration rules

For each legacy quote/invoice/payment:

1. Parse the legacy record with a versioned adapter.
2. Convert rupee values to integer paisa using explicit rounding rules.
3. Recalculate totals with the OS calculator.
4. Compare calculated vs legacy total.
5. Stop the row if the difference exceeds an approved tolerance.
6. Import source total and discrepancy into audit metadata.
7. Do not mark a payment verified without evidence.
8. Post ledger entries only after the accounting mapping and period are approved.

### 5.5 Import reconciliation report

Every staging import must produce:

- source row counts.
- imported counts by entity.
- skipped counts and reasons.
- duplicate/merge counts.
- missing relationship counts.
- quote/invoice total discrepancies.
- payments without proof.
- orphan media.
- broken/chain redirects.
- pages without SEO/alt text.
- users requiring password reset or 2FA setup.
- final owner approval status.

---

## 6. Upgrade waves

The upgrade is intentionally split into waves. A wave is complete only when its code, data, tests, browser evidence and documentation are complete.

### Wave 0 — Freeze, inventory and safety baseline

**Goal:** prevent legacy changes and establish a known baseline.

Deliverables:

- Freeze v2.1 schema/content changes during migration windows.
- Inventory every v2.1 route, admin screen, API action, table, JSON store, file store and scheduled job.
- Confirm canonical business settings: phone, email, hours, address, currency, payment terms and brand lines.
- Rotate any credentials that appeared in old public repositories or artifacts.
- Create staging branch/database and backup legacy source.
- Add `composer.lock` and enable CI.
- Define the release dashboard and migration runbook.

Exit criteria:

- No unresolved secret exposure.
- Source and target inventories signed off.
- Staging database and rollback backup verified.
- CI runs PHP tests, Pint, Larastan, frontend build and dependency audit.

### Wave 1 — Identity, settings and system shell

**Goal:** establish one secure OS login and one settings source.

Port:

- users and roles.
- profile/Me screen.
- password reset.
- 2FA.
- active sessions.
- business settings.
- site settings.
- integration settings.
- notification settings.
- audit log.
- system health.

Build/verify:

- owner setup flow.
- role permission matrix.
- break-glass 2FA recovery.
- encrypted secret storage.
- no legacy tokens/session state imported.

### Wave 2 — CRM and enquiry intake

**Goal:** make the OS CRM the owner of all new enquiries.

Port:

- public contact forms.
- estimator enquiries.
- booking enquiries.
- legacy form bridge.
- web chat intake.
- WhatsApp inbound leads.
- contacts/companies.
- lead stages.
- notes/activities.
- tasks/site visits.
- assignment rules.
- lead tracking board.

Acceptance:

- Every source calls `CaptureLead`.
- Duplicate contacts are merged or flagged.
- Assignment and SLA timers work.
- Source, page and campaign attribution is retained.
- Public forms work with and without JavaScript.

### Wave 3 — Sales, documents and accounting

**Goal:** replace legacy sales/money storage with relational, auditable OS workflows.

Sequence:

```text
Estimator → Estimate → Quote draft → Approval → Sent frozen version
         → Client portal → Accepted quote → Project/invoice
         → Payment proof → Verification → Allocation → Ledger
```

Deliverables:

- rate book and estimator rates.
- estimates and conversion to quotes.
- quote templates and document descriptions.
- quote builder with revisions.
- approval rules and audit events.
- signed quote links and acceptance/change requests.
- invoices and milestone plans.
- payments, proof uploads, verification and receipts.
- chart of accounts, periods, journals and payment-method mappings.
- reports: funnel, revenue/collections and receivables.

Hard gates:

- All totals are calculated by domain services.
- Sent quote versions are immutable.
- Payment posting is idempotent.
- Ledger entries balance to the paisa.
- No payment is marked verified without the required authorization/evidence.

### Wave 4 — Projects and client portals

**Goal:** connect accepted sales to delivery and client visibility.

Deliverables:

- project creation from accepted quote.
- project board and stages.
- milestones, updates, photos and expenses.
- invoice/payment links.
- client project portal.
- handover checklist and sign-off.
- review request workflow.

Acceptance:

- A quote can become a project exactly once.
- Project billing links to invoices without duplicated totals.
- Client portal exposes only the authorized project.
- Private media cannot be downloaded through guessed paths.

### Wave 5 — Inbox, WhatsApp and Telegram

**Goal:** provide one safe communications workspace.

Deliverables:

- unified inbox.
- WhatsApp credentials/settings and template manager.
- inbound/outbound messages with statuses.
- 24-hour window and consent/opt-out enforcement.
- media receive/send.
- canned replies with variables.
- conversation assignment and SLA.
- website chat header badge/toast/drawer.
- Telegram staff linking, commands, alerts and approval callbacks.
- durable webhook event storage and retries.

Owner inputs:

- Meta business credentials and template approvals.
- Telegram bot credentials and webhook URL.
- broadcasting choice if real-time push is required; polling remains the shared-hosting fallback.

### Wave 6 — Automation and agents

**Goal:** turn repeatable operations into owner-controlled, observable workflows.

Deliverables:

- automation catalog and switches.
- sequence builder and seeded sequences.
- step-run logs.
- pause/resume/cancel.
- client reply/won/lost exit rules.
- quiet hours and daily caps.
- lead/quote/invoice/visit/review triggers.
- Telegram/email/in-app alerts.
- monthly CRM report.
- keyless bot mode and human handoff.
- optional AI provider abstraction with encrypted key and budget cap.

Safety gates:

- No automated client message without consent and allowed channel state.
- No automated money posting.
- No SEO/content agent publishes without human approval.
- Every agent has an explicit owner switch and run history.

### Wave 7 — Website CMS, content and media parity

**Goal:** absorb v2.1 Content, Media, Pages and Builder into the Laravel CMS.

Deliverables:

- pages list with filters, status, type and author.
- page templates for service, city, article, study and campaign pages.
- section library with schema-driven fields.
- saved sections.
- drag/reorder/duplicate/copy/paste.
- draft/preview/publish/version restore.
- conflict detection.
- inline editing where safe.
- header/footer builder.
- nested menus and mega panel.
- content libraries: FAQs, testimonials, logos and team.
- blog/insights and portfolio records.
- forms builder and estimator block.
- media library with private storage, conversions, alt text and usage counts.

Builder rule:

- Structured blocks are the default.
- Free canvas is restricted to campaign pages.
- Raw HTML is owner-only and DOM-sanitized.
- Builder writes CMS records, never uncontrolled public files.

### Wave 8 — SEO, SEO agent, Speed and Site health

**Goal:** upgrade v2.1 website operations into measurable, human-approved systems.

#### SEO overview

- page title/description checks.
- canonical and trailing-slash checks.
- H1/heading checks.
- schema presence/validity.
- image alt/dimension checks.
- sitemap and robots status.
- redirect chain warnings.
- indexability and orphan-page checks.

#### SEO agent

Three layers:

1. **Crawler rules:** managed `robots.txt`, `llms.txt`, sitemap and crawler policy.
2. **Deterministic auditor:** scheduled scan with findings and severity, not a fake single score.
3. **AI assistant:** proposes titles, descriptions, FAQs and schema; human approves and every change is logged.

#### Speed

- page weight and image-size checks.
- WebP/AVIF and `srcset` enforcement.
- lazy-loading audit.
- cache-header audit.
- theme CSS/build-size monitoring.
- PageCache hit/miss status.
- optional Google PageSpeed API with owner credentials.

#### Site health

- broken internal links.
- missing images/media.
- orphan pages.
- 404ing redirects.
- draft/published drift.
- failed imports.
- missing alt text.
- stale sitemap/robots.
- queue, scheduler, database, storage and backup indicators.

### Wave 9 — Theme and design system upgrade

**Goal:** make both old v2.1 screens and new OS screens visually coherent without a risky rewrite.

Deliverables:

- central Woodex token vocabulary.
- runtime CSS variables for colors, type, spacing, radius, shadows and containers.
- Light/dark theme policy.
- contrast checks for body text and controls.
- theme presets: Woodex classic, gallery serif, modern slate and warm walnut.
- theme draft/publish workflow.
- preview on real public and admin pages.
- legacy v2.1 compatibility aliases during migration.

Design rule:

- Preserve the current Woodex visual language first.
- Do not replace the admin with a generic TailAdmin template.
- Use Preline/Tailwind components adapted to Woodex tokens.
- Remove hard-coded color drift from migrated screens.

### Wave 10 — Cutover, cleanup and retirement

**Goal:** safely retire the legacy admin and make Laravel OS the single system.

Steps:

1. Freeze legacy writes.
2. Run final delta import.
3. Reconcile counts, financial totals, users, media and URLs.
4. Switch public website groups only after parity approval.
5. Redirect old admin URLs to OS tabs where needed.
6. Disable legacy admin/API writes.
7. Retain read-only legacy archive outside the web root.
8. Remove installers, demo utilities, old tokens and unused public diagnostics.
9. Run production smoke, role matrix, portal checks, forms and provider checks.
10. Observe for at least one business week before declaring final retirement.

---

## 7. Website conversion plan

The website must move group by group, not through a risky one-time rewrite.

### Conversion groups

| Order | Group                | Scope                                                                 |
| -----:| -------------------- | --------------------------------------------------------------------- |
| 1     | Legal/utilities      | Privacy, contact, estimator, booking                                  |
| 2     | Insights             | Blog/guides and insight index                                         |
| 3     | Cities/areas         | City and Lahore-area pages                                            |
| 4     | Projects             | Portfolio/project pages and media-heavy layouts                       |
| 5     | Rooms/property sizes | High-intent residential pages                                         |
| 6     | Services/specialists | Main commercial/service pages                                         |
| 7     | Hub pages            | About and services indexes                                            |
| 8     | Home                 | Final highest-risk cutover, unless owner-approved pilot is maintained |

### Per-group gate

Before deleting a static group:

- same URL and trailing-slash behavior.
- same or intentionally improved title/description/canonical.
- same H1/heading order.
- same internal links and no broken links.
- same images or approved replacements.
- alt text and dimensions present.
- JSON-LD valid.
- forms create OS leads.
- analytics events verified.
- 375/768/1440 visual checks pass.
- page weight/LCP/CLS has no unexplained regression.
- owner signs off in staging.

### Rollback

- Restore the static group from the release branch.
- Redeploy.
- Keep CMS content and decisions intact.
- Investigate the parity report.
- Retry only after a new owner approval.

---

## 8. API migration plan

### Compatibility stage

Keep legacy endpoints temporarily, but route them into OS actions:

```text
legacy /api/forms.php     → CaptureLead
legacy /api/chat.php      → Conversation/ChatWidget services
legacy quote endpoints    → Quote actions/portal services
legacy booking endpoints  → SiteVisit/booking actions
legacy webhook paths      → OS webhook controllers
```

Compatibility endpoints must:

- validate the legacy request shape.
- normalize into OS DTOs.
- use the same authorization/throttling/idempotency as native endpoints.
- emit a deprecation/usage log.
- never write directly to legacy JSON stores after the cutover date.

### Native OS API stage

Expose only the stable endpoints required by:

- public website forms/chat.
- signed client portals.
- WhatsApp/Telegram webhooks.
- MCP read-only bridge.
- internal browser clients through Livewire actions where possible.

### API retirement gate

Retire a legacy endpoint only when:

- usage log shows zero active callers for an agreed observation period.
- all public/static pages use native or compatible OS paths.
- integrations have updated webhook URLs.
- owner approves removal.

---

## 9. Testing and quality plan

### Unit tests

- Money/paisa rounding.
- Quote calculations and milestone splits.
- Phone/email normalization.
- Stage and assignment rules.
- SEO validators.
- Section schema cleaning.
- HTML sanitization payloads.
- signed-link expiry.
- automation safe limits.
- ledger balance and reversal rules.

### Feature/integration tests

- public lead capture from each source.
- quote lifecycle and immutable sent versions.
- invoice/payment/ledger flow.
- project creation and portal access.
- WhatsApp/Telegram webhook signatures and idempotency.
- roles and permissions.
- CMS publish/preview/rollback.
- media upload and private access.
- SEO agent proposal/approval.
- imports and reruns.

### Browser tests

- dashboard and every workflow hub.
- owner/admin/sales/accountant/editor/support views.
- quote builder and payment workflows.
- inbox/chat drawer.
- website page builder at desktop/tablet/mobile.
- public forms with JavaScript on/off.
- client portals desktop/mobile.
- 375/768/1440 widths.
- keyboard focus and critical accessibility paths.

### Release checks

```text
composer validate
composer install --locked
composer audit
vendor/bin/pest
vendor/bin/pint --test
vendor/bin/phpstan analyse --memory-limit=1G
npm ci
npm run build
HTTP smoke
browser smoke
MySQL fresh install
MySQL migration update
backup + restore drill
route × role matrix
website URL parity
```

### Definition of done

A feature is not complete until:

- code is implemented in the target Laravel architecture.
- permissions and audit behavior are present.
- tests cover success, failure and boundary paths.
- browser behavior is verified where UI is involved.
- migration/import impact is documented.
- `PROJECT_STATUS.md` and `platform/STATE.md` are updated.
- CI is green.
- rollback is documented.
- owner signs off when the feature affects production workflows.

---

## 10. Delivery governance

### 10.1 Branching

- `main`/release branch: deployable only.
- one branch per wave or tightly scoped feature.
- no direct production edits.
- database migrations and code ship together.
- frontend build output is committed when required by Hostinger.

### 10.2 Change control

Any change involving one of these requires explicit owner approval:

- database schema or destructive data changes.
- public URL or redirect changes.
- money/payment/ledger behavior.
- automation sending behavior.
- external provider broadcasts.
- raw HTML/free-canvas capability.
- tenant/multi-studio behavior.
- replacing the current Woodex design language.

### 10.3 Observability

Monitor:

- application errors.
- failed jobs.
- scheduler heartbeat.
- webhook failures/retries.
- queue age.
- backups and last restore test.
- login/2FA failures.
- import errors.
- 404s and redirect chains.
- SEO findings.
- message delivery failures.
- quote/invoice/payment exceptions.

### 10.4 Data ownership

| Data                                                | Source of truth after migration             |
| --------------------------------------------------- | ------------------------------------------- |
| Users, roles and permissions                        | Laravel/Spatie database                     |
| CRM leads/contacts/companies                        | Laravel relational database                 |
| Quotes/invoices/payments                            | Laravel Sales + Accounts database           |
| Projects and client portals                         | Laravel database + private media            |
| Inbox/conversations                                 | Laravel database + provider event records   |
| Website pages/sections/media/menus                  | Laravel CMS database/private storage        |
| Long-form source files, if Sveltia remains approved | Git repository, imported into CMS           |
| Theme tokens                                        | Laravel settings/theme tables               |
| Secrets                                             | Environment/private encrypted settings only |
| Legacy v2.1 data                                    | Read-only archive after cutover             |

---

## 11. Milestone schedule

The schedule is dependency-based rather than calendar-promised. Each milestone can be split into smaller pull requests.

| Milestone | Outcome                                           | Dependencies                       |
| --------- | ------------------------------------------------- | ---------------------------------- |
| M0        | Baseline, freeze, secrets and CI                  | Owner canonical settings           |
| M1        | Secure OS identity/system shell                   | PHP 8.4, Composer, database        |
| M2        | CRM receives all new enquiries                    | M1, public forms/webhooks          |
| M3        | Sales + accounting replace legacy money workflows | M2, chart/period policy            |
| M4        | Projects and portals connect accepted work        | M3                                 |
| M5        | Inbox, WhatsApp and Telegram operational          | Provider credentials/approvals     |
| M6        | Automation and bot controlled by owner            | M2/M5, consent policies            |
| M7        | Website/content/media/builder parity              | Theme tokens, CMS, URL map         |
| M8        | SEO agent, speed and health complete              | Website CMS and crawler access     |
| M9        | Static-to-CMS conversion complete                 | Per-group parity/sign-off          |
| M10       | Legacy admin/API retired                          | Final delta import and observation |

### Suggested implementation order inside the repository

1. Confirm current branch status and refresh documentation claims.
2. Add/activate lockfile and CI.
3. Finish/verify import framework and staging reconciliation.
4. Close system identity/permissions/recovery gaps.
5. Run CRM source migration and switch new leads to OS.
6. Run sales/accounting migration and financial reconciliation.
7. Port project/inbox/integration state.
8. Complete Website v2.1 parity items: templates, content libraries, team, SEO agent, speed, health and presets.
9. Convert public URL groups one at a time.
10. Retire the legacy admin only after the observation window.

---

## 12. Owner decisions required

The following decisions must be recorded before the relevant wave:

1. Canonical studio phone, email, address and hours.
2. Interior/Furniture/Project business-line rules.
3. Quote and invoice numbering starting points.
4. Final payment terms and ledger account mapping.
5. Whether existing legacy users receive forced password reset and 2FA enrollment.
6. Whether WhatsApp marketing broadcasts are allowed at all.
7. WhatsApp template approval and Meta business access.
8. Telegram bot ownership and alert recipients.
9. AI provider, model, monthly budget and allowed channels.
10. Real-time inbox provider or polling-only decision.
11. Off-site backup provider and restore-drill schedule.
12. SEO crawler policy: which training crawlers are allowed/blocked.
13. Whether long-form content uses Sveltia or the Woodex CMS only.
14. Theme presets and final design token values.
15. Static-to-CMS conversion order and owner for each group.
16. Whether publishing needs a second-person approval.
17. Production staging domain and cutover date.

---

## 13. Risk register

| Risk                                    | Impact   | Mitigation                                                                   |
| --------------------------------------- | -------- | ---------------------------------------------------------------------------- |
| Legacy data is inconsistent             | High     | Read-only source, import checksums, reconciliation and owner approval        |
| Money totals differ after parsing JSON  | Critical | Recalculate server-side, stop on discrepancy, import exception report        |
| Duplicate contacts/leads                | High     | Normalize phone/email, merge suggestions, preserve legacy refs               |
| Static file still shadows CMS page      | High     | Static-shadow indicator, per-group compare and explicit file deletion        |
| Hostinger does not delete removed files | High     | Explicit deletion step and URL smoke after deploy                            |
| Cron is missing                         | High     | Health heartbeat and owner runbook alert                                     |
| Dependency drift                        | High     | Commit `composer.lock`, `composer install --locked`, Composer audit          |
| Provider credentials fail               | Medium   | Settings test actions, webhook diagnostics and provider staging checks       |
| Automation sends incorrectly            | Critical | Consent guard, caps, quiet hours, owner switch, dry-run and logs             |
| Builder allows unsafe HTML              | Critical | Owner-only raw HTML, DOM sanitizer, payload regression tests                 |
| 2FA owner lockout                       | High     | Tested break-glass recovery path before production                           |
| Backup cannot restore                   | Critical | Off-site target and scheduled restore drill                                  |
| Documentation claims drift              | Medium   | Generate counts from CI and update status after every wave                   |
| Scope expands into SPA/multitenancy     | High     | Keep Laravel/Livewire and single-studio architecture; record scope additions |

---

## 14. Final acceptance checklist

### System

- [ ] Laravel OS is the only writable application backend.
- [ ] v2.1 admin writes are disabled or routed through OS compatibility adapters.
- [ ] Owner/admin/sales/accountant/editor/support permissions are verified.
- [ ] 2FA and break-glass recovery work.
- [ ] Audit log captures sensitive actions.

### CRM and Sales

- [ ] Public forms, chat and WhatsApp create CRM leads through one action.
- [ ] Lead tracking and SLA states work.
- [ ] Estimate-to-quote flow is idempotent.
- [ ] Sent quote revisions are immutable.
- [ ] Invoice/payment/receipt flow is reconciled.
- [ ] Ledger balances and accounting periods are enforced.

### Projects and Inbox

- [ ] Accepted quote creates one project.
- [ ] Client portal links are signed and expiring.
- [ ] Private media cannot be guessed/downloaded.
- [ ] Inbox assignment, consent and opt-out work.
- [ ] WhatsApp and Telegram webhooks are signed/idempotent.
- [ ] Automated messages obey caps and quiet hours.

### Website

- [ ] All target pages exist in CMS with original URLs.
- [ ] Builder works at desktop/tablet/mobile widths.
- [ ] Content, media, templates and menus are manageable by staff.
- [ ] Theme tokens control the public design.
- [ ] SEO overview and redirects are operational.
- [ ] SEO agent suggestions require human approval.
- [ ] Speed and site-health findings are actionable.
- [ ] Every converted URL passes parity before static retirement.

### Deployment and operations

- [ ] PHP 8.4 and required extensions are enabled.
- [ ] `composer.lock` is committed and CI is active.
- [ ] Fresh MySQL install passes.
- [ ] Database update path passes.
- [ ] Cron heartbeat and queue drain are visible.
- [ ] SMTP/provider tests pass in staging.
- [ ] Off-site backup and restore drill pass.
- [ ] Hostinger staging has been observed for one business week.
- [ ] Main-domain cutover has rollback files/database backup.

---

## 15. Final target state

When this plan is complete, Woodex will have one governed Laravel system:

```text
Overview
  ├─ CRM
  │   ├─ Leads
  │   ├─ Contacts
  │   └─ Tasks & Visits
  ├─ Sales
  │   ├─ Estimations
  │   ├─ Quotations
  │   ├─ Invoices
  │   ├─ Payments
  │   └─ Documents
  ├─ Accounts
  ├─ Projects
  ├─ Inbox
  ├─ Automation
  ├─ Marketing
  ├─ Website
  │   ├─ Pages & Builder
  │   ├─ Content & Media
  │   ├─ SEO & SEO agent
  │   ├─ Speed & Site health
  │   └─ Theme
  └─ System
      ├─ Users, roles and Me
      ├─ Settings and integrations
      ├─ Audit and health
      ├─ Imports and updates
      └─ Backups and privacy
```

The legacy v2.1 system remains available only as a controlled migration source and archive. Woodex OS becomes the single source of truth for business data, public content, workflows, automation, security, reporting and operations.

**Recommended first execution step:** approve Wave 0, create the staging database, enable CI/lockfile work and produce the v2.1-to-OS import inventory before changing production behavior.
