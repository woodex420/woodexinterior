# 03 · Existing features matrix

Every row carries its evidence and a status from the master prompt's vocabulary:
**Working** (verified end-to-end), **Partial**, **UI-only**, **Mocked**, **Broken**, **Missing**,
**Blocked**, **Not applicable**.

The action column uses the owner's required vocabulary:

| Action | Meaning in this repository |
|---|---|
| **KEEP** | Works; keep as is |
| **MIGRATE** | Exists in the legacy frontend/reference and must be carried into Laravel |
| **IMPROVE** | Exists but has a verified gap |
| **MERGE** | Duplicate or fragmented; consolidate |
| **REPLACE** | Current implementation should be replaced (with a documented reason) |
| **BLOCKED** | Cannot be verified or completed in this environment |

> Scope note: the brief's "migrate the legacy frontend" and the code's own plan (`docs/13`) both
> define the legacy frontend as **79 exported pages + home** served from `platform/public`. The v2.1
> pro **admin source** is not available (§01-6), so every "migrate" row below refers to the visible
> frontend output or to features already ported from it — never to an unseen file.

---

## 1. Platform modules

| # | Module | What exists (verified) | Status | Action | Evidence |
|---|---|---|---|---|---|
| M-01 | **Overview / Dashboard** | KPI cards, bell drawer, my-day, notifications, global search | Working | KEEP | `smoke` 200 `/admin`; `AdminAccessTest`; prior browser battery (docs/29) |
| M-02 | **Approvals** | Quote-approval queue with decide actions | Working | KEEP | `Approvals` component; `P10SidebarHubsTest`; route `can:quotes.approve` |
| M-03 | **Reports** | Revenue & collections, sales funnel, receivables, CSV export | Partial | IMPROVE | `ReportsKitTest` covers the calculations; `P10SidebarHubsTest` renders `/admin/reports` and asserts tab content; no dedicated `ReportsHub` component test |
| M-04 | **CRM — leads** | List, board, lead page, stages, assignment rules | Working | KEEP | `LeadsHub`, `LeadShow`, `PipelineBoard`; `AdminAccessTest`, `LeadsTest` family; policies |
| M-05 | **CRM — contacts/companies** | Contacts list, company merge view, statements | Working | KEEP | `ContactsIndex::mount()` authorizes; `CompaniesTest`, `ContactsTest` |
| M-06 | **CRM — tasks & visits** | Task list, site visits, reminders | Partial | IMPROVE | `P10SidebarHubsTest` renders `/admin/tasks` (list and visits tab); no dedicated `TasksHub` behaviour test |
| M-07 | **Sales — estimations** | Estimate builder, rates, line items | Working | KEEP | `EstimatesTest`, `EstimateBuilder` |
| M-08 | **Sales — quotations** | Builder, revisions, approvals, portal accept/changes, frozen PDF, templates, rate book, description library | Working | KEEP | `QuoteBuilderTest`, `QuoteSendingTest`, `QuoteScreensTest`, `DocumentLibraryTest` |
| M-09 | **Sales — invoices/payments** | Invoices, milestones, payments with proof, allocations, dues | Working (logic) / UI needs data | KEEP + verify | `AdvanceInvoiceTest`, `PaymentsTest`, `GlSalesPostingTest`; demo DB has 0 invoices/payments → UI E2E not exercised |
| M-10 | **Accounts** | Chart, journals, periods, payment-method accounts, GL posting | Working | KEEP | `AccountsLedgerTest` 16, `GlSalesPostingTest` 12; journals balance asserted in docs/29; **not re-verified against live data this phase** |
| M-11 | **Projects** | Projects list/board, milestones, updates, handover items, project portal + receipts | Working (logic) / UI needs data | KEEP + verify | `ProjectsTest`, `ClientPortalTest`, signed `p/` routes; demo DB 0 projects |
| M-12 | **Client portal** | Signed links, six-digit code entry, quote/invoice/project pages, handover sign-off | Working | KEEP, **fix nav** | Route smoke `/portal` 200; `ClientPortalTest`; **sidebar shows it as planned P4b** (N-03) |
| M-13 | **Inbox / conversations** | Conversations, messages, media, canned replies, SLA | Working (logic) / UI needs data | KEEP + verify | `ConversationsTest`, `ConversationMediaTest`, `CannedRepliesTest`; demo DB 0 conversations |
| M-14 | **Web chat** | Widget script, start/send/poll, intake to inbox (off by default) | Working | KEEP | `WebChatWidgetTest`; `/chat/widget.js` 404s while disabled (intended) |
| M-15 | **Chat bot** | Rule-based engine, knowledge base, usage meter | Partial | IMPROVE | `BotEngineTest`; nav item still marked P4/planned; AI provider key-gated |
| M-16 | **Automation** | Automations, sequences, step runs, execution logs, alert rules | Working (logic) / UI needs data | KEEP + verify | `AutomationsDashboardTest`, `SequenceEngineTest`, `AlertRulesTest`; demo DB 0 automations, 0 alert rules |
| M-17 | **Marketing — social** | Composer, review flow, scheduler, captions | Working (logic) / provider-gated | KEEP | `SocialPlannerTest`; Meta/LinkedIn credentials absent → external publish **Blocked** |
| M-18 | **Marketing — lead ads** | — | **Missing** | MIGRATE (P7) | No route/component; nav marks P7; `docs/01` describes it as P7 |
| M-19 | **System — users/roles** | 8 roles, 44 permissions, 2FA enforced, user admin | Working | KEEP | `UsersAndSettingsTest`, `AdminAccessTest`; middleware `EnsureTwoFactorIsEnabled` |
| M-20 | **System — audit log** | 58 records (demo), actor/action/target | Working | KEEP | `AuditLogTest`, `/admin/system/audit` |
| M-21 | **System — change requests** | Board, statuses, decisions | Working | KEEP | `ChangeRequestsBoardTest` |
| M-22 | **System — health** | App/DB/queue/storage/provider checks, updates screen | Working | KEEP | `DatabaseUpdatesTest`, `smoke` 200 |
| M-23 | **System — integrations** | Status list, Google OAuth connect | **Partial / UI-only** | IMPROVE | Screen says "Connection flow is not implemented in this release" for providers; Google OAuth routes exist and are key-gated |
| M-24 | **System — data import** | CSV import, mapping, dry-run | Working | KEEP | `SystemScreensTest`; Jan-2025 lead sheet imported earlier (21 leads) |
| M-25 | **System — recycle bin** | Soft-deleted records restore/purge | Working | KEEP | `QuoteScreensTest`, `/admin/recycle-bin` 200 |
| M-26 | **Website — pages/media/menus/heroes/content/team** | All screens live, all tested | Working | KEEP | 26 Website test files (174 tests) |
| M-27 | **Website — SEO / speed / health / theme** | SEO overview + agent, speed checks, health, theme tokens | Working | KEEP | `SeoAgentTest`, `SpeedDashboardTest`, `ThemeTokensTest`, `WebsiteAdminParityTest`; Google PSI key-gated for live speed scores |

---

## 2. Website delivery (the migration question)

| # | Item | Verified state | Status | Action | Evidence |
|---|---|---|---|---|---|
| W-01 | **Public URL ownership** | 80 static page folders (79 + home) answer **byte-for-byte**; 143 CMS pages exist; 80 of them share a URL with a static file; 63 are served by Laravel | Working, but **two sources for the same URL** | **Decision required** | sha256 match for `/about/`; DB slug set; `docs/13` §3 |
| W-02 | **Static → CMS conversion** | Plan exists in 8 groups; **0 groups converted**; comparison tool `tools/website/compare.php` exists (Observed, not yet run) | Not started | MIGRATE | `docs/13` §4–§7; tool header |
| W-03 | **Publishing a CMS page to a shadowed URL** | Does not change the public page (static file wins); only `?preview=1` reaches the draft | Partial (by design) | IMPROVE / decide | `/about/` static; `SiteRouter` + `routes/web.php` fallback comment |
| W-04 | **Sitemap** | 143 URLs, all CMS pages | Working | KEEP | `curl /sitemap.xml` → 143 `<loc>` |
| W-05 | **Redirects** | Admin screen + CSV import/export + hit counter; demo DB 0 rows | Working | KEEP | `WebsiteAdminTest` redirect tests |
| W-06 | **Frontend HTML output** | 80 pages: 0 missing H1, 0 multi-H1, 0 images without alt, 0 images without dimensions, 0 duplicate IDs, 0 empty links; 274 form fields, 3 placeholder-only, 3 honeypots | Working | KEEP | Phase 0 DOM scan (sample-level; full per-page audit is Phase 1) |
| W-07 | **Legacy form bridge** | `api/forms.php` → CRM; origin check + throttle | Working | KEEP | Route + `PublicLeads` tests; not re-exercised this phase |
| W-08 | **Two frontends in the repo** | `woodex/` (40 files, an earlier design) and `platform/public/*` (the approved v1 export) | Confusing | **MERGE/remove one** | directory listing; `README` calls `woodex/` "design reference" |

---

## 3. Page Builder — capability matrix

Evidence column references the test file or the browser check that actually exercised it.

| Capability | Status | Evidence | Gap / next step |
|---|---|---|---|
| Add / delete / duplicate / reorder sections | **Working** | `PageBuilderLiveTest`; `builder-check` drag | — |
| Drag-and-drop reorder with handle | **Working** | `builder-check` (Hero ↔ Intro) | — |
| Undo / redo (buttons + Ctrl+Z/Y/Shift+Z), 25 deep | **Working** | `BuilderHistoryAuditTest` (5 tests); `builder-check` | — |
| Layers panel (list, search, jump, move, hide) | **Working** | `BuilderHistoryAuditTest`; `builder-check` | — |
| Live preview follows typing, before first save | **Working** | `PageBuilderLiveTest`; `builder-check` | — |
| Click a section in the preview to edit it | **Working** | `ClickToEditTest`; `builder-check` | — |
| Section style panel (background, spacing, width, align, name, hide) | **Working** | `SectionStyleTest` (8) | — |
| Free-form HTML block, sanitised | **Working** | `CanvasHtml` tests (`CampaignCanvasTest`) + `SiteRenderingTest` arm | Sanitiser is allowlist-based; Phase 8 to fuzz it |
| Page templates (start from / apply / save back) | **Working** | `PageTemplatesTest` (6, incl. 50-layout import) | — |
| Saved sections (reusable) | **Working** | `SectionLibraryTest` | — |
| Theme presets + token builder | **Working** | `ThemePresetsTest`, `ThemeTokensTest` | — |
| Draft / publish / scheduled publish gate | **Working** | `WebsiteAdminTest` publish gate; `PageValidator` | Public effect limited by W-01 for 80 URLs |
| Version history + restore | **Working** | `SectionStyleTest` restore test; `/admin/website/pages/{page}` history card | — |
| SEO + accessibility check in editor and list | **Working** | `BuilderHistoryAuditTest`, `WebsiteAdminTest` | — |
| Responsive **preview** (desktop/tablet/mobile frame) | **Working** | editor device toggle; `builder-check` uses desktop | — |
| Responsive **style overrides** per breakpoint | **Missing** | no per-breakpoint settings in `SectionStyle` / `blocks.settings` | Phase 8 design |
| Nested containers / grid / columns / flex / spacer | **Missing** | typed block model has no container node | Big architectural decision |
| Free canvas: absolute placement, zoom, multi-select | **Missing** | not present | The v2.1 pro canvas is not in the block editor |
| Copy / paste / cut sections, multi-select | **Missing** | only duplicate and drag | Phase 8 |
| Per-element inspector (typography, colour, border, shadow on any node) | **Missing** | only section-level presets | Phase 8; HTML block is the escape hatch |
| Component registry with schemas / plugin boundary | **Missing** | `BlockType` enum is the registry | Acceptable for now; document it |
| Autosave + conflict detection | **Partial** | `refreshPreview()` persists the draft on the debounced preview; no version/ETag conflict check | Phase 8: add `updated_at` precondition |
| Server-side schema validation for every block | **Partial** | `SavePage` + `PageValidator` + per-type field cleaning | Phase 8 to fuzz invalid payloads |
| Publish/unpublish permission enforced server-side | **Working** | `WebsiteAdminTest` "editor may change but not publish"; policy/permission `website.publish` | — |
| Media permissions / missing media handling | **Working** | `MediaLibrary` tests; `WebsiteMediaController` 404s | — |
| Network-interruption safety | **Not tested** | — | Phase 8 acceptance test #12 |

**Builder verdict:** the *typed-section* builder is real, tested and materially complete for the
studio's pages. It is **not** a general-purpose canvas editor: nesting, per-element styling,
breakpoint overrides and copy/paste are genuinely absent, not hidden. Against the brief's component
library (layout containers, basic elements, media, marketing, business, CMS, forms, ecommerce), the
typed model covers the **marketing/business/CMS/form** families at section granularity and lacks the
**layout/basic** families at element granularity.

---

## 4. Frontend reference comparison (v2.1 pro)

| Reference item | State in Woodex OS | Status | Action |
|---|---|---|---|
| Website sidebar (All pages … Website Settings) | Reproduced 1:1, old names | Working | KEEP |
| Section library, hero slides, SEO overview, site health | Rebuilt 19 Oct | Working | KEEP |
| Page builder: drag, click-to-edit, undo/redo, layers | Rebuilt 19 Oct | Working | KEEP |
| Section style panel (background/spacing/width/align) | Rebuilt 19 Oct | Working | KEEP |
| HTML/code block | Rebuilt 19 Oct | Working | KEEP |
| Templates library (50) | Derived from the studio's own pages | Working | KEEP, confirm names |
| Per-element Content/Style/Advanced panels | Not built | Missing | Decide Phase 8 |
| Nested layout containers | Not built | Missing | Decide Phase 8 |
| Responsive style controls | Not built | Missing | Decide Phase 8 |
| 26 fonts / font picker | Not built (3 font families) | Missing | Decide Phase 8 |
| Link picker (choose an internal page) | Not built (free-text URL) | Missing | Phase 8 |
| Import/export of a page's schema | Not built | Missing | Phase 8 |
| Builder source (`frontend-v1/builder.js`, `templates-v26.js`) | **Not available in any accessible repository** | **Blocked** | Owner to supply a ZIP/branch |

---

## 5. Quality, security and delivery findings (Phase 0)

> **Status after Phase 1 (19 Oct):** S-03, Q-02, Q-03, Q-04, Q-05 and Q-08 are **closed**; S-01 is
> **partially closed** (66 parameterless screens matrixed, parameterized routes still policy-tested);
> Q-01 (`composer.lock`) remains open pending the first CI run. See `10_PHASE1_CLOSURE.md`.

| ID | Priority | Area | Finding | Evidence | Recommended action |
|---|---|---|---|---|---|
| Q-01 | **P1** | Supply chain | No `composer.lock` in root or `platform/` | `git ls-files \| grep composer.lock` → none | Commit a lockfile; CI installs with `--locked` |
| Q-02 | **P1** | CI | Workflows exist only as templates (`docs/ci/*.yml`); no `.github/workflows` | `ls .github` → absent | Activate `platform-ci.yml` + deploy workflow |
| Q-03 | **P2** | Code style | Repo-wide `pint --test` fails on **33 files**, while `PROJECT_STATUS.md` claims "Pint 607 files clean" | `pint --test` output; `/tmp/pint.txt` | Run `pint` once, commit; then gate it |
| Q-04 | **P2** | Docs truth | `PROJECT_STATUS.md` says 1,145 tests / 118 files; actual **1,189 / 122**. Same file claims Pint clean (see Q-03) | test run; pint run | Update status file from CI output, not by hand |
| Q-05 | **P2** | Static analysis | Full PHPStan run: **6 errors in 2 files** (`Domain/Website/Contracts/LibraryEntry.php:21`, `Livewire/Admin/Website/SiteHealthIndex.php:121–124`), while the status file claims "Larastan 0" | `phpstan analyse` EXIT=1 | Add the two array-shape docblocks; gate PHPStan in CI |
| Q-06 | **P2** | Test data | Demo DB has 0 invoices, payments, projects, conversations, automations, alert rules, social posts, navigation items, redirects, change requests, content entries | direct SQL counts | Seed a minimal E2E fixture set for module verification |
| Q-07 | **P2** | Coverage | Reports and Tasks are covered only by the hub render test and `ReportsKitTest`; no component-level behaviour test | `P10SidebarHubsTest`, `ReportsKitTest` | Add behaviour tests before Phase 4 |
| S-01 | **P1** | Authorization | 54 admin routes have no route-level permission; components authorize in `mount()` (spot-checked 3). Not yet proven for all | route middleware dump | Phase 1: matrix every route × role through HTTP |
| S-02 | **P2** | Auth | 2FA is mandatory middleware on the whole admin; policy tested | `EnsureTwoFactorIsEnabled`, `AdminAccessTest` | Verify recovery/lockout path in Phase 1 |
| S-03 | **P1** (website) | XSS | `CanvasHtml::clean()` is bypassable with unquoted attributes and HTML entities; output is printed raw on public pages | executed payloads (see `07` §3) | DOM-based allow-list sanitiser + regression tests; interim owner-only HTML block |
| Q-08 | **P3** | Test tooling | `builder-check.mjs` flags an aborted `__preview-beacon` as a failure when the preview reloads; 1 of 3 runs red with all functional steps green | three runs on 19 Oct | Ignore aborted beacon requests in the check |
| I-01 | **P2** | Integrations | Provider "connection flow is not implemented in this release" on the Integrations screen | Blade line 73 | Label it UI-only in the matrix; no fake success |
| N-01/N-02/N-03 | P2 | Navigation | Duplicate Alert Rules; undefined P4b; portal shown as planned though built | §02 | Owner decision + one small nav fix |
| B-04 | **P1** | Release evidence | Production deployment and database unverifiable from the sandbox | curl SSL failure; no credentials | Owner supplies a read-only check or runs the release checklist on the host |
| B-05 | — | Reference | **Resolved** — v2.1 pro source found at `marketingwoodex-cloud/-marketingwoodex@720d817` (branch `arena/8a776c65-marketingwoodex`) | GitHub tree API: `frontend-v1/builder/templates-v26.js`, `builder.js`, `api/builder.php` | Use as Phase 2 reference (`09`) |

---

## 6. Pint failures (exact list, Phase 0)

```
app/Console/Commands/SeoAgent.php                  app/Domain/Sales/Actions/RejectPayment.php
app/Console/Commands/SocialAgent.php               app/Domain/Sales/Actions/RequestQuoteApproval.php
app/Domain/Bot/Actions/ReplyWithBot.php            app/Domain/Sales/Actions/RequestQuoteChanges.php
app/Domain/Conversations/Actions/CheckConversationSla.php      app/Domain/Sales/Actions/SuggestQuoteAcceptance.php
app/Domain/Conversations/Actions/ReceiveWebChatMessage.php     app/Domain/System/Actions/GenerateMonthlyReport.php
app/Domain/Conversations/Actions/SendConversationMessage.php   app/Domain/System/Actions/SetChangeRequestStatus.php
app/Domain/Crm/Actions/CheckLeadSla.php            app/Integrations/WhatsApp/Actions/CheckWhatsAppQuality.php
app/Domain/Crm/Actions/NotifyMentions.php          app/Integrations/WhatsApp/Actions/CheckWhatsAppUsage.php
app/Domain/Crm/Actions/SendReminders.php           app/Livewire/Admin/System/SettingsPage.php
app/Domain/Crm/Jobs/SendLeadAlert.php              app/Livewire/Admin/Website/MenusIndex.php
app/Domain/Projects/Actions/ChangeProjectStage.php app/Livewire/Admin/Website/SectionLibraryIndex.php
app/Domain/Projects/Actions/ShareProjectUpdate.php app/Livewire/Admin/Website/SeoOverviewIndex.php
app/Domain/Sales/Actions/AcceptQuote.php           app/Settings/BotSettings.php
app/Domain/Sales/Actions/ChaseQuotations.php       routes/admin.php
app/Domain/Sales/Actions/DecideQuoteApproval.php   tests/Feature/SequenceEngineTest.php
app/Domain/Sales/Actions/RecordPayment.php         tests/Feature/Website/WebsiteAdminParityTest.php
app/Domain/Sales/Actions/RecordQuoteView.php
```

These are pre-existing: none of them is a file touched by the 19 Oct builder work, and the builder
files themselves pass `pint --test`.
