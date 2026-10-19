# Woodex OS: build state

_Update at the end of every task (docs/03 Part A §7)._

## Resume here (project memory, saved 17 Oct 2026 PKT — agent export MERGED, gate green)

- **AGENT EXPORT MERGED (17 Oct, rebuilt after a sandbox reset):** the Drive export
  (`woodexinterior-all-work-2026-10-17.zip`, SHA-256 d8509402…7a07d, base 7f5593c, 96 modified + 75 new
  files) merged as ONE merge commit on top of `6080d97` (git 3-way, 14 conflicts resolved by hand).
  Gate on the merged tree: **861 passed / 1 skipped / 0 failed, Larastan 0 errors, Pint 589 clean**
  (branch alone was 569). Owner GO received 17 Oct — land + push + deploy.
- **Integration decisions (owner, 17 Oct via ask_user):** Telegram keeps MY full command centre +
  inline buttons inside THEIR hardened webhook shell; WhatsApp keeps WAP-001 encrypted credentials +
  D-112 acceptance hint inside their durable recovery queue; AcceptQuote keeps both `?User $by` and
  `?int $revision`; MCP/Google/Description Library/Document Designer merged, dormant until credentials;
  their docs renumbered 12–16 → 21–25.
- **Hardening adopted:** link authority requires an active user + private-or-legacy chat_type; a link
  code can never steal another account's active chat; forged callbacks ignored silently; 30/min
  per-sender door throttle + 5/min for /start.
- **P8 MASTER PLAN approved (owner, 17 Oct):** one entity (Interior/Furniture as business lines);
  MySQL for production after the GL wave; rule-based automation first; web chat widget before
  WhatsApp Cloud API; Jan-2025 leads imported now, financial staging when files arrive.
  See docs/26-P8-MASTER-PLAN.md. Wave 1: deploy → in-dashboard PDF preview → lead import →
  activate document core → furniture quotation pictures.

## Resume here (project memory, saved 17 Oct 2026 PKT — deploy prep + agent-work recovery plan)

- **DEPLOY DECISIONS (owner, via ask_user):** (1) the agent's unpublished 744-test work is recovered by
  RESUMING the agent's closed session and having it push to its branch — then I pull and run FULL
  verification (line-by-line diff review, full gate, screen test-drive, claims cross-check) with the
  owner's sign-off before any merge/deploy; (2) deploy NOW = current verified branch tip (no master
  switch yet); (3) always: check agent work, ask questions, then start (standing owner instruction).
- **Agent-work facts (verified 17 Oct):** the agent's branch `arena/fe1287c9-woodexinterior` on GitHub has
  ZERO commits beyond PR #2 (2FA lockout fix + Telegram runtime diagnostics, merged into our branch 16 Oct
  22:18 UTC, covered by our gates). NOTHING of their latest session (App Connections hub, MCP bridge,
  Description Library, Document Design, payment/quotation hardening, Jan-2025 import, docs/20 checklist,
  744 tests) is on GitHub or Drive — it lives only in their closed sandbox (preview
  8080-isnahsykyf37tp24ghz4e.e2b.app). Recovery = resume that session, have it commit+push, then I verify.
- **Deploy-safety guard added (commit after 8ed2769):** `ThemeCss::css()` returns `:root {}` (uncached) when
  `theme_tokens` is missing — the window between a Hostinger deploy and the one-click Database update
  degrades to "no override" (pages keep their own defaults = same v1 look) instead of SQL errors; CMS pages
  and both theme routes stay 200. Test added (7 in ThemeTokensTest now). Gate: 569 passed / 3 856,
  Larastan clean, Pint clean 544.
- **Deploy checklist for the owner (docs/06 §2):** hPanel → Advanced → Git (branch
  `arena/8be318a2-woodexinterior`) → Redeploy if auto-deploy is off → open the site → one-click Database
  update (adds canned_replies, theme_tokens, saved_sections; ProductionSeeder seeds them idempotently) →
  check: site looks unchanged, /assets/theme.css serves :root with --navy #0C1628, admin → Theme Builder
  opens. After deploy: my earlier commits c84df84…8ed2769 go live (reports kit, audit log, canned replies,
  theme tokens + builder, V2-2 part 1 section library/builder).
- **NEXT after the agent pushes:** full verification per the owner's decision — pull their branch, review
  every file in the diff (security, lane boundaries — their checklist strays far beyond the agreed
  Automation/bot/alert-rules lane into documents, payments, integrations), full gate on the merged result,
  screen test-drive, cross-check claims (66 tables, Jan-2025 16+16 numbers), written report, owner
  sign-off, THEN merge/deploy. Expect conflicts/overlap in STATE.md, ProductionSeeder, AdminNavigation,
  routes — and lane questions to put to the owner.

## Resume here (project memory, saved 17 Oct 2026 PKT, after V2-2 part 1 — section library + page builder)

- **V2-2 part 1 is DONE and shipped:** the section library and the page-builder upgrades (docs/12 §5.2/§5.3).
  `BlockType::description()` + `category()` (Hero/Proof/Content/Conversion) drive the library card in the page
  editor — one declaration, library + editor both use it. `saved_sections` table + `SavedSection` model
  (type cast to BlockType, data JSON, created_by): configure a section once, ★ "Save as section", it appears
  under "Saved by the studio", Insert drops a copy (id=null) on any page, Delete removes it. Drag-to-reorder
  via Livewire 4 `wire:sort="sortBlocks"` (ephemeral `key` per block, assigned in reload/add/insert/duplicate,
  never persisted — SavePage ignores it); ⧉ duplicate; ↑↓ kept as fallback. Device preview in the right
  column (desktop 1280/tablet 820/mobile 390, scaled iframe, `?preview=1`). Shareable preview links:
  `PreviewLink` (Domain/Website/Support) = slug + expires + HMAC(app.key) signature — nothing stored, dies on
  its own; `SiteRouter::mayPreview(request, page)` accepts staff session OR valid token (V2-2 §5.3 "shareable
  preview link"). Tests: `tests/Feature/Website/SectionLibraryTest.php` (7).
- **Test gotcha confirmed:** Laravel's HTTP test client TRIMS the trailing slash — public-page tests must use
  `siteGet()` from `InteractsWithPublicWebsite` (calls SiteRouter directly), and `url()` also trims it, so
  PreviewLink builds via `Page::urlFor()` (keeps the canonical slash). BelongsTo docblocks: use
  `@return BelongsTo<User, $this>` (not `self`/concrete class).
- **V2-2 remaining (next):** campaign free-canvas mode (docs/12 §5.3, landing pages only, excluded from token
  re-skins), inline text editing in the rendered page, section thumbnails. Then V2-3 header/footer builder.
- **Preview restarted fully updated** (process `woodex-os-preview-b24e9189`, port 8080): migrate +
  ProductionSeeder run; `/`, `/admin`, inbox, reports, canned replies, theme builder, pages, audit, furniture
  editor all 200; theme tokens 15 rows navy #0C1628.
- Gate green at 568 passed / 3 850 assertions, Larastan clean, Pint clean 544 files.

## Resume here (project memory, saved 17 Oct 2026 PKT, after V2-1 — theme tokens + theme builder)

- **V2-1 is DONE and shipped (owner decisions: live v1 values, wire the live site — both confirmed via
  ask_user):** `theme_tokens` table (group/key/value/label/type/sort) + `ThemeToken` model
  (`app/Domain/Website/Models/`) seeded by `ThemeTokensSeeder` (15 tokens in the LIVE CSS's own
  vocabulary — `--navy #0C1628`, `--wood #B8956A`, `--surface #F4EFE7`, `--ink #12151C`, `--muted
  #6A6560`, `--wx-font` Plus Jakarta Sans stack, `--r-lg 16px` — from the final :root cascade of
  site-p21.css → v1-p21.css → the inline wx-crit layer). `ThemeCss`
  (`app/Domain/Website/Support/`) renders tokens + ALIASES (`--wx-navy`, `--beige`, `--wx-r-*` …) +
  DERIVED v2 vocabulary (`--color-navy-900: var(--navy)`, tints via color-mix, `--font-heading`,
  `--radius-ui`) into one :root block, cached a day (`woodex:theme-css`), content-hashed.
- **Two URLs, one theme:** (1) `/assets/theme.css` — the stylesheet every v1 static page ALREADY links
  last (it beat the inline wx-crit styles in the cascade); the static `:root{}` stub was DELETED so the
  app serves it (60 s cache) → saving a token in the Theme Builder re-skins the LIVE woodex.com.pk with
  no deploy (verified live in the preview). Deploy safety: `tools/deploy/after-composer.php` deletes the
  stale stub Hostinger would leave behind (deploy replaces but never deletes). (2) `/theme/{hash}.css` —
  what v2 pages link (head.blade.php, after @vite); hash = content so fresh URLs are immutable, old
  hashed URLs serve current tokens no-cache.
- **Theme Builder:** Website → Theme Builder (`admin.website.theme`, `theme.manage`) — grouped colour
  pickers (hex) + text inputs (rgba/shadow) + font/radius, Save (flushes cache, mints hash), "Back to
  v1 palette" (resetToV1 re-applies the seeder's values), "Open the site". Nav placeholder wired.
  Registered `ThemeTokensSeeder` in ProductionSeeder (7th) so `woodex:update` installs it.
- **NEXT — V2-2: the section builder / page builder** (docs/12 §5.2, V2-2): drag-and-drop (Livewire 4
  `wire:sort` is available), device preview, draft → publish. The 15 section templates already read
  tokens. Then V2-3 header/footer builder. P5 exit: owner completes a full day of approvals from
  Telegram — S1–S3+S5+S6 shipped; S4/S7 = automation agent's lane.
- Tests: `tests/Feature/ThemeTokensTest.php` (6). Gate green at 561 passed / 3 766 assertions,
  Larastan clean, Pint clean 540 files. Preview verified: login 302, `/`, `/furniture/` (v2 page,
  links /theme/{hash}.css), `/admin/website/theme`, `/assets/theme.css` all good; a navy change showed
  up live and was reverted.

## Resume here (project memory, saved 17 Oct 2026 PKT, after CNV-004 — canned replies)

- **CNV-004 is DONE and shipped:** canned replies with variables. `canned_replies` table (title, body, locale
  en/ur, keywords, usage_count, is_active, position) + `CannedReply` model
  (`app/Domain/Conversations/Models/`) — `matchesFor()` filters by title/keywords/body for the "/" picker;
  `renderFor()` fills {first_name} (first word of the contact name), {studio_name} (brand_name),
  {studio_hours} (BusinessHours compressed to "Mon–Sat, 10:00–19:00"), {quote_link} (latest sent/viewed/
  accepted/expired quote's `QuoteLinks::portal()` link; empty when none). Inbox composer: typing "/" shows
  the picker; picking fills the draft and counts usage. Management: Settings → Canned replies
  (`admin.canned-replies`, settings.manage) — add/edit/hide. `CannedRepliesSeeder` (7 defaults, EN + Roman
  Urdu) runs in ProductionSeeder so `woodex:update` installs it on the server. Tests:
  `tests/Feature/CannedRepliesTest.php` (6). Gate green at 555 passed / 3 720 assertions, Larastan clean,
  Pint clean 534 files.
- **NEXT — Wave 3: V2-1, the design tokens from v1's own design (D-116).** v1's HTML is IN THE REPO:
  `platform/database/migrations/2026_10_17_000200_reload_the_complete_website.php` (the complete website
  reloaded into the CMS) — extract the palette/typography/spacing/radii from it into the v2 Tailwind v4
  `@theme` tokens (docs/12 §6: assisted parse → tokens → sections). `/tmp/v1` is GONE (VM reset) — use the
  migration, or re-export from the CMS pages table. Then V2-2 (the section builder). Wave 2 is complete
  (reports kit + SYS-007). P5 exit: owner completes a full day of approvals from Telegram — S1–S3+S5+S6
  shipped; S4/S7 = automation agent's lane.

## Resume here (project memory, saved 17 Oct 2026 PKT, after SYS-007 — the audit-log viewer)

- **SYS-007 is DONE and shipped:** System → Audit log (`app/Livewire/Admin/System/AuditLog.php`, route
  `admin.system.audit`, permission `audit.view`, nav item 'Audit log'). Three tabs — model changes (spatie
  activity_log: description, causer, subject, event badge, expandable what-changed from `attribute_changes`),
  webhook deliveries (provider, external_id, signed/unsigned, status), integration calls (action, ok/failed,
  HTTP code, duration). Filters: causer select, subject-type select, text search; paginated. Tests:
  `tests/Feature/AuditLogTest.php` (3). Gate green at 549 passed / 3 688 assertions, Larastan clean,
  Pint clean 529 files.
- **Wave 2 shipped so far:** the P3 reports kit (funnel, revenue & collections, receivables, CSV, ranges)
  and SYS-007. **Remaining Wave 2:** CNV-004 (docs/14 §2.2 conversations remainder) → then Wave 3: V2-1
  design tokens from v1's own design (D-116) → V2-2 builder. P5 exit: owner completes a full day of
  approvals from Telegram (S1+S2+S3+S5+S6 shipped; remind: profile → Link Telegram, then /today /approvals
  /idea /health). S4 digests + S7 alert-rules builder wait on the automation agent's lane.

## Resume here (project memory, saved 17 Oct 2026 PKT, after the P3 reports kit — Wave 2 first item)

- **The P3 reports kit is DONE and shipped (RPT-002/005, INV-005b, RPT-009):** three screens behind real
  routes now — `/admin/reports/sales-funnel` (perm reports.view), `/admin/reports/revenue-collections`
  (perm reports.finance), `/admin/receivables` (perm invoices.view); nav placeholders replaced. Shared kit in
  `app/Support/Reports/`: `ReportRange` (presets this/last month, last 90 days, quarter, year + equal-length
  previous period), `Csv` (UTF-8 BOM, plain rupees), and the three query classes (SalesFunnelReport — leads by
  source/line/owner with quotes-sent/accepted/win-rate + lost reasons; RevenueCollectionsReport — quoted/
  accepted/invoiced/collected per month, by line, by method+bank via payment_allocations, outstanding today;
  ReceivablesReport — per-client aging 0/30/60/90+ worst-first + totals). Every screen: comparison row,
  CSV button (authorized), Print button. Tests: `tests/Feature/ReportsKitTest.php` (6). Gate green at
  546 passed / 3 677 assertions, Larastan level 6 clean, Pint clean 527 files.
  - Gotchas hit: Eloquent builders hydrate selectRaw aliases as enums/casts (use `DB::table` for aggregate
    reads); `Builder<Quote>` is NOT covariant with `Builder<Model>` (byDay takes `Query\Builder`);
    blade `@php(...)` inline takes one statement only — use `@php … @endphp` blocks.
- **P5 shipped:** S1+S2 (framework + reads), S3 (actions + inline buttons), S5 (health completion), S6
  (change-requests board). Remaining P5: S4 digests + S7 alert-rules builder = automation agent's lane;
  S8 leftovers (unlink on deactivation, quiet-hours batching) — small, fold into the next pass.
- **NEXT — Wave 2 remainder:** SYS-007 audit-log viewer (permission `audit.view` exists; data = spatie
  activity log on models + `WebhookEvent` + `IntegrationLog`) → CNV-004 (docs/14 §2.2 remainder) → then
  Wave 3 (V2-1 tokens from v1's own design — D-116 un-gated it). P5 exit: owner completes a full day of
  approvals from Telegram — remind them to link the chat (profile → Link Telegram) and try /today /approvals
  /idea /health.

## Resume here (project memory, saved 17 Oct 2026 PKT, after P5 S6 — the change-requests board)

- **P5 S6 is DONE and shipped (SYS-010):** System → Change Requests board (`app/Livewire/Admin/System/
  ChangeRequests.php` + view, route `admin.system.change-requests`, permission `system.change_requests`
  seeded for owner/admin — the nav placeholder is gone). Cards flow new → accepted → planned → in progress →
  done/rejected, each offering only its next move; closing requires a note. `SetChangeRequestStatus` action +
  `ChangeRequestUpdatedNotification` (bell + Telegram to the requester, never to the mover). Search covers
  title/body and CR-numbers; filters open/all/by-status. Tests: `tests/Feature/ChangeRequestsBoardTest.php`
  (5, incl. the /idea → board → phone loop). Gate green at 540 passed / 3 628 assertions, Larastan clean,
  Pint clean 518 files. `woodex:update` migrates + reseeds permissions, so the server picks it up on apply.
- **P5 shipped so far:** S1+S2 (framework + reads, `3fc70c7`), S3 (actions + inline buttons, `1fe9d08`),
  S5 (health completion, `3429465`), S6 (this board). S4 digests + S7 alert-rules builder are the automation
  agent's lane (docs/16 §1); S8 hardening leftovers (unlink on user deactivation, quiet-hours batching) remain
  small and unscheduled — do them with Wave 2.
- **NEXT — Wave 2 per docs/16 §10: the P3 reports kit.** Three screens behind the null routes in
  AdminNavigation lines 40/72/73: Receivables (RPT-005, per-client balances 30/60/90), Sales Funnel (RPT-002,
  leads → quotes → wins by source/line + period filter), Revenue & Collections (RPT-009-ish, invoiced vs
  collected per month) + the CSV export kit (INV-005b/RPT-002 export). Then SYS-007 audit-log viewer (uses
  existing `audit.view` permission), then CNV-004. P5 exit test: the owner completes a full day of approvals
  from Telegram — remind them to link their chat (profile → Link Telegram) and try /today /approvals /idea.

## Resume here (project memory, saved 17 Oct 2026 PKT, after P5 S5 — health completion)

- **P5 S5 is DONE and shipped (SYS-008):** one collector `app/Domain/System/Health/SystemHealthReport.php`
  feeds both System → Health and `/health`. New checks: database size (driver-aware: information_schema on
  MySQL, pragma on SQLite), last backup (newest .zip on the local disk), scheduled tasks with their last run
  (new `scheduled_task_runs` table written by `app/Listeners/RecordScheduledTaskRun.php` on
  `ScheduledTaskFinished` — task names normalised off the "'php' 'artisan'" prefix; pruned daily 04:45 after
  14 days). Health screen got a **Send test email** button (sends to the signed-in admin's own address via
  the array mailer pattern in tests) and a **Scheduled tasks** card. `/health` now answers DB size, last
  backup, stale task names, PHP/Laravel versions. Tests: `tests/Feature/SystemHealthTest.php` (5) — gate
  green at 535 passed / 3 604 assertions, Larastan clean, Pint clean 514 files. Note: `Mail::fake()` cannot
  see `Mail::raw()` (the fake's raw() is a no-op) — test raw mail with `mail.default=array` + the transport's
  messages().
- **P5 S3 (shipped earlier today):** `TelegramCallbackBus` + `/approvals /approve /assign /note` + inline
  buttons (Assign to me / Verify / Not matched), 24 h button expiry. Tests: TelegramActionCommandsTest (10).
- **Next per docs/16 §10 (Wave 1):** P5 S6 (change-requests board, SYS-010 — System → Change Requests list
  of /idea /issue entries with status flow and Telegram notify to the requester) → then Wave 2: P3 report
  screens (Receivables, Sales Funnel, Revenue & Collections + CSV), SYS-007 audit viewer, CNV-004. P5 exit
  test unchanged: the owner completes a full day of approvals from Telegram. S4 digests + S7 alert-rules
  builder are the automation agent's lane (docs/16 §1).

## Resume here (project memory, saved 17 Oct 2026 PKT, after P5 S3 — the action commands)

- **P5 S3 is DONE and shipped:** `TelegramCallbackBus` (`app/Domain/System/Telegram/`) parses `action:id:expires`
  callback data (24 h expiry, refuses politely after); the webhook controller routes `callback_query` updates
  (answers the button toast AND says the outcome in the chat); `answerCallbackQuery()` added to the gateway,
  the HTTP client and the fake. Four commands: `/approvals` (lists pending quote approvals with the asker and
  reason), `/approve <id> [no <reason>]` (runs `DecideQuoteApproval` — same rules as the screen, incl. nobody
  approves their own request), `/assign <lead> <person>` (`AssignLead`; ambiguous names are answered with the
  options), `/note <lead> <text>` (an activity note marked "(via Telegram)"). Buttons: new-lead alert got
  *Assign to me* (callback) beside WhatsApp/Open (no Call button — Telegram buttons cannot open tel: links);
  payment-proof alert got *Verify* / *Not matched* callbacks. Sensitive actions (void, refund, roles) are never
  offered. Tests: `tests/Feature/TelegramActionCommandsTest.php` (10) — gate green at 530 passed / 3 579
  assertions, Larastan level 6 clean, Pint clean 509 files.
- **Next per docs/15 + docs/16 §10 (Wave 1):** P5 S5 (health completion — anything `/health` still cannot see)
  → S6 (change-requests board) → S4 digests are the automation agent's lane (docs/16 §1); then Wave 2: P3's
  three report screens (Receivables, Sales Funnel, Revenue & Collections + CSV export kit) → SYS-007 audit
  viewer → CNV-004. The P5 exit test is still the owner completing a full day of approvals from Telegram.
- The owner answered the three standing questions (17 Oct, D-116 recorded in docs/07 and docs/16 §11):
  WhatsApp live connection coming **soon** (steps to remind: Settings → WhatsApp → credentials → Test
  connection → sync templates → map *quotation ready*); the team split is **agreed**; the theme path is
  **v1's own design extracted as tokens** — V2-1 is un-gated.

## Resume here (project memory, saved 17 Oct 2026 PKT, after the owner answered the three standing questions)

- **The owner's order for the frontend, unchanged:** the original v1 site is live on woodex.com.pk and stays live;
  **v2 is not started** — its admin master plan (stack, theme builder, page builder, SEO) is the next document,
  and its decisions are recorded below and in docs/07 (D-105…D-110).
- **Quotation is finished** (owner's three steps, 17 Oct): send on WhatsApp, then estimator → draft quote, then
  follow-up automation — all done and pushed (`926ec75`, `f353412`, `7062a5a`). The v2 admin master plan is
  written too (`9e4e829`, docs/12–14).
- **A website estimate starts a real quotation** (QUO-017, step 2): `CreateQuoteFromEstimate` reads the service,
  size and finish the estimator captured, prices the line from the **rate book** rather than the range the website
  showed, and writes both numbers on the quotation's internal notes so whoever opens it can explain the difference.
  The enquiry page offers it in one click and opens the new draft. **It stays a draft** — three form fields are not
  a scope.
- **A quotation now reaches the client on WhatsApp as a signed link, not a PDF** (QUO-008, D-111):
  `SendQuoteByWhatsApp` resolves or opens the conversation, sends the link inside Meta's 24-hour window and an
  approved template outside it, and writes `whatsapp_sent` / `whatsapp_failed` on the quote's timeline. A refusal
  from Meta never loses the quotation: the snapshot and link exist, and the team is told why the message did not
  go. The send dialog offers WhatsApp only when the client has a number and the API is connected.
- **A client's "ok" raises its hand instead of accepting the price** (D-112): `SuggestQuoteAcceptance` reads short
  replies — English and the Roman Urdu clients write ("theek hai", "ji haan", "👍") — ignores questions and change
  requests, remembers the message (`acceptance_hint_at`), notifies the team, and shows a banner on the quotation
  with **Accept quotation** / **Not a yes**. `AcceptQuote` takes an optional user, so the timeline says who
  confirmed it rather than pretending the client pressed the button.
- **Nobody is chased twice, and nobody is forgotten** (step 3): `ChaseQuotations` runs daily at 09:00 and warns the
  studio once when a quotation is about to lapse, lapses it when it does, and chases unanswered ones every
  `quote_follow_up_days` (3) to a **maximum of three chases**. It never messages the client — it tells the studio
  to call. `woodex:quote-follow-ups`, scheduled `dailyAt('09:00')->withoutOverlapping(30)`; off by default behind
  `sales.quote_follow_ups_enabled` (`7062a5a`).
- **A second agent is working on automation** (owner, 17 Oct): their deliverables are a P1–P3 audit, a master
  agent architecture (Telegram control, approval rules, specialist agents, funnel) and a 75-package completion
  backlog. **None of it is in this repo yet** (checked 17 Oct). The team protocol, lane split and the review
  checklist for their work are in `docs/16-MASTER-PENDING-LIST.md` §1: their lane is `app/Domain/Automation/`
  + bot + alert rules + their docs; this workspace keeps Telegram commands (the `TelegramCommandBus` is the
  interface they extend), inbox, reports and the whole frontend v2. Every claim of "built" gets checked against
  this codebase; decisions go to docs/07; their code arrives with the full gate green.
- **The master pending list P1→P7 is written** (`docs/16-MASTER-PENDING-LIST.md`, `c80a391`), code-verified:
  P3's three report screens are genuinely placeholders (Sales Funnel, Revenue & Collections, Receivables —
  RPT-002/005, the real P3 gap); the audit-log viewer (SYS-007) does not exist despite its permission; backups
  run but have no off-site destination yet; booking is paused pending real slots. WhatsApp live connection is
  **no-go for now** per the owner — nothing code-side blocks on it. Run order for both agents in four waves is
  in §10; standing questions for the owner in §11.
- **The three standing questions are answered (17 Oct):** WhatsApp live connection is **coming soon** (owner
  will paste the Meta credentials — steps in docs/16 §11.1); the **team split is agreed** (automation agent:
  sequences/bot/alert-rules + their 3 documents; this workspace: Telegram commands, reports, audit viewer,
  inbox, frontend v2); the **theme path is v1's own design extracted as tokens (D-116)** — V2-1 is un-gated.
- **Next for this workspace, per the agreed run (docs/16 §10, Wave 1):** P5 S3 (approvals from Telegram:
  callback buttons, /approvals /approve /assign /note) → S5 (health completion) → S6 (change-requests board);
  then P3's three report screens. The automation agent's work is checked against docs/16 §1 when it lands.
- **The owner redirected the order (17 Oct): P5 → P6 next** ("start next p5 to p6 create complate list
  to start impliment"). The P4 remainder (canned replies, sequences, broadcasting, bot) stays on docs/14 and
  resumes where the owner directs. The complete, ordered P5→P6 list is `docs/15-P5-P6-IMPLEMENTATION-LIST.md`
  (`c29d5bb`): P5 in eight steps S1–S8 (framework → read commands → action commands → digests → health →
  change-requests board → alert rules → hardening), P6 as V2-1…V2-6 (docs/12) plus the PRD rows docs/12 leaves
  open (performance WEB-011, accessibility, GA4 cards RPT-007). **V2-1 is gated on the owner's theme-path
  decision** (docs/12 §6).
- **The Telegram bot is a command centre now** (P5 S1 + S2, TGM-004): a `TelegramCommandBus`
  (`app/Domain/System/Telegram/`) with one class per command behind the linked user's own permissions —
  unlinked chats and missing roles get one honest line, a throwing command is reported and answered, never a
  500 (Meta retries those forever), and a chat is limited to 30 commands/minute. `/help` is generated from the
  registry so it cannot go stale. **Commands live:** `/today` (visits, SLA-overdue leads, own tasks; payments
  and approvals only for roles who can act), `/leads [new|mine|unassigned]`, `/lead <ref|phone>` (phone in any
  format, E.164 stored, displayed readably), `/find <text>`, `/quotes [pending|viewed]`, `/quote <no>`, `/due`
  (overdue + due this week with balances), `/visits [today|tomorrow]`, `/report [day|week|month]`, `/health`
  (same collectors as the Health screen), `/mute <hours|off>` (the same `muted_until` the alerts respect).
  **P5 remaining (docs/15):** S3 action commands + inline buttons (approvals, assign, note; callback-query
  routing), S4 digests, S5 health completion (DB size, last backup, scheduled-task last runs), S6 change-requests
  board, S7 alert rules, S8 hardening.
- **Verified for S1+S2:** Pest **520 passed / 3 530 assertions** (15 new in `TelegramCommandCentreTest`;
  the old linking/idea tests untouched and green) · Larastan level 6 clean · Pint clean on 503 files. The
  webhook is exercised the way Meta sends it: POST + secret, replies asserted through the fake gateway.
- **The studio can connect WhatsApp itself now** (WAP-001, D-115): Settings → WhatsApp takes the phone-number
  id, WABA id, token, app secret and verify token, **encrypts the three secrets with the app key**
  (`App\Settings\Casts\EncryptedStringCast`, never in a Livewire property — the browser only ever sees a masked
  hint like `EAAG••••••••abcd`), and keeps the server .env as a per-field fallback (`App\Integrations\WhatsApp\WhatsAppCredentials`
  decides, so the gateway, the webhook signature check and the handshake all read one source). **Test connection**
  asks Meta what it knows about the number (verified name, display number, quality rating) and, given a number,
  sends one real message — because valid credentials prove nothing about delivery. The Integrations card no longer
  hardcodes "not configured".
- **WhatsApp templates are a screen, not a deployment** (WAP-005): Automation → WhatsApp Templates syncs what the
  business account holds (paging followed, `rejected_reason` shown, a template Meta dropped is marked *Removed
  from Meta* rather than deleted), submits new ones with one example per `{{n}}` (ordinary mistakes — bad name,
  missing example, out-of-order variables — are caught here instead of at Meta), and maps each moment (quotation
  ready, enquiry, visit reminder, invoice, payment reminder, follow-up) to one **approved** template. `SendQuoteByWhatsApp`
  now sends whatever is mapped to *quotation ready* — in the template's own language — falling back to
  `services.whatsapp.quotation_template` (finally added to config, default `quotation`). No template name lives in
  code.
- **Verified:** Pest **505 passed / 3 406 assertions** (25 new: `WhatsAppConnectionTest` 13, `WhatsAppTemplateManagerTest`
  12 — including "the payload in the settings table never contains the plain token" and "a template Meta paused is
  not sent") · Larastan level 6 clean · Pint clean on 489 files · preview checked: `/`, `/estimator/` 200;
  `/admin/settings?tab=whatsapp`, `/admin/integrations`, `/admin/integrations/whatsapp-templates` render the new
  screens. **Waiting on the owner:** live credentials in Settings → WhatsApp, then Test connection, sync, and
  mapping a template to *quotation ready*.
- **Three planning documents are written** (`9e4e829` + `434bb69`): `docs/12-FRONTEND-V2-MASTER-PLAN.md` (the
  v2 stack, the six modules, the theme-import pipeline, the Sveltia question, phases V2-1…V2-6),
  `docs/13-V1-TO-V2-CONVERSION-PLAN.md` (how the 142 live files are retired group by group, home last, with the
  render-comparison gate each group must pass), and `docs/14-PENDING-TASKS.md` (every pending P4, P5 and v2 task
  with its real status, checked against the code). The recommended order in docs/14 §1 was accepted by the owner
  ("as you recommend"): WAP-001 + WAP-005 first — **done below** — then canned replies (CNV-004), then the
  follow-up sequence engine (AUT-001/002/005/006).
- **A latent bug this exposed and fixed:** a conversation opened by an *outbound* message had no unread counter in
  memory, so the first message the studio sent — exactly what sending a quotation does — wrote NULL into a NOT NULL
  column. Inbound only ever added to the counter, so it had never surfaced. Fixed in `Conversation::touchLastMessage`
  and in both places a conversation is created.
- **Merged another session's branch** (PR #2, owner setup, two-factor policy, Telegram diagnostics) by rebasing;
  the suite was re-run after the merge: **466 passed**, Larastan level 6 clean, Pint clean on 469 files.
- **A quotation nobody answered is chased, not forgotten** (AUT, step 3): `ChaseQuotations`, run daily at 09:00 by
  the cron the studio already has, tells the team to call after three silent days, warns once before the price
  lapses, and lets a stale quotation lapse on its own. **The system never writes to a client by itself** — it tells
  the studio, and the studio calls. Each nudge is remembered on the quotation: chased once per period, three times
  at most. Both rhythms are Settings (and chasing can be switched off).
- **Verification:** Pest 480 passed (3 324 assertions) · Larastan level 6 clean · Pint clean on 475 files ·
  preview checked (site, estimator, admin) before pushing · 34 new tests across the three quotation steps.

## Current handoff: document-library/design vertical slice + complete estimation/Accounts PRD — 17 Oct 2026 PKT

- **Plan first:** `docs/19-DOCUMENTS-ESTIMATION-IMPLEMENTATION-PRD.md` completes the Interior/Furniture master plan before code, builds on the approved balanced-ledger scope in docs18, analyzes the private WF-10153 Furniture reference, distinguishes six template/master layers, maps the owner's frontend service families, links GitHub research with license cautions, and stages photo rows, advanced estimates, manual invoices and Accounts. No source PDF/PII/prices/images were added to public code or imported as transactions.
- **Implemented first local slice:** additive `description_templates`/`document_designs` migration,12 safe editable starter descriptions/two brand profiles. Sales → Description Library supports brand/section search, CRUD/archive, copy to a new unpriced quote line and New Rate Book Item. Existing rates/quantities/text do not follow template updates. Active/brand/state/permission checks and500-character catalog compatibility are enforced.
- **Document Design:** Settings-managed shared brand name/accent/header/footer/local JPEG/PNG logo (96KB,600×240px). New/duplicated quotes copy immutable profile values/logo bytes/hash into metadata; existing drafts adopt only through explicit **Apply current design**, using `QuoteEditor`/parent locking and approval fingerprint binding. Project shares Interior design. Quote-derived invoices inherit frozen sent-quote branding. Old snapshots retain legacy layouts; old pending approvals may require withdrawal/re-request, not silent upgrades.
- **In-window previews:** quote/header/version/invoice-card/invoice-list PDF controls use an authenticated same-window overlay with close/download/focus controls. Sent quote default PDF now serves frozen bytes. Missing issued invoice PDF fails409 for reviewed recovery rather than live regeneration. Existing advance-invoice creation/freeze flow verified in tests and isolated browser QA; no invoice/payment/acceptance record was created in the owner's preview by QA.
- **Evidence:** full **744passed /4,500assertions /1existing skip**,259.11s (`/tmp/documents-full-tests.log`),30new tests/78assertions. Larastan6 no errors; Pint22 PHP files PASS; Vite PASS (existing chart-size warning). Dedicated browser checks pass description creation/copy, footer/logo persistence, brand switch, quote/invoice inline PDF, no new tabs/JS errors,1440/390/360px. QA iframe checks both3pc-blocked/no-cookies PASS. Docker shell/XML structure checked, **native engine/Compose/MySQL execution NOT RUN**.
- **Broad browser gate remains OPEN:** old `browser-check.mjs` homepage `#lf-name` selectors fail on both the unchanged8080 baseline and QA homepage. Its admin/layout/blur-save/approval/send/client PDF/acceptance checks pass on the isolated copy. Do not say the complete browser check passed; public website behavior was not rewritten for this task.
- **Data-safe QA/refresh:** used a separate `/tmp/woodex-documents-qa/app` vendor/storage/database/cache copy; `SANDBOX_APP_DIR` allows isolated sync and preview cookie-memory file can be overridden. Before8080 migration stopped server, backed up SQLite privately, compared all66original table-row hashes and private env: unchanged. Added only migration bookkeeping +12description starters +2designs. No reset/reseed/reimport/backup restoration on active data. Existing January16new contacts/leads and review holds preserved.
- **Runtime request:** no native PHP/MySQL/Docker engines installed; Debian/DockerHub downloads unavailable under npm/PyPI-only sandbox networking. `tools/docker/` provides a prepared, guarded PHP8.4+MySQL8.4 QA harness with disposable credentials, read-only source, isolated volumes and no exposed database port. It is **not built or run**, not a change to Hostinger runtime and not evidence of concurrency acceptance.
- **Next:** furniture short-name/specification/photo columns + immutable media and PDF visual review; then advanced BOQ/estimate definitions and shared frontend pricing; chart/periods/journal foundations, manual invoice builder/receivables integration and payables/expenses/recurrence per docs18/19. Native MySQL races/related writers/outbox recovery/Accounts UAT/actual2025finance files remain gates. These features are **not implemented** by the new design/library screens.
- **Final8080 check:** authenticated existing Sales/imported lead and both new screens200; quote-modal PDF200, no new tab, zero JS errors,1440/390/360px; fresh iframe3pc-blocked/no-cookies PASS. Current process `woodex-document-builder-preview-fcc209b4`,PID25377,port8080. QA8090 stopped. Private proof raster confirms a local logo/custom footer fit without header collision; no product-photo or multipage certification. Logs `/tmp/documents-preview-final.log` and `/tmp/documents-preview-iframe.log`.
- **Publication:** local8080 preview only. Closed session cannot publish; start a new coding session for GitHub/Hostinger release. Preserve optional owner2FA, consent/STOP, manual milestone draft-only behavior and all existing private data.

## Previous handoff: January 2025 leads imported; core approval/edit locking; Accounts scope confirmed — 17 Oct 2026 PKT

- **Owner answers recorded before changes:** prioritize “2025 data update in lead sale”; **new contacts only**; **basic ERP with balanced ledger**; 2025 account history exists. Only the January lead CSV is attached so far. Actual invoice/receipt/expense/payable/bank/balance files still need upload; never fabricate financial history from lead notes. Complete scope, current/planned matrix and staged Accounts build: `docs/18-BASIC-SALES-ACCOUNTS-ERP-PLAN.md`.
- **Applied to preview, not production:** supplied CSV had five preamble rows then 26 lead rows. Created **16 new contacts +16 historical leads**, skipped **two duplicate-phone rows**, held **eight rows** (four invalid/missing phones, four missing/invalid dates). Sanitized row review: `docs/2025-01-LEAD-IMPORT-REVIEW.csv`. Raw CSV/private data/reports/SQLite backups stay outside Git. Historical statuses/quotation/assignee/meeting fields retained in private lead enquiry/provenance; no invented won deals, assignments, appointments, quotes, invoices or payments.
- New `LeadCsvImporter` and `woodex:import-lead-csv` are dry-run by default; apply needs an explicit flag and a **fresh active owner**. New-only matching includes primary/alternate/WhatsApp numbers and archives. Stable batch/source IDs + checksums use `legacy_refs`; changed prior rows require review. Immediate second apply created **zero** additional records and recognized 16 imports. Historical capture goes through `CaptureLead` with explicit import-only historical date, no consent, no event/alert/automatic assignment/SLA clocks. Created/occurred times are **UTC-midnight day anchors**, not audited actual event times; source date/day precision retained. Global app timezone/storage conventions remain a finance-history review gate.
- Backed up the current DB while preview stopped, then applied the import. **Every pre-existing row across 66 checked tables was preserved**; expected lead numbering advanced. Added only contacts16/leads16/timeline16/legacy_refs16/audit32; no jobs, appointments, invoice/payment or other records were added by the import. Never restore an earlier backup over the owner's ongoing preview edits.
- **Core B04 coordinated locking implemented:** new `QuoteWriteLock`, parent-first editor/recalculator and fresh child rows; approval request/decision, reopen/revise, delete, view and public-change actions lock/recheck current state. Actors reload for privileged actions. Decisions compare original requested v2 content/revision/recipient/figures binding; changed proposals and stale decisions fail closed. Changed-revision approval asks allocate that revision before the request. Older v1 approvals require withdrawal/re-request/review, not silent upgrade. Public changes, like acceptance, require displayed revision; old forms must reload. Stale views cannot undo acceptance/lose counts.
- **Not full B04/MySQL acceptance:** related contact/company/bank/config/milestone writers, deadlock/retry and multi-worker tests remain. Native PHP/PDO MySQL, MySQL/MariaDB server and Docker are absent here; **real concurrency verification NOT RUN**, not replaced by SQLite claims. Orphan PDF and durable financial outbox recovery still pending. No important Telegram writes/automatic milestone issuing enabled; owner2FA policy unchanged.
- **Evidence:** 58 new regressions /220 assertions; full **714 passed /4,422 assertions /1 existing sandbox skip**, 232.30s (`/tmp/2025-approval-full-tests.log`). Larastan6 no errors; Pint `--test`19 PHP paths PASS; Vite build passes (existing chart-size warning). Final authenticated Leads/list/imported lead detail/Quotes/Invoices/Payments all200; no page JS errors. App Connections200, three scripts/styles200, 1440/390/360px no overflow. Iframe checks **3pc-blocked + no-cookies PASS** (`/tmp/2025-approval-iframe.log`). Broad homepage/browser acceptance not recertified.
- **Preview running on8080:** current source/assets synced, config/views refreshed without reset/reseed/env edit; no new schema migration in this slice. No browser financial/provider writes and no backup restoration during QA. Data is in the local runtime, not the production database.
- **Next implementation:** chart of accounts, periods, balanced immutable journal posting/reversal, source idempotency and account permissions; then wire existing invoices/verified receipts, supplier payables, expenses, outgoing payments, recurrence drafts and reconciled reports. These modules are **planned, not implemented by this delivery**. Confirm recognition/cutover/tax/entity policy with Accounts before historical posting. User selected balanced ledger, not inventory/payroll or an ERPNext/Gauzy replacement. Actual MySQL test environment and financial files remain explicit gates.
- **Publication:** preview only. Closed/merged session cannot publish; new coding session required for release. Preserve all existing local work and private preview data; do not reset/clean or use production money/data as test fixtures.

## Previous handoff: port 8080 refreshed; B04 foundations started — 17 Oct 2026 PKT

- Owner requested updated App Connections on **8080**, restart, then next work. Completed two data-preserving preview refreshes; listener runs on `0.0.0.0:8080` with current code/rebuilt assets and B08 changes. First refresh applied pending WhatsApp recovery migration. Final `migrate --force` had nothing pending; B04 unique milestone index was already installed and verified. Backups remain outside Git; all **66 existing business/settings/session tables** were byte-for-byte row-equivalent across the final migration/cache refresh (migration bookkeeping excluded). No reset/reseed/backup restore over ongoing preview work; env/storage/vendor retained.
- **B04 first slice implemented**, not package completion. Report: `docs/17-QUOTATION-IDEMPOTENCY-START.md`. Send/accept reload/lock current quote and recheck status/validity/revision; send rechecks existing approval figures and fresh active actor/policy. Fresh relations used for snapshots. Repeated sends/acceptances are refused without duplicate business effects; acceptance HTTP form explicitly carries displayed revision so an old form cannot accept a later resend.
- Project/advance helpers serialize on quotation, require accepted state, and check existing/archived records inside the transaction. Advance and manual milestone invoices serialize quote → project → milestone; new `InvoiceNumbering` chooses max historical suffix including archives, not count+1. Nullable unique `invoices.project_milestone_id` migration refuses historical duplicates for Accounts review, never deletes them. Manual milestone invoices remain **draft-only** (D-88); advance setting unchanged.
- Frozen advance PDFs return unchanged on repeat freeze; missing/partial/hash-mismatched documents require reviewed repair. Failed PDF writes roll back the database effect. Quote mail and staff quote alerts queue after the **outermost** commit; real database-queue commit/rollback regressions added. No durable outbox claim; orphan files after a successful write followed by DB rollback remain a reviewed-recovery gate.
- **Evidence:** 34 new tests /134 assertions; complete suite **656 passed /4,202 assertions /1 existing sandbox skip**, 178.99s (`/tmp/b04-full-tests-final.log`); Larastan6 no errors; Pint15 PHP paths; Vite build passes (existing ApexCharts size warning). Authenticated App Connections200, zero page JS errors, 1440/390/360px no overflow. Cross-site iframe checks **both third-party-cookie-blocked and no-cookie scenarios pass**, including hub search/MCP setup/Google dialog. No provider connection/key creation/webhook registration or financial action was performed by UI checks. Broad homepage/browser acceptance was not recertified.
- **Next:** complete coordinated quote-editor/recalculate/approval request+decision/reopen+revise/view/change-request locking and original-proposal/recipient/content fingerprint checks; then MySQL multi-worker races, Accounts UAT and durable delivery recovery. Existing financial fingerprint is not complete recipient/content authorization. Do not mark B04 checked based on these SQLite regressions. B08 broader own/team authority and runtime/provider gates also remain.
- **Publication:** local preview only; nothing pushed/deployed to Hostinger. Closed session cannot publish; start a new coding session for release. Preserve optional-owner-only2FA/confirmed challenges and disabled important Telegram writes. Runtime process reference/QA details are in the session handoff; runtime data/private env must never be copied into Git.

## Previous handoff: B08 Telegram identity and consent safety — 17 Oct 2026 PKT

- Owner requested **start next**. Implemented the next bounded local safety slice: Telegram personal identity, active staff/link checks, header authentication migration, actor/link rate limits and STOP-preservation guards. Details: `docs/25-TELEGRAM-CONSENT-HARDENING.md`. B08 is **not fully accepted**: broader finance own/team permission review and real-runtime evidence remain. B04 quote/send/milestone idempotency was not started in that B08 batch; the current handoff above records its subsequent first slice.
- **Telegram identity:** new `HandleTelegramCommand` action accepts only human private messages with equal positive sender/chat IDs. Group/channel/sender-chat/bot/forwarded/via-bot identities and callbacks/edited messages are ignored; no group inherits a linked user's authority. Links/code use active fresh users and locked code rechecks; new 16-character codes, max 30 minutes, existing valid eight-character codes compatible. Cannot steal a chat already linked to another staff account. `/idea` and `/issue` remain available to active linked staff only; finance/approvals/arbitrary writes remain disabled.
- **Webhook migration:** new stable POST `/webhooks/telegram`, official secret header required. Both Integrations and existing console registration use the secret-free URL. Old secret path requires a matching header by default; explicit temporary `TELEGRAM_ALLOW_LEGACY_WEBHOOK=true` permits no header only on the correct old path, never a wrong header/new-path bypass. Default/example false; no private env edits or credential requests. Re-register after deployment using the existing saved values. Preserve pending updates and request only implemented message updates.
- **Limits:** authenticated global 300/min, sender 30/min, link commands 5/min; hashed sender keys and independent WhatsApp buckets. A new denial test caught Laravel sorting throttle ahead of the custom auth middleware; fixed explicit priority in `bootstrap/app.php` and proved invalid headers cannot exhaust a legitimate sender's bucket. Command business effect + event marker now commit together under event lock; trusted received replay retries after rollback. Reply errors do not repeat committed effects; safe generic logs. Full queued Telegram/reply-outbox recovery remains pending.
- **Personal alerts:** User routing blocks inactive staff and legacy group links; TelegramChannel reloads recipient/link before delivery, honoring unlink/disable/group/mute changes despite stale cached relations. Existing group rows retained, not deleted; re-link in a private chat for personal alerts. Separate group alert authorization is not implemented.
- **Consent:** returning `CaptureLead` cannot OR positive checkbox flags over existing opt-out. `MergeContacts` locks/reloads both contacts in ID order, propagates either side's suppression and keeps both consent flags false. `SaveContact` locks/reloads and refuses ordinary re-consent edits. Inbound matching locks contact; repeated STOP preserves suppression timestamp; START/returning messages do not erase STOP. Full conversation/media merge and legacy import suppression reconciliation remain separate backlog work.
- **Send boundary:** SendConversationMessage reloads current active staff permissions, conversation and contact suppression before provider calls; opted-out/missing contacts blocked, templates need affirmative WhatsApp consent. Internal notes remain allowed without a send. Existing inbox toast truthfully describes blocked sends. No provider HTTP call can recall an already in-flight message; outbox/cancellation, marketing/template-category policy and explicit evidence-backed resubscription remain pending.
- **Final validation:** complete fresh PHP suite **622 passed / 4068 assertions / 1 skipped**, 149.15s, `/tmp/a0-b08-full-tests-final.log`; **61 new regression cases**. Larastan level 6 no errors (`/tmp/a0-b08-phpstan-final.log`); Pint on 23 PHP paths. Existing owner-only optional 2FA/confirmed challenges, first-A0 finance/WhatsApp, Google/MCP and website cases remain green. One installer/database-driver test is sandbox-skipped. Providers faked; in-memory SQLite; no real provider/MySQL concurrency/production/UAT claim. No browser rerun or Blade/CSS/JS change; earlier homepage browser expectations remain open.
- **Release/next:** no schema changes in B08, but preceding WhatsApp recovery migration still required for the combined release. Existing user/preview database not reset, credentials unchanged, no remote operations/deployment. New coding session required to publish. Next B04 idempotency and remaining B01/MySQL/Accounts/permission gates, followed by durable events/outbox/approval/dashboard foundations. Telegram synchronous processing/reply-loss-on-crash remains explicitly documented; do not claim B07 complete for Telegram.

## Previous handoff: A0 finance and WhatsApp safety implementation — 17 Oct 2026 PKT

- **Owner authorized local implementation after receiving the audit:** start in the recommended order. The prior audit-only scope below is historical. This first bounded batch implements parts of B02/B03/B05/B06/B07/B08; it is not completion of all 75 packages. Closed session still cannot push, open a PR or deploy; continue publication/runtime acceptance in a new coding session.
- **Finance:** `AllocatePayment` reloads/locks the payment and locks invoice candidates in ID order; rejects cross-client, nonpayable/credit-note/non-PKR targets, project/account mismatches, inactive accounts and invalid/excess explicit amounts. PKR-only payment ledger remains explicit; no new multicurrency column. `RecordPayment` honors a strict explicit map (remainder is credit); an invoice preference instead follows existing D-85: own invoice → eligible oldest debts → credit. Selected project/account filters constrain eligible excess. No allocation/credit-adjustment UI or mutation of already-receipted allocations is exposed.
- **Receipt/claims:** verify/reject reload and lock current state; stale retries cannot replace a receipt or reverse a decided claim. Current active-user/finance-permission checks added at action boundaries. Receipt storage failure rolls back confirmation; Laravel 13 rollback callbacks remove generated files on an enclosing rollback. Client mail/team notifications queue after commit. Proof submission reloads payable invoice state. Staff/public proof money parsing uses integer `Money`, rejects scientific/overprecise/unbounded input. New-payment request idempotency keys, hard-crash file cleanup and durable delivery outbox remain pending.
- **WhatsApp:** signed intake persists every entry/change/message/status with its exact normalized payload before enqueue. `QueueWhatsAppEvent` / `ProcessWhatsAppEvent` process one trusted row under lock; domain write + outcome commit together. Exact-ID legacy failed replay and already-written message recovery supported; duplicate jobs do not duplicate effects. Five-minute enqueue lease; minutely `woodex:retry-whatsapp-webhooks` recovers received/failed events; backoff 1/2/4/8 minutes, max five processing attempts. Reviewed `--event=EVENT_ID` console redrive only; processed/ignored/untrusted/other-provider rows refused. Generic safe diagnostic errors/logs, no provider exception bodies. Early delivery statuses wait for outbound rows; late failures do not downgrade delivered/read. Delayed inbound uses provider time for the 24-hour window and preserves newer previews.
- **Schema/runtime handoff:** new `2026_10_17_000300_add_whatsapp_webhook_recovery.php` adds queue/retry timestamps + index. Migration tested in isolated SQLite; **not run on production or the existing preview/customer database**. Production must use the database queue and working existing minute cron; sync remains synchronous. Apply the migration before the changed webhook code is enabled; verify real drain/timeouts. No new cron/Redis/daemon, no credential edits or real provider calls. Existing Telegram/MCP/Google work preserved.
- **Final validation:** freshly reran the complete PHP suite: **561 passed / 3774 assertions / 1 skipped**, 148.07 seconds, `/tmp/a0-full-tests-final.log`. Includes **50 new desired-behavior cases** (30 finance integrity + 20 WhatsApp recovery). The skipped installer/database-driver test is sandbox-specific. Existing Node MCP adapter tests **3/3 passed**; Larastan level 6 no errors; Pint run on all 21 batch PHP paths. Isolated real Laravel database queue exercised for acknowledgment/worker and finance/webhook rollback/commit boundaries; providers faked. No browser suite rerun; two earlier homepage browser form expectations remain open. No MySQL concurrent verification proof or production/UAT claim.
- **Details and next work:** `docs/24-A0-SAFETY-IMPLEMENTATION.md` includes exact semantics, retry/redrive operations, schema/rollout and remaining gates. Checkboxes in doc 14 still mean full package acceptance. Next complete Telegram sender/group/active identity and consent review (B08), quote/milestone idempotency (B04), production-like MySQL/Accounts acceptance and actual runtime checks (B01–B07), then common events/outbox/approval/dashboard foundations. Historical batches falsely marked processed are not bulk reset: require reviewed reconciliation. Existing media downloader remains a separate recovery/validation/file-cleanup follow-up.
- Approval-gated important Telegram writes, D-88 manual milestone draft and optional-owner-only 2FA remain unchanged. No master agent/specialist dashboards/general sequences enabled. No commit/push/deployment in this batch.

## Previous handoff: OS audit + master automation plan — 17 Oct 2026 PKT

- Owner selected **audit/plan only**, approval-gated Telegram writes, compare Hostinger vs optional worker, and balanced shared controls before specialist agents. No new business feature/provider configuration/deployment in this task; preserve existing local Telegram/MCP/Google work.
- Deliverables: `docs/21-OPERATING-SYSTEM-AUDIT.md` (P1–P3 traceability, all integration/automation inventory, evidence/unknowns), `docs/22-MASTER-AGENT-AUTOMATION-PLAN.md` (master/tools/agents/full funnel/approvals/separate dashboards/hosting comparison), `docs/23-AUTOMATION-COMPLETION-BACKLOG.md` (**75** dependency-linked work packages), and `docs/audit-evidence/2026-10-17-results.md` + probe source snapshot. Documentation index updated.
- **Do not repeat historical “P0–P3 complete / P4 complete” as PRD acceptance.** P1 foundation substantial; P2 quote follow-up/expiry/options/import and P3 manual/variation/credit/aging/reports/financial import remain gaps. Project-workspace phase labels conflicted with original P4 Conversations. Website chat/public slot booking explicitly off; general sequences/AI/master runtime/agent dashboards not implemented.
- **New isolated observations: 7 probes, 22 assertions**, fake providers and seeded in-memory SQLite, not production and no preview DB restore. F01 cross-client allocation (reproduced through Livewire); F02 allocation resurrects void invoice; F03 stale verification replaces receipt identity (one allocation, no concurrent MySQL test); F04 explicit allocation map ignored; F05 targeted excess credit conflicts with D-85; F06 batched inbound loses second message and failed-event replay does not repair; F07 batched statuses mark unprocessed second message processed. Full details/reproduction in doc 12 and evidence files. These probes assert observed defects and are **not repair regression expectations**.
- Recommendations: new coding session starts **B01–B08** (runtime truth, finance integrity, webhook batching/recovery, sender/permission/consent boundaries), then common event/outbox/sequence/approval controls and dashboards; complete sales/report/import gaps in parallel. Reuse domain actions; no replacement CRM or unofficial WhatsApp business-number transport. Preserve D-88 manual milestone invoice draft and optional-owner-only 2FA choice.
- Public production check reached Woodex sign-in at `/admin/login` via `/admin`; no authenticated inspection. Deployed revision/migrations/cron/provider/business acceptance remain unknown. Already saved private Telegram replacements must be verified as loaded, not requested again. Real Google registration/consent remains pending.
- Prior **511 passed / 3504 assertions / 1 skipped** remains the earlier local baseline, not rerun for this docs-only audit. Separately reran existing PaymentsTest + ConversationsTest: **24 passed / 124 assertions**; the uncovered negative/batch cases still reproduced in the seven probes. Two known homepage browser form expectations still pending. New probe result must not be presented as a new 518-test full-suite run. Validated all 75 unique backlog IDs, dependency references/no cycles, local document links and Markdown table/fence structure.
- Reference decisions: PHP Telegram Bot core README says development on hold; retain existing gateway unless separately justified. WhatsApp on-prem scripts archived/deprecated; use existing official Cloud API. OpenWA README explicitly warns of unofficial/ban risk; wacrm is useful UX inspiration but Next.js/Supabase, not a Laravel module. Older Woodex feature guide describes more flows, but documentation is not migrated implementation/live proof. Official Meta pricing says per-message began July 2025 with October 2026 changes; use effective-date delivered-usage rates, not original stale examples.
- No remote push/PR/deployment in this closed session. This audit is not authorization to enable agents or publish changes. Next batch requires the owner's implementation/deployment approval in a new coding session.

## Google connections and staff sign-in follow-up — 17 Oct 2026 PKT

- Owner chose **Google first**, **both business connection and staff login**, and **not registered yet**. Added a
  reference-inspired Connect Google dialog, permission summary, collapsible setup checklist, non-secret callback
  URI, server configuration reload and an explicit disabled setup gate. No editable client-secret/auth-base-URI
  fields. Configured authorization navigates to Google's official page outside embedded frames and returns here.
- Business connection is owner/password-confirmed and identity-only (`openid email profile`): encrypted hidden
  access/refresh tokens, account/state/scopes/last-check display, explicit refresh/check and provider revocation.
  Failed checks require reconnect; failed revocation keeps a disabled pending record rather than pretending to
  disconnect. No Drive/Sheets/Gmail/Calendar/Meet APIs or background sync are enabled by basic Google consent.
- Staff sign-in is separate: explicit password-confirmed linking while signed in, immutable Google subject,
  verified email, no saved staff provider tokens, no auto-registration/email matching/role changes, active-user
  check and existing Fortify confirmed TOTP challenge (including owners). Personal unlink requires the password.
  Google sign-in is disabled by default; password login stays available. Mandatory staff enrolment is preserved.
- Session-bound one-use 10-minute state + PKCE S256; fixed HTTPS provider endpoints; callback intent/actor checks;
  generic redacted failures; metadata-only lifecycle audit; no-store/no-referrer OAuth responses. Separate named
  IP throttles avoid sharing the counter between nested limits. No new Composer packages or server daemon.
- New migration creates `google_connections` + `google_identities`. `.env.example` contains empty Google client
  placeholders only. No actual environment credentials were changed or requested. D-20/D-47 and schema docs
  updated; full registration, private environment and acceptance guide: `docs/GOOGLE-OAUTH-SETUP.md`.
- **Verification:** full Pest **511 passed / 3,504 assertions, 1 skipped**; Google-specific **33 / 186**; Node MCP
  **3/3**; changed PHP Pint + Larastan level 6 pass; production assets rebuilt (existing chart-size warning only).
  Google setup UI passes at 1440/390/360px, Escape, hidden unconfigured login, and personal setup guidance without
  making a Google request. Both iframe-cookie scenarios include the Google dialog and pass.
- General browser check rerun against an **isolated app/DB copy** so quote/PDF/client-acceptance checks cannot
  overwrite edits in the user's active preview. Admin/mobile assertions pass; the same two unrelated expectations
  for a missing inline v1-homepage form fail. Browser tooling now accepts BROWSER_DB_FILE/BROWSER_DB_COPY overrides.
- **Not live-connected or published.** An OAuth app is not registered, so real Google consent, refresh/revocation
  and staff federated sign-in remain deployment acceptance steps. Session is closed after PR #2; all follow-ups
  remain local. Start a new coding session to publish, apply Database update and configure Google privately.

## Current local follow-up — 17 Oct 2026 PKT

- **Integrations hub + read-only MCP foundation implemented locally.** Search/category filters, 12 explicitly
  Planned provider cards with requirements dialogs, existing service controls, and an MCP setup panel. The owner
  selected business connections, Woodex tools exposed to AI clients, and a local adapter alongside Hostinger PHP.
- Owner-only, password-confirmed key creation/revocation actions are transactional with metadata-only audits.
  SHA-256-only token storage, 1–90 day expiry, scopes, one-response plaintext display, current active-user checks,
  existing leads/quotes permissions and record visibility, pagination, production HTTPS and IP/key rate limits.
  No writes, sends, shell/filesystem access, provider OAuth or general-purpose URL proxy. D-47 explicitly records
  the narrow generated-first-party-key extension; provider secrets remain server-side and never appear in the hub.
- `tools/mcp-bridge` uses pinned official MCP v2 packages, runs locally over stdio and exposes status, lead summaries
  and quote summaries. See `docs/INTEGRATIONS-MCP-PLAN.md` and the adapter README for setup and roadmap. No Node
  daemon on Hostinger. Vendor-client installation is not verified; exact Hermes product remains unspecified.
- **Verification:** full Pest **478 passed / 3,318 assertions, 1 skipped**; MCP subset **18 / 75**; Node **3/3**
  including real stdio protocol calls; changed-PHP Pint and Larastan level 6 pass; production Vite assets rebuilt;
  runtime npm audit reports no vulnerabilities. Browser acceptance creates a demo key and makes all three actual
  MCP calls against Laravel, checks revocation, search/requirements/Telegram diagnostics and responsive layout.
  Both iframe-cookie modes also test hub search and MCP setup. No real keys are captured in screenshots/logs.
- General `browser-check.mjs` was rerun: admin navigation, chart/sidebar geometry, quote editing/sending/PDF/client
  acceptance and mobile checks pass; its two pre-existing public-homepage form expectations still fail because
  the restored v1 home has no inline enquiry form. This is not an all-browser-suite-green claim.
- Previous secret-free Telegram configuration/cache repair is retained. Live Telegram delivery and Hostinger
  deployment remain unverified; saved private credentials have not been changed or requested again.
- **LOCAL ONLY:** this session is closed following PR #2. No GitHub operations, push or deployment attempted for
  these follow-ups. Start a new coding session to publish; then apply Database update for `mcp_access_tokens`.

## Resume here (project memory, saved 17 Oct 2026 PKT, after the original site went live and its forms were wired)

- **woodex.com.pk now serves the original v1 site, file for file (owner's decision): 142 pages copied verbatim**
  into `platform/public/**` — same folders, same paths, same HTML, CSS, JavaScript and images. Nothing
  redesigned, nothing re-typed, nothing rebuilt. The CMS copy of the same 143 pages stays in the database as
  editable content, but it is **not** what the public sees now; how edits go live is the owner's call, later.
- **The two addresses the old JavaScript calls are answered by Woodex OS, so the pages needed no change at all**
  (`LegacySiteBridgeController`, routes in `routes/api.php`):
  - `GET /api/forms.php` → `{"ok":true,"turnstile":""}` (no captcha box is drawn; honeypot + rate limit instead).
  - `POST /api/forms.php` → the enquiry is validated and written through **CaptureLead**, so it lands on the same
    pipeline as every other lead, with its source set from the form that sent it (`whatsapp`, `estimator`,
    `booking`, otherwise website form) and every extra field kept in `meta.fields`. Replies `{"ok":true,"id"}`.
  - `action=wa_click` → `{"ok":true}`: a WhatsApp tap is a ping, not a lead.
  - `action=book_create` → **kept as a lead with source `booking`**, then answered `ok`, which shows the page's own
    "booking request received, our team will confirm on WhatsApp" panel. The slot is never promised.
  - `action=book_cfg` / `book_slots` → booking is **off** (`{"ok":true,"on":false,"types":[]}`), so `/book-a-visit/`
    shows its own pre-written "Online booking is paused" panel. Woodex OS has no diaries yet (PRD §5.6).
  - `GET|POST /api/chat.php` → `{"ok":true,"on":false}`: the widget stays out of the way until website chat (P4).
- **Rate limiting was split** so a page view cannot spend the enquiry budget: `throttle:public-site` (120/min)
  guards all three bridge routes, and only writing a lead spends the 5-per-minute / 50-per-day budget, which is
  now counted inside the controller (`legacy-leads-minute`, `legacy-leads-day`).
- **Verification:** Pest **431 passed**, 3 098 assertions (12 new in `LegacySiteBridgeTest`, which is the contract
  the pages depend on) · Larastan level 6 clean · Pint clean on 460 files · every endpoint checked with curl
  (enquiry → lead, honeypot → silent ok, invalid → the visitor's message, WhatsApp tap, booking off, chat off) ·
  preview checked on port 8000: `/`, `/kitchen-design/`, `/contact/` answer 200 and every asset the pages call
  for answers 200. No browser in this sandbox, so the form was verified by contract, not by clicking.
- **Next, in the owner's order:** the v1 → v2 conversion plan. Questions first, then the plan, then the work.

## Resume here (project memory, saved 17 Oct 2026 PKT, after the complete old website went into the CMS)
- **The complete frontend v1 is the website now: 143 pages, 0 redirects (owner's decision, D-102).** The old site's
  54 specialist URLs used to 301 to the nearest discipline; they are pages now, editable in Website -> Pages with
  the Woodex builder. `database/data/legacy-website.php` holds 143 pages and an empty redirect list.
- **How it gets to the server in one step:** `woodex:reset-website` (new command) empties the public website —
  pages, sections, photographs, the 301s and the import bookkeeping — and
  `2026_10_17_000200_reload_the_complete_website.php` empties and reloads `database/data/website-bundle` in the
  same Database update, so woodex.com.pk is never blank. It records the bundle's checksum, so a later update with
  the same bundle changes nothing and pages the studio has since edited survive (D-104).
- **The four content fixes are actions now** (`app/Domain/Website/Actions/`, D-103), because an import brings the
  old pages back without them: AddMissingCallToActions, LinkCityPagesToAreas, LinkEmptyServiceUrls (123 empty
  discipline links filled in — the old site listed Furniture with no link at all) and RepairImportedCopy (the
  privacy page no longer promises Supabase and Vercel).
- **Two bugs found doing it:** a reset did not clear the *image* bookkeeping, so every imported block pointed at a
  deleted photograph (ImportLegacyMedia now checks a ref before believing it); and `/furniture/` carried the
  wardrobe page's title and description — it has its own words now, not yet its own photographs.
- **The audit after the reload:** 143 pages · 0 broken links · 0 orphans · min 489 words, median 815 · every page
  has one h1, JSON-LD, alt text and dimensions, and a closing · 5 pages still carry no photograph
  (`/services/`, `/projects/`, `/book-a-visit/`, `/estimator/`, `/privacy/`) · sitemap 143 URLs.
- **Verification:** Pest **419 passed** (6 new: the four actions and the reset command, which asks before it
  deletes and keeps media and redirects when asked) · Larastan level 6 clean · Pint clean on 458 files · admin
  smoke 34 pages · preview checked (frontend + dashboard) before pushing.
- **docs/11 is the v2 plan** the owner asked for: SEO first (the 54 specialist pages have to stop reading like
  copies of the six disciplines), then the section library and page patterns, then the design-token theme builder.
  Item 1 is still the owner's one click: **woodex.com.pk -> /admin -> System -> Database update.**

## Resume here (project memory, saved 17 Oct 2026 PKT, after the 89-page launch audit)
- **All 89 pages were audited page by page** (docs/10): every page rendered through the app's HTTP kernel, 704
  internal links followed. **Sound:** 89/89 answer 200, **0 broken links**, one `<h1>` each, titles ≤ 65 chars,
  descriptions 120–165 chars with no duplicates, JSON-LD on every page, every image has alt + width/height,
  `last_reviewed` set, **no thin content** (min 489 words, median 929, max 1 738).
- **Three defects found and fixed in this commit:**
  1. **The site had no navigation.** `navigation_items` was empty, so the header and the footer were blank and
     27 of 89 pages (home, about, projects, insights, privacy, all 12 cities, all 8 Lahore areas) had no link
     pointing at them — only the sitemap. `Menus::defaults()` now supplies a header and a footer until the studio
     fills in Website → Menus, and `Menus::cityLinks()` puts **Where we work** (the 12 cities) in the footer.
     Orphans **27 → 0**; average internal links per page **2.2 → 22**.
  2. **40 pages ended on a full stop** — every cost guide had no form and no next step. `..._130000` gives every
     published page without one an enquiry-form block with a heading written for its kind of page. **40 → 0**.
  3. **The 8 Lahore area pages were unreachable.** `..._130100` ends each city page with **"Areas of X we work
     in"**, listing its areas (Lahore → all 8; the other cities have none yet, so nothing was invented).
- **`tools/website/audit.php`** (new): renders every published page, follows every internal link, prints a summary
  and exits non-zero on a broken link. Run it after every content batch: `php tools/website/audit.php`.
- **Still open (owner):** 5 pages carry no photograph (`/services/`, `/projects/`, `/book-a-visit/`,
  `/estimator/`, `/privacy/`) — the library holds 74 imported images with no names, so nothing was picked at
  random; and **woodex.com.pk → /admin → System → Database update** still needs one click to publish the 89
  pages and create the conversation tables.
- **Verification:** Pest **413 passed**, 2 878 assertions (2 new navigation tests) · Larastan level 6 clean ·
  Pint clean on 450 files · audit re-run clean · preview checked (frontend + dashboard) before committing.

## Resume here (project memory, saved 16 Oct 2026 PKT, after P4: conversations part 1 - the inbox)
- **P4 part 1 — conversations: the pipes and the inbox (this commit, CNV-001/002/005, WAP-001…004/006):**
  - **Admin -> Inbox** (`/admin/inbox`, `inbox.view` to read, `inbox.reply` to answer): conversations on the left,
    the thread on the right with delivery ticks, assign, close/reopen, and internal notes that never leave.
  - **WhatsApp Cloud API** behind `WhatsAppGateway` (`app/Integrations/WhatsApp/`): send text and templates, fetch
    inbound media. Credentials come from the server `.env` (`WHATSAPP_TOKEN`, `_PHONE_NUMBER_ID`, `_APP_SECRET`,
    `_VERIFY_TOKEN`, `_WABA_ID`) — never the settings table — and every call is written to the integration log.
  - **Webhook** `/webhooks/whatsapp`: Meta's `GET` handshake, then `X-Hub-Signature-256` on every `POST`; each
    event is stored once in `webhook_events`, so a replay costs one insert and changes nothing.
  - **A client's message does the work**: it is matched to a contact by number (a stranger becomes one), opens or
    reopens a conversation, and reopens Meta's 24-hour window. Outside the window the composer refuses free-form
    text: only an approved template may go out. `STOP` / `UNSUBSCRIBE` / بند کریں are heard at once.
  - A failed send is stored as a message with status *failed* and Meta's error beside it — never a silent lie.
- **Verification of this commit:** Pest **412 passed, 2 867 assertions** (15 new in `tests/Feature/ConversationsTest.php`:
  the handshake, a forged signature, an inbound message becoming a contact and a conversation, a second message
  joining the same thread, a replayed message, opt-out, a closed thread reopening, a reply inside the window, a
  refusal outside it, a failed send, an internal note, delivered/read receipts that never move backwards, media
  stored on our side, and the two permission checks) - Larastan level 6 clean - Pint clean - PHP lint clean -
  smoke **34 admin pages** - the website's 89 pages still answer 200.
- **Still to come in P4:** the website chat widget + public chat API (CNV-001/008), the template manager and
  broadcasts (WAP-005/007), the usage and cost meter (WAP-008), canned replies (CNV-004), the AI bot (BOT-001…010),
  sequences (AUT-001/002/005/006) and broadcasting with a polling fallback (CNV-006).

## Where we were (project memory, saved 16 Oct 2026 PKT, after P4 + the full website going live in the CMS)
- **Where we are:** P0-P3 are done and live; **P4 is complete**: the project workspace (part 1) and the client
  portal, handover and case-study draft (part 2). **woodex.com.pk runs this branch** — every push redeploys it.
- **The demo home page is gone; the whole website is the CMS (this commit, D-93…D-95):**
  - `HomeController` + `resources/views/site/home.blade.php` are **deleted**. The home page is the CMS page with
    the slug `/`, served by the `SiteRouter` like every other page: what the studio edits in Website -> Pages is
    what visitors get. No second template to keep in step.
  - **All 89 pages are published** (`database/migrations/2026_10_16_110000_publish_imported_website_pages.php`):
    the home page, `/insights/` and its 43 guides, `/projects/` and its 6 studies, 12 city pages and the 8 Lahore
    area pages. Pages without a single visible block stay drafts.
  - The home page's imported "Tell us about your space" closing was text without a form; it is now an **enquiry
    form block** (`..._110010_add_enquiry_form_to_home.php`), keeping the words the import brought.
  - **Two live bugs fixed:** `public/robots.txt` was a static `Disallow: /` — the whole website was being kept
    out of Google (it is now answered per domain: the admin says no, the website says yes and points at the
    sitemap); and `public/.htaccess` **stripped the trailing slash** from every URL, which fought the SiteRouter
    that puts it back — two redirects for every page.
  - New settings properties now carry defaults, so a pending Database update can never 500 the public site.
  - A signed-in member of staff no longer fills the page cache: whoever opened a page first used to decide
    whether everybody else was counted in analytics for a day (`PageCache`).
- **P4 part 2 — the client's side (this commit, PRJ-006…PRJ-008, D-90…D-92):**
  - **Client portal** `/p/{public_id}` (signed, 120 days): where the job stands, the diary with its photographs,
    the drawings we chose to share, invoices (each opens its own signed invoice page), and the receipts.
  - **No client password**: a client who has lost the link asks for six digits at `/portal` with the phone number
    or email they booked with; the code is emailed, works once and dies in 15 minutes.
  - **Handover**: the checklist starts from Settings → Sales, the team ticks it and adds lines, the client signs
    with their name on the portal — and the job closes itself (stage completed, progress 100%).
  - **Review request**: `woodex:review-requests` (daily 10:30 in the cron) emails the Google review link a few
    days after the signature, once the owner has pasted their link into Settings → Business.
  - **Case study**: with the client's consent, a finished job becomes a **draft** portfolio page in the CMS —
    hero, the story from the shared diary, a gallery copied into the website's media library, and the facts.
  - The project page gained a **Client portal** card (link, copy, WhatsApp, email the link, review status) and a
    **Case study** card, and every file now has a *Private / On portal* toggle.
- **Verification of this commit:** Pest **397 passed, 2 888 assertions** (11 new in `tests/Feature/ClientPortalTest.php`:
  what the client sees and does not, the document that only appears once shared, the cancelled project, the
  checklist, signing the handover, the six-digit code, the review email, the case-study draft, the portal link
  email and the two "nothing is sent" guards) · Larastan level 6 clean · Pint clean · PHP lint clean (425 files) ·
  smoke 33 admin pages · `/portal` answers 200.
- **P4 part 1 — the project workspace (commit 90c8781, PRJ-002…PRJ-005, D-87…D-89):**
  - **Admin → Projects** — every job with its stage, status, progress, next milestone, value, invoiced/collected
    and target date (late jobs first); filter by stage, status and PM.
  - **Admin → Projects → Board** — the same jobs as cards, one column per stage (planning → design → procurement
    → execution → finishing → handover → completed). Drag a card to move the work on; *Completed* closes the job.
    A move tells the client one line about the stage (email now, WhatsApp with the conversations phase) and is
    switchable in Settings → Sales.
  - **The project page** — the money (value / invoiced / collected / outstanding), the milestones (tick one to
    mark it reached → progress moves; *Raise invoice* creates a **draft** invoice for it), the **site diary**
    (text + photographs + progress, optionally tied to a milestone, shared with the client on purpose), the files
    (drawings, approvals) and the internal notes.
  - The quotation page's project card now links straight to the project.
- **Verification of this commit:** Pest **386 passed, 2 811 assertions** (9 new in `tests/Feature/ProjectsTest.php`:
  the project that acceptance opens, a board move and what the client is told, closing the job, an internal note
  staying internal until it is shared, photographs + progress + the timeline, the milestone invoice raised once as
  a draft, raising it from the screen, `projects.manage` vs `projects.view`, and the screens) · Larastan level 6
  clean · Pint clean · PHP lint clean (407 files) · smoke 33 admin pages incl. both new ones.
- **P3 — get paid (commit e982c91, INV-005/006, D-84…D-86):** money reaches the ledger two ways and both land in the
  same place.
  - **Record payment** (Admin → Sales → Payments): the studio saying the money is in the bank — verified at once,
    receipt `RC-2026-0001` written to storage, mailed to the client, the team notified on Telegram.
  - **"I have paid"** on the client's invoice page (`/i/{public_id}`, signed for 90 days, no login): the client's
    amount, date, method, bank reference and an optional screenshot → **Pending verification**, nothing allocated.
    Accounts presses **Confirm** (allocates + receipt + mail) or **Send back** with a reason (mail with the reason).
  - Money settles **its own invoice first, then the oldest open invoices of that client first**, and an overpayment
    stays as unallocated client credit (`AllocatePayment`, D-85).
  - The client invoice page shows the frozen invoice, the bank account to pay into, the PDF link and a WhatsApp
    share; it 404s for a draft or a paid/void invoice. Every invoice row in the quote builder now has a
    **Client link** and a **WhatsApp** link.
  - Only `payments.verify` (accounts) may confirm or send back; a payment can only be acted on while it waits.
- **Verification of this commit:** Pest **377 passed, 2 745 assertions** (9 new in `tests/Feature/PaymentsTest.php`:
  record + receipt + mail, spreading over two invoices with the remainder as credit, client proof, confirm, send
  back, the client page, the draft/paid guards, permissions and the Livewire screen) · Larastan level 6 clean ·
  Pint clean · PHP lint clean (392 files) · smoke 31 admin pages green including `/admin/payments`.
- **Next (your call):** the **conversations phase** (docs/03 P4: WhatsApp Cloud API, the unified inbox, the chat
  bot, sequences) — that is what puts the portal link and the updates on WhatsApp instead of email; or the
  remaining P2 items (QUO-012 reminders/expiry, QUO-019 quote options, v2.1 quote import); or **website batch 3**
  (publish the 12 cities + 8 Lahore areas + `/insights/` + `/projects/`, humanize home and the six services).
  Tell me which and I start it.
- **Website (unchanged, still open):** 12 pages live from the CMS, **76 drafts waiting for a human**
  (12 cities, 8 Lahore areas, 48 insights, 6 projects), 54 old service URLs 301'd, parity test green.
- **Open items P-01…P-16** (docs/07 section C): P-06 (rotate credentials) blocks go-live; P-12 (real project
  photographs) blocks the case studies; P-15 (GA4/GTM/Clarity IDs into Website → Settings) blocks tracking;
  P-16 (email + hours in Settings) should be done now.
- **Rules to follow:** docs/07 (D-01…D-92), docs/09 and platform/AGENTS.md. The plan and specs are in docs/00-09.
- **Code:** everything is on branch `arena/8be318a2-woodexinterior` and pushed. `master` is still d77016b; merge
  through a pull request when the owner approves.
- **Preview (AI sandbox only):** after a VM reset run `sh tools/sandbox/setup.sh` (~3-4 min). Then start
  `PORT=8080 node --stack-size=8000 tools/sandbox/server.mjs` from `/tmp/woodex-run`. Checks: `smoke.sh`,
  `browser-check.mjs`, `home-check.mjs`, `page-check.mjs`, `iframe-check.mjs`, `installer-check.mjs`
  (tools/sandbox/README.md). The preview's database holds the imported pages; `/tmp/ref/frontend-v1` is the
  read-only clone of the old site and `/tmp/ref` is never pushed to.
- **Preview data:** demo seed (26 leads, 5 users, 4 quotations) + the 89 imported pages (12 published).
  Owner login `owner@woodex.test` / `Woodex-Preview-2026`. The preview database lives in `/tmp` and is rebuilt
  from the seeders after a VM reset (the pages then come from the committed bundle via `woodex:import-website`).

### P4 part 2 delivered: the client portal, handover and case study (16 Oct 2026, D-90…D-92)
- **Portal** (`/p/{project:public_id}`, signed 120 days): `Portal\ProjectPortalController` + `site/project-portal.blade.php`
  — progress, the diary with its photographs, shared documents, invoices, receipts and the handover the client signs.
  Files are streamed per route; a photograph of a shared update is client-visible by definition, a drawing only
  when the team flips it to *On portal* in the admin.
- **Code instead of a password** (`/portal`): `RequestPortalAccess` / `OpenPortalWithCode`, `portal_access_codes`,
  a six-digit code by email (`PortalCodeMail`), one use, 15 minutes, five tries. Answer is the same either way.
- **Handover** (PRJ-007): `project_handover_items` + `sales.handover_checklist`, `StartHandover` and
  `AcceptHandover` (the client's typed name is the signature; the job closes itself), and `RequestClientReviews`
  run daily by `woodex:review-requests` with `business.google_review_url`.
- **Case study** (PRJ-008): `DraftCaseStudy` writes a **draft** `Page` (type project: hero, story, gallery, specs),
  copies the shared photographs into the website media library, and is idempotent — only with the consent tick on.
- `p` and `portal` are reserved in `SiteRouter` so the CMS 404 handler never answers for them.

### P4 part 1 delivered: the project workspace (16 Oct 2026, D-87…D-89)
- **Screens** (`App\Livewire\Admin\Projects`): `ProjectsIndex` (list, filters, money per job), `ProjectBoard`
  (Kanban by stage, drag a card → `moveProject` → `ChangeProjectStage`), `ProjectShow` (the workspace: stage and
  status, the money strip, milestones, the site diary, files, notes).
- **Actions** (`app/Domain/Projects/Actions/`): `ChangeProjectStage` (stage, closes the job at *Completed*, one
  line to the client, a note on the timeline, a bell + Telegram alert to the team), `ReachProjectMilestone`
  (reached_at + progress = the sum of the milestones reached), `RaiseMilestoneInvoice` (one **draft** invoice per
  milestone, numbered `{project code}-{n}`), `PostProjectUpdate` and `ShareProjectUpdate` (the site diary).
- **Model + schema:** `project_updates` (migration `2026_10_16_000800`) with a `photos` media collection;
  `Project` gained `HasAttachments` (files), `updates()`, `company()`, `invoicedMinor()` / `collectedMinor()` /
  `balanceMinor()`, `nextMilestone()` and `isOverdue()`; `project_update` added to the morph map.
- **Settings:** `sales.project_stage_notifies_client` (default on) — Settings → Sales.
- **Policy:** `ProjectPolicy` — `projects.view` to look, `projects.manage` to move, post, share and raise.

### P3 part 1 delivered: payments, proofs and receipts (16 Oct 2026, D-84…D-86)
- **Actions** (`app/Domain/Sales/Actions/`): `RecordPayment` (verified at once, receipt number `RC-{year}-{0001}`
  per year inside the same transaction, mail + Telegram), `AllocatePayment` (own invoice, then oldest due date
  first, remainder unallocated), `SubmitPaymentProof` (client claim, `Pending verification`, proof in the `files`
  media collection, throttled 10/min), `VerifyPayment` (confirm → allocate → receipt → mail), `RejectPayment`
  (reason 5–500 chars → mail). `Money::ofMinor()->format($lakhGrouping)` everywhere; paisa stored as integers.
- **Receipt PDF** (`ReceiptPdf` + `views/pdf/receipt.blade.php`): the studio's letterhead text, receipt number,
  client, the invoices the money settled and how much of each, the method/reference/date, and the amount in words.
- **Client invoice page** (`Portal\InvoicePortalController`, `views/site/invoice.blade.php`): signed `i/{public_id}`
  for 90 days — status banners, invoice/paid/balance, the frozen lines, the bank account to pay into, PDF +
  WhatsApp links, and the proof form. Draft and void invoices 404; a paid or already-reported invoice hides the form.
- **Admin → Sales → Payments** (`Livewire\Admin\Sales\PaymentsIndex`): status chips with counts, search, a
  "record payment" form (client typeahead, one or the oldest open invoices, method, reference, bank account, date,
  notes), and per row: **Proof** (the uploaded file), **Receipt** (PDF), **Confirm**, **Send back** (reason modal).
  A warning counts how many are waiting for accounts.
- **Wiring:** `PaymentPolicy` (view follows `invoices.view`; `payments.record` to record; `payments.verify` to
  confirm or send back, and only while pending) · `payments.invoice_id` migration `2026_10_16_000700` ·
  `Payment` gained `HasAttachments` + `implements HasMedia` · `Invoice::payments()` + `applyPayment()` ·
  `/i` reserved in `SiteRouter` (D-86) · "Client link" and WhatsApp per invoice in the quote builder.

## Website state (batch 2: the import, 16 Oct 2026)
- **Where the website stands:** the CMS (batch 1) and the import of the old site (batch 2) are built and pushed:
  - **Live:** the home page (`/`, still its own Blade page) plus 12 pages from the CMS: the six core services
    (`/interior-design/`, `/fit-out/`, `/renovation/`, `/architecture/`, `/3d-visualization/`, `/furniture/`),
    `/services/`, `/about/`, `/contact/`, `/privacy/`, `/estimator/`, `/book-a-visit/`.
  - **Draft in the admin:** 76 more pages (12 cities, 8 Lahore areas, 48 insights, 6 projects, `/insights/`,
    `/projects/`) - publish them per batch after a person has read them (Admin -> Website -> Pages).
  - **54 old service URLs 301** to the service that answers them, with a hit counter
    (Admin -> Website -> SEO & Redirects).
  - **Parity test green:** `tests/Feature/Website/LegacyUrlParityTest.php` requests all 143 URLs (3.7s).
- **How content reaches the server:** the old HTML lives on a laptop, so `php artisan woodex:export-website`
  writes `platform/database/data/website-bundle` (pages.json + 68 photographs, 9.5 MB, committed), and the
  migration `2026_10_16_000400_import_website_content` calls `woodex:import-website` - the owner's one-click
  "Database update" does the rest. Both importers are idempotent (`legacy_refs`, checksum per slug).
  To re-import from the old HTML: `php artisan woodex:import-legacy --path=/path/to/frontend-v1`
  (`--publish --only=/slug/,...` publishes; without `--publish` everything lands as a draft).
- **Owner to do:** after a deploy, sign in and press **System -> Database update** (it adds the new tables such as
  `project_updates`, `project_handover_items` and `portal_access_codes`, and all 89 pages); add the **cron line**
  shown by the installer (it now also runs the daily review request); paste the **Google review link** into
  Settings -> Business; set up 2FA.
- **Next (batch 3):** publish the 12 cities + 8 Lahore areas + `/insights/` + `/projects/` after reading them, and
    humanize home and the six services (docs/09 section 9). Then batch 4 (48 insights), 5 (projects + tracking +
    `llms.txt` + consent), 6 (estimator and book-a-visit as real tools).

## Hostinger deploy fix (16 Oct 2026, D-56…D-58)
- **What happened (15 Oct, deploy of 3155343):** hPanel's Git deploy copied the repository into the **main domain's**
  `public_html` (its default root directory). `woodex.com.pk` answered 403 (no index file at the repository root),
  the Composer step was skipped (no root `composer.json`), there was no CSS (`public/build` was gitignored) and the
  subdomain still showed Hostinger's default page. Hostinger's Git deploy copies files and runs only Composer.
- **Fix:**
  - Root `.htaccess` → everything to `platform/public`; nothing else is web-reachable.
  - Root `composer.json` → same `require` as `platform/composer.json`, `vendor-dir: platform/vendor` (works with or
    without Composer scripts); `tools/deploy/after-composer.php` clears stale caches.
  - `platform/public/build` committed (rebuild + commit with every CSS/JS change).
  - `.env` + `storage/` in `~/woodex-os/<site>/` when `platform/.env` is absent (`App\Support\Install\SiteState`).
  - Web installer `/install` (setup code from the data folder, two steps, then closed) and System → Database update
    after deploys (`EnsureDatabaseIsCurrent`, `woodex:update`). Health shows database, data folder and build checks.
  - `public/index.php` explains a wrong PHP version or missing packages instead of a blank 500.
  - Seeders only add missing rows (`firstOrCreate`): re-running never undoes the owner's rates/templates/stages.
  - Production forces https links when `APP_URL` is https (CDN-safe); `TRUSTED_PROXIES` for a CDN.
- **Verified:** Pest 256 passed, 1155 assertions (1 skipped in the sandbox only: MySQL needs sockets); Larastan clean; Pint clean; lint;
  smoke (22 pages), browser-check, iframe-check; `installer-check.mjs` on a simulated `~/domains/<site>/public_html`:
  fresh site → installer → wrong code refused → install → `/install` 404 → owner signs in → a new migration sends the
  owner to Database update → Apply → admin works.
- **Not verifiable here:** the real Hostinger Composer step (which command, PHP version, memory) and LiteSpeed's
  `.htaccess` handling. The first deploy log will tell; the preflight page and docs/06 §2.7/§10 cover the failures.

## Current phase: P2 Quotations, part 1 done → part 2 next

### P2 part 1 delivered: quote builder (16 Oct 2026)
- **Quotations list** (QUO-016) at Sales → Quotations: status chips with counts (incl. "Needs follow-up": sent or
  viewed, no reply for 2 days), search by number, title, client, company or phone; line, owner and sort filters;
  value, owner and age columns.
- **New quotation** (QUO-003/006): choose the client (search, or start from an enquiry or contact page), link the open
  enquiry, the line and a template card (16 templates + "Blank"). The number is given on creation: WI-10100+
  interior/project, WF-10050+ furniture. The draft starts with valid-until = today + 15 days and the template's
  terms (or the default execution/design terms), scope, notes and milestone plan.
- **Quote builder** (QUO-003/004/005/015/020) at `/admin/quotes/{id}`:
  - Sections (rename, note, reorder, delete) and lines: description, qty with 3 decimals, unit, rate, line discount
    as % or Rs, optional line, internal cost.
  - Every field saves on blur, and the server recalculates line totals, section subtotals, discount, other
    charges, tax, rounding, total and margin (`QuoteEditor` → `RecalculateQuote`, D-54).
  - Enter in qty/rate/discount adds a line below and focuses it. Lines and sections drag and drop (SortableJS) or
    use the up/down actions.
  - The rate book picker (search + category) copies the item into the quote.
  - Side panel: client, title, validity, quote discount (% or Rs), other charges (label + amount), tax %, payment
    milestones (Execution 50/40/10, Design 75/25 or Custom; amounts always add up to the total), scope of work
    (bullets), terms, client notes and internal notes.
  - A sticky totals bar warns about lines without a quantity or rate and about milestones that are not 100%.
  - Duplicate, Save as template (new or update) and delete draft (recycle bin). Read-only for viewers and for
    quotes that are no longer drafts.
- **Rate book** (QUO-001): list with search, category and hidden/visible filters. Managers (`quotes.catalog`, D-53)
  add, edit and hide items (rate, internal cost, finish, line).
- **Quote templates** (QUO-002): cards with type, line, plan, sections, lines and usage count; "View lines",
  "Use" and edit details (name, description, type, line, plan, scope, terms, notes, hide).
- **Around the CRM:**
  - The lead page has a Quotations card with a "New quotation" link. The contact page and company page list quotes.
  - Global search finds quotation numbers and titles; the recycle bin has a Quotations tab.
  - Quote events appear on the lead and contact timelines.
- **Visibility** (D-53): managers, admins and accounts see every quote. Sales and designers see their own quotes and
  those of their leads.
- **Found by the real-browser check:** Livewire 4's `wire:model.blur` never sends a request (D-55). The builder uses
  `wire:model.live.blur`, and `browser-check.mjs` now edits a quantity, checks the server total and puts it back.

### P2 part 1 verification (16 Oct 2026)
- Pest: 212 tests, 968 assertions, all passing. The 40 new tests include the 42-line Renovation template totalled
  to the paisa against an independent BigDecimal sum, plus discounts, other charges, tax, rounding, milestones,
  moves, locks, duplicate, templates, visibility and the Livewire builder.
- Pint clean · PHP lint clean (269 files) · Larastan level 6: no errors.
- `browser-check.mjs` passes, including the quote builder edit/restore and the phone width; `iframe-check.mjs`
  passes; the smoke test checks 21 admin pages.

### P2 part 2b delivered: project + advance invoice (16 Oct 2026, D-80…D-82)
Accepting a quotation now opens the job and asks for its first payment, in the same transaction as the acceptance:

- **Project (PRJ-001, `OpenProjectFromQuote`)** — code = the quotation number, so the client keeps the number they
  know; value, client, line, site city/area from the lead, PM = the quote owner, and the quotation's payment stages
  copied as project milestones. One project per quotation, however often acceptance is called.
- **Advance invoice (INV-001/004, `RaiseAdvanceInvoice`)** — the milestone with the `on_acceptance` trigger becomes
  the first invoice: 50% on an execution job, 75% on design, whatever the owner set on a custom plan. Number is the
  quotation number with a suffix (`WI-10123-1`), tax keeps the quotation's rate, the bank account is the one for the
  business line (Interior → WOODEX INTERIOR, Furniture → WOODEX FURNITURE), and it lands as **draft** or **issued**
  according to `sales.advance_invoice_mode` (issue by default: never sent without a person saying so). An issued
  invoice is frozen — snapshot + PDF stored with it.
- **Screens** — Sales → Invoices lists every invoice with status, amount, balance and the overdue total; the
  quotation page gained a "Project and invoices" card; the invoice PDF is served to the team from storage.
- **Tests:** 8 new (`tests/Feature/AdvanceInvoiceTest.php`) covering the project, the three plans, idempotency,
  draft mode, bank routing, the list + PDF permissions, the quote page card and tax.

### P2 part 2 plan (items 1–4 and 5 are done: part 2a + 2b, D-60…D-64, D-80…D-82)
1. **Approvals (QUO-007):** discount above the role limit (`discount_limits_bp`) or margin below `min_margin_bp` →
   pending approval; the owner or manager approves in the admin (and later on Telegram, TGM-002). Approvals menu.
2. **Send + versions (QUO-008/013):** an immutable `quote_versions` snapshot and PDF hash on each send; a copyable
   signed link plus email (WhatsApp in P4). Editing a sent quote creates revision R1, R2…; staff see the
   differences.
3. **PDF (QUO-014):** A4 Studio letterhead with dompdf: client block, scope, sections, totals, amount in words,
   milestones, terms, bank details and signatories, page numbers.
4. **Client page (QUO-009/010):** signed, expiring link with a Studio-branded view, PDF download, Accept (name +
   checkbox), Request changes and Ask on WhatsApp. View tracking with first-view and "hot" alerts.
5. **Accept automation (QUO-011): done.** Lead → Won, contact → client, project opened, advance invoice raised, the
   team alerted with the invoice number and amount — all in one transaction. Recording the payment against it and
   sending it to the client are P3.
6. **P2 part 3:** validity reminders and expiry (QUO-012), options A/B (QUO-019) and the v2.1 quotes/templates
   import.

### Preview sign-in fix (16 Oct 2026)
- **Test 1 (00:18 PKT):** the owner signed in as `admin@woodex.com.pk`, which does not exist in the demo data, and
  landed on the public holding page with no error. **Cause:** the preview proxy drops the `Cookie` request header.
  The browser keeps the cookies (beacon `cookieKept=1`), but every request reached Laravel without them and started
  a new session. Even the right password would have bounced back to the sign-in form.
- **Fixes:**
  - A failed sign-in returns to its form with the error (D-51).
  - The preview server keeps each browser's session (cookie memory, D-52).
  - The sign-in page has a demo-account box with a one-click sign-in (preview only).
- **Test 2 (00:43 PKT):** sign-in worked (the session was signed in), but a double click on "Sign in" sent a second
  POST. That request arrived already signed in, and Laravel's "already signed in" redirect goes to the route named
  `home`, which is the public website. Opening the sign-in page again went there too.
  **Fixed:** signed-in users on guest pages go to `admin.dashboard` (`redirectUsersTo`); the auth forms submit
  only once per page (D-51, tested).
- **Test 3 (00:47 PKT):** the owner was in the admin and browsed 10 screens without errors.
- **New check `tools/sandbox/iframe-check.mjs`** reproduces the Arena setup: a cross-site iframe, a proxy without
  Referer, third-party cookies blocked, and a run that drops every cookie. Before each fix it showed exactly what the
  owner saw. Now both runs pass:
  - a wrong password shows the error;
  - a double click sends one request and opens the dashboard;
  - the sign-in page leads back to the admin;
  - `wire:navigate` and a full reload stay signed in.
- The request log shows cookie names and a short browser tag (`ua=Windows/Chrome153`). The beacon reports
  `cookieKept`, `framed` and `storageAccess`.
- **Verification:**
  - Pest: 172 tests, 726 assertions, all passing.
  - Pint clean.
  - `browser-check.mjs` passes; `iframe-check.mjs` passes both runs; smoke test: 17 admin pages return 200.

### Preview fix (16 Oct 2026, after P1 part 2)
- **Holding page unstyled in the owner's browser (reported twice):** the preview emitted absolute asset URLs on a
  guessed preview host; they failed in the real browser while in-sandbox curl passed. All output URLs are now
  root-relative (preview server rewrite + `ASSET_URL=/`), so pages work under any origin (D-36). Confirmed from the
  owner's own browser: CSS and fonts load from the preview origin and JavaScript runs (preview beacon: Alpine started).
- **Lead form invisible without JS:** the whole form sat in `<template x-if>`; it is now plain HTML enhanced by
  Alpine, `x-cloak` added to site.css, submit disabled until Alpine starts (D-49).
- **Dashboard chart drawn twice and overflowing its card; y-axis 0,1,1,2,2,3,3:** init race fixed, charts re-fit to
  their box, whole-number ticks (D-48).
- **Admin content slid in on every page load / `wire:navigate`:** sidebar width is now a CSS variable set before
  first paint (D-48).
- Preview server: gzip/brotli (livewire.js 609 → 121 KB, ApexCharts 567 → 135 KB), request log with start / done /
  ABORTED lines and proxy headers.
- Sandbox tooling moved into the repo (`tools/sandbox/`, D-50) including a real headless-Chromium check.

### P1 part 2 delivered (this branch)
- **Fix: public holding page without styling** (giant logo): the sandbox preview server was rebuilt so asset URLs
  always use the public https origin (no mixed content), static files are served with correct MIME types, and inline
  SVGs (logo, icons) now carry width/height fallbacks so no page can explode while CSS loads (D-36).
- **Pipeline exit test** (CRM-004/005): dragging a card to *Site visit* without a booked visit opens the booking
  modal; cancelling puts the card back; booking moves it (also when dragged back from a later stage).
- **Visit booking everywhere** via one concern (`BooksSiteVisits`) with a **double-booking warning** (same team member,
  overlapping time → "Book anyway").
- **Visit calendar** (CRM-008): week columns and month grid with status colours, previous/next/today, day → week.
- **Staff reminders** (CRM-007/008): `woodex:reminders` every minute → task reminders at `remind_at` (30 min before
  due) and site-visit reminders 24 h and 2 h before, in-app + Telegram, each sent once (D-40).
- **Repeating tasks**: daily, working days (Mon–Sat), weekly, monthly; completing creates the next one (D-40).
- **Companies** (CRM-002): list (search by name/phone/email/NTN, type, sort), company page (details, people with
  link/unlink, enquiries, P2/P3 placeholders), edit, delete; companies in global search.
- **Assignment rules** (CRM-009) at Settings → Lead assignment: ordered rules by line, service, city/area and source;
  role or chosen people; round robin or fewest open leads; workload cap; "working hours only" (after-hours rule
  below it); fallback = least-loaded sales person. **Reassign with a reason** on the lead page (D-37).
- **SLA** (CRM-010, AUT-004): response target per source, **holidays** in Settings → Business, second escalation to
  the final roles (default owner) after the escalation delay (D-38).
- **Lead scoring rules in Settings** (CRM-011): Settings → Lead rules (budget, size, line, timeline, lead type,
  services, cities, per-source points, hot threshold) (D-39).
- **List tools** on Leads (GLB-003): sort, 25/50/100 per page, column chooser, saved views per person (with default
  view), CSV export (`leads.export`, formula-injection safe), bulk assign / move stage / delete (D-43).
- **Notification centre** (GLB-008): header bell with unread count, mark read, deep links, 30 s polling while visible,
  and a full Notifications page (D-45).
- **@mentions** in notes (GLB-005): `@Sara`, `@Sara Khan`, `@sara.khan` notify in-app + Telegram (D-44).
- **Attachments** (GLB-006) on leads and contacts: photos, PDF, DWG/DXF, Excel, Word up to 20 MB, private disk,
  authorised download route, WebP thumbnails for images, timeline entry (D-41).
- **Recycle bin** (GLB-010): delete lead / contact (with its enquiries) / company, restore together; owner-only
  **erase** of personal data for privacy requests (D-42).
- **Integrations hub** (SYS-005) + **integration log viewer** (GLB-018): status cards (Telegram, email, website forms,
  Turnstile, v2.1 database; WhatsApp/Meta/LinkedIn planned), test buttons, connect Telegram webhook (D-47).
- **Data import wizard** (SYS-006): connection check, choose parts, queued dry run, reports with warnings/duplicates
  downloads, real run only after a dry run in the last 24 h + typing IMPORT (D-46).

### Verification (16 Oct 2026, PHP 8.4.25 sandbox, real dependency versions)
- **Pest: 170 tests, 704 assertions, all passing** (133 from part 1 + 37 new).
- **Pint clean · PHP lint clean (246 files) · Larastan level 6: no errors · all Blade views compile.**
- Live preview: 17 admin pages + holding page return 200 as owner; CSS/JS URLs root-relative, `text/css` /
  `text/javascript`. **Real-browser check (headless Chromium) passes:** styles applied, Alpine/Livewire running, no
  failed requests or console errors, one chart inside its card (expanded and collapsed sidebar), no layout slide on
  load or `wire:navigate`, mobile drawer, lead form visible without JS and submitted with JS (reference returned).

### Next (after P1)
1. Activate CI (docs/ci/README.md; Larastan is blocking there), then commit the first `composer.lock`. Waiting for
   the owner: this GitHub connection cannot write `.github/workflows`.
2. Staging on Hostinger: subdomain, PHP 8.4, cron, `woodex:install`, Telegram webhook (docs/06); first import dry run
   from System → Data import against a copy of the v2.1 database (docs/08). Waiting for P-05.
3. P2 Quotations (docs/03 Part B): part 1 done, see above.
4. Deferred from P1 (small): CSV import of leads/contacts with mapping (CAP-009); full alert-rule builder (AUT-003,
   P5); client WhatsApp visit reminders (P4); saved views on Contacts/Companies (the concern is ready).

### P1 part 1 delivered (commit cfd0fd5)
- **Lead page** `admin/leads/{lead}` (CRM-003/006/016, GLB-004): one-tap call outcomes, WhatsApp/email/meeting/note
  logging, next action with presets (1 h, tomorrow, 3 days, next week), stage bar with lost-reason modal, owner
  reassignment, site visit booking, tasks, editable details, score badge and SLA status, full timeline.
- **First-response SLA** (CRM-010, D-31): first outbound attempt stops the timer; New → Contacted only when the client
  was reached; 3 unanswered calls in a row suggest WhatsApp; `woodex:sla-check` every minute flags breaches once and
  alerts owner + escalation roles (in-app + Telegram).
- **Lead scoring** (CRM-011) on capture: budget band, size, service, line, referral/returning, timeline; Hot ≥ 80.
- **Contacts** list + **Contact 360** (CRM-001/012/015): search by phone in any format, client/prospect filter, tags,
  duplicate detection (phone/email/name) and **merge** (moves leads, timeline, tasks, visits; fills blank fields),
  new enquiry for an existing client. Sales users see only their own contacts.
- **Tasks** (CRM-007): mine / whole team, overdue / today / upcoming / no date, complete and reopen, link to a lead
  by reference.
- **Site visits** (CRM-008): agenda (needs update / today / tomorrow / dates), map link, one-tap WhatsApp
  confirmation, confirm / reschedule / no-show / cancel, **outcome form with room measurements** → lead size, a
  "prepare quotation" task due in 48 h and a follow-up call.
- **My Day** (CRM-013): uncontacted leads first (with SLA countdown), overdue follow-ups, later today, today's visits,
  tasks due, unassigned leads for managers.
- **Global search** (GLB-002): Ctrl/⌘ K palette in the header over leads and contacts (name, phone in any format,
  email, reference), keyboard navigation, respects visibility.
- **Users & roles** (SYS-002): invite by email (set-password link), roles with descriptions, edit, deactivate
  (signs out, unlinks Telegram: TGM-007), resend invite; guards: never the last active owner, never yourself, only
  owners grant the owner role.
- **Settings** screen (SYS-001): business identity, contact, working hours/days, social links; sales rules (validity,
  invoice terms, discount limits in %, minimum margin, SLA minutes, lost reasons, budget bands, terms, signatories);
  notifications (Telegram on/off, quiet hours, digest time, escalation roles). Phones normalised to E.164.
- **v2.1 importer** (SYS-006 CRM part, D-32): `php artisan woodex:import-v21 {--dry-run} {--only=*} {--connection=legacy}`
  for users, clients, leads, notes, bookings; idempotent via `legacy_refs`; report + warnings/duplicates CSV.
  Runbook: `docs/08-V21-IMPORT-RUNBOOK.md`.
- Lead sources extended for organic/offline channels (D-33); manual lead form offers them.
- Admin JS: Preline per-plugin import (44 KB instead of ~390 KB, D-35). Navigation: My Day, Contacts, Tasks, Site
  Visits, Settings, Users & Roles enabled; lead links everywhere (list, board, dashboard, Telegram, notifications).
- Demo seeder (local only) adds calls, visits (today/tomorrow/overdue/completed with measurements) and tasks.

### Part 1 verification (15 Oct 2026)
- **Pest: 133 tests, 518 assertions, all passing** (60 from P0 + 73 new: lead page, contacts, tasks/visits/My Day,
  SLA, global search, users/settings, importer against a v2.1-shaped database, value conversions).
- **Pint: clean (210 files). PHP lint: clean.**
- **Larastan level 6: no errors** after enabling `parseModelCastsMethod` (D-34). It found two real bugs while P1 was
  built (a renamed method still called in one path; an untyped schema call).
- Live preview: all 16 admin pages render as owner (dashboard, My Day, leads, lead page, pipeline, contacts,
  contact page, tasks, site visits, settings × 3 tabs, users, health).

## P0 Foundation (done, commit ce819db)
- Laravel 13 + PHP 8.4, Livewire 4 class components, Fortify (login, reset, TOTP 2FA + enforcement), spatie
  permission/activitylog/medialibrary/settings/backup, dompdf, brick/math; Vite 8 + Tailwind 4.3 + Preline 5;
  TailAdmin-style shell with the full navigation (later screens shown disabled with their phase).
- Roles/permissions (PRD §7), settings groups, schema for system/CRM/sales/projects/billing (docs/04), support classes
  (Money, AmountInWords, Phone, Numbering, BusinessHours), CaptureLead + public lead API (throttle, honeypot,
  Turnstile, bridge key), Telegram alerts + account linking + `/idea` `/issue`, dashboard, leads list, pipeline board,
  system health; seeders incl. 16 real quote templates and the rate book.

### Assumptions
- WhatsApp/phone +92 322 4000768, email woodexinterior.pk@gmail.com, hours Mon–Sat 10:00–19:00 (P-01…P-03).
- Asia/Karachi stored as application time zone (no DST in Pakistan).
- Free Preline blocks only until a Pro licence is confirmed (P-04).

### Known issues / risks
- CI not active yet: the Arena GitHub connection cannot write `.github/workflows/` (docs/ci/README.md).
- Importer: v2.1 phone numbers that are not valid Pakistani/international numbers are kept in contact notes; the
  dry-run warnings list them. Budget text that names no amount stays in the lead details.
- Nohemi font commercial licence to be confirmed before the public website launch (P-09).
- Credentials exposed in the old public repository must be rotated before go-live (P-06).

## Security & Telegram repair — 2026-10-16

- Added manual TOTP setup-key fallback, time-sync/app guidance and exact six-digit confirmation validation.
- Telegram linking now checks token/secret configuration, opens the configured bot with a `/start` deep link,
  allows code regeneration, refreshes the loaded link state on render, and invalidates pending codes on unlink.
  `TELEGRAM_BOT_USERNAME` defaults to the owner's public bot, `WoodexInteriorBot`.
- Removed raw Telegram transport exception logging/reporting: the request URL can contain the bot token.
- Report/runbook: `docs/TELEGRAM-SECURITY-REPAIR.md`. Exposed bot token must be rotated in BotFather and the new
  token placed only in the private production environment; production webhook registration remains outstanding.
- Verification: 25 targeted Pest tests / 143 assertions, Pint, production build, targeted desktop/phone security
  browser check and both iframe modes passed. The general browser check still fails on unrelated public-homepage
  `/api/chat.php` 404s and missing expected enquiry form. No production/live bot verification: Telegram and the
  hosting domain are outside the sandbox's network allowlist. No deployment performed.

## Telegram runtime follow-up — 2026-10-17

- Owner reports previous patch deployed, replacement token saved, webhook registration succeeds; screenshots show
  no replies to plain `/start`. Production cause remains unverified (network allowlist prevents direct checks).
- Added admin-only, locked **Check live connection** diagnostics: server-side getMe/getWebhookInfo, expected bot
  and webhook comparison, pending count, redacted delivery error and local inbound timestamps. No secrets shown.
- Corrected misleading token-present "Connected" label to "Configured" and registration success wording.
- Fixed HTML escaping of command replies (`/help` placeholders and staff names); preserved pending updates on
  webhook re-registration. Neither fix is claimed as a verified cure for production plain `/start` silence.
- 32 targeted tests / 171 assertions, Pint, Vite build, targeted desktop/mobile diagnostics browser check and both
  iframe-cookie modes passed. Full general browser check remains subject to the public-homepage issues above.
- Production next step: deploy, use /admin/integrations → Check live connection, send a fresh /start and recheck.
  Runtime interpretation table added to docs/TELEGRAM-SECURITY-REPAIR.md. No production deployment performed here.

## Owner-only optional 2FA enrolment — 2026-10-17

- Owner explicitly selected optional 2FA for owner accounts only. D-20 updated: owners can open admin pages and
  integrate Telegram before authenticator setup; admin/accountant enforcement is unchanged when enabled.
- Centralized the effective requirement in User::requiresTwoFactorAuthentication(); middleware, cancellation/
  disable guard and page wording use the same rule. Explicit owner exemption covers old cached role lists and
  multi-role owners. No global ENFORCE_2FA change, no database secrets cleared automatically, no Fortify bypass.
- Confirmed owner 2FA still challenges at sign-in. Unconfirmed setup can be cancelled with the account password;
  linking Telegram does not turn 2FA on or make it mandatory. Installer wording reflects the role policy.
- Verification: 45 targeted tests / 238 assertions, Pint, Vite build, real browser navigation while enforcement
  is on with pending owner QR setup (dashboard/leads/integrations/security), password-protected cancellation,
  desktop/mobile layout and both iframe-cookie checks passed. General homepage check has the unrelated known
  failures documented above. Production not modified: deploying this branch is required for the live fix.

## Deployment preparation — 2026-10-17

- Owner requested deployment to woodex.com.pk but could not identify the Hostinger-connected branch. No Hostinger
  access or GitHub deployment workflow is available in this session; production deployment cannot be confirmed.
- Committed security/Telegram changes on arena/fe1287c9-woodexinterior and merged the latest previous application
  branch (8d3eee1) into it so the website enquiry-form bridge is preserved. No changes pushed to other branches.
- Post-integration verification: 57 targeted tests / 309 assertions passed, including the website bridge tests.
- Release branch is intended for hPanel → woodex.com.pk → Advanced → Git → select
  arena/fe1287c9-woodexinterior → Deploy. Preserve the existing target directory and private production settings.
  No new database migrations are introduced by the security repair. Confirm the deployed commit and owner
  access after hPanel finishes; then use /admin/integrations → Check live connection for Telegram.


## Telegram configuration-loading repair (local only) — 2026-10-17

- Owner confirms credentials were saved in the private .env, but Connect webhook still reports missing runtime
  credentials. Production cause is unverified: stale cached config, classic platform/.env precedence, parsing/
  permissions or server environment overrides can produce this result. No production files were inspected.
- Added read-only TelegramConfiguration diagnostics using the application's selected environmentFilePath():
  active relative path, readable/invalid state, credential presence on disk vs runtime, config-cache status,
  and a warning when a separate private env exists but the classic file was selected. Secret values and raw
  dotenv exceptions never leave the service. The diagnostics open automatically for an owner on missing setup.
- Added owner-only Reload server configuration: clears only Laravel config cache, checks failure, and performs a
  full redirect so the next request reboots services. Warns that all saved env changes take effect; never changes
  .env precedence, credentials, database settings or webhook registration. Other staff cannot invoke it.
- 71 targeted tests / 380 assertions, Pint, production build, desktop/mobile configuration browser check and both
  iframe modes passed. Existing general-homepage-check limitations remain outside this repair.
- This coding session is closed after PR #2 merged. These additional changes are LOCAL ONLY; no push, PR, remote
  GitHub call or production deployment attempted. A new coding session is required to publish this follow-up.

## P8.1 Accounts ERP — batch C: double-entry ledger core — 2026-10-17

- Batch C landed on bc82386: chart of accounts (starter PKR set, groups 1000–5000, ~28 leaves, business_line
  on revenue accounts), immutable journal entries with two-way linked reversals, integer-paisa amounts,
  monthly accounting periods (all open — closing is a deliberate recorded act), JE-{year}-{seq} numbering,
  unique(source_type, source_id) idempotency, OPEN-period posting rule, balanced-entry enforcement inside
  a single DB transaction. One legal entity; Interior/Furniture are business-line dimensions, not separate books.
- Domain: platform/app/Domain/Accounts (Enums/AccountType, Models/{Account, AccountingPeriod, JournalEntry,
  JournalLine}, Exceptions/JournalPostingFailed, Actions/{PostJournalEntry, ReverseJournalEntry}); migration
  2026_10_17_140000_create_accounts_tables; ChartOfAccountsSeeder (+ RolesAndPermissionsSeeder accounts.view/
  accounts.manage, NumberSequenceSeeder JE-, ProductionSeeder). Screens: /admin/accounts (chart tree with
  create/edit), /admin/accounts/journals (post, view, reverse with reason), /admin/accounts/periods (open/close).
  Owner+admin see accounts; only owner manages. 17 tests / 204 assertions incl. re-seed idempotency regression.
- Gate notes: full suite 85 files green (2 env-conditional skips) under the wasm rig, Pint 607 files PASS,
  Larastan 0 errors. Two real bugs caught and fixed by this gate: reversal reverses_entry_id was never persisted
  (PostJournalEntry context + ReverseJournalEntry now link both directions), and period seeding crashed on
  re-seed (SQLite date storage vs date-only equality — seeder now probes with whereDate).
- Rig note (sandbox only, never committed): wasm defers PendingCommand::__destruct, so RefreshDatabase's
  discarded migrate:fresh PendingCommand never runs and destructs fatally at teardown. Runtime TestCase sets
  mockConsoleOutput=false from construction; artisan() then returns real exit codes, which is what the suite
  asserts. Under wasm the suite must run file-by-file (phpstan needs --debug to avoid parallel workers).

## P8.2 Payments & cash — due payments, GL posting, Telegram /collect and /statement — 2026-10-17

- Sales now feeds the ledger (owner decision: full batch D). An issued invoice books Dr Trade
  receivables / Cr revenue by business line (4010/4020/4090) with sales tax to Taxes payable; a
  verified receipt books Dr the method's cash/bank account / Cr receivables for the allocated part
  and Customer advances for any unallocated credit. Both are idempotent per source via the journal
  source guard; an unmapped payment method is a hard stop with the fix named in the message, never
  a suspense-account guess. Posting lives inside RaiseAdvanceInvoice and VerifyPayment — one
  transaction, so the books cannot drift from the act.
- New: payment_method_accounts table + Accounts → Payment methods screen (owner-only) mapping the
  eight methods to asset accounts; seeded defaults incl. a new 1040 Mobile wallets account
  (jazzcash/easypaisa). Chart seeder remains re-seed safe.
- New screens: Sales → Due Payments (/admin/sales/dues) — open invoices oldest-first, aging buckets
  (current/1-30/31-60/61-90/90+), top clients by exposure, and a consent-gated Remind button
  (WhatsApp in-window text or the mapped PaymentReminder template; no consent/number/connection is
  answered with an honest reason, per A0 law).
- Telegram: /collect <invoice> <amount> [method] [reference] — two-step, repeat with `confirm`
  records AND verifies (collector is the verifier), allocates, numbers a receipt, posts to the
  ledger, and answers with the receipt number and remaining balance; refuses amounts above the
  balance. /statement <client> — outstanding invoices, totals, overdue, recent receipts;
  disambiguates duplicate names. Both are in the generated /help.
- Live preview proof: INV-LIVE-0001 (Rs 500,000) → JE-2026-0001 Dr 1100/Cr 4010; /collect-style
  Rs 200,000 cash → RC-2026-0001 + JE-2026-0002 Dr 1010/Cr 1100; dues screen shows the Rs 300,000
  balance. Gate: 87/87 files green (3 env-conditional skips), Pint 620 files PASS, Larastan 0.
  26 new tests / 115 assertions (GlSalesPostingTest, CollectStatementTest, DuePaymentsTest).
- Not in this batch: printable PDF statements (P8.3/P8.4), refunds/reversals after verification
  (no refund flow exists yet — reversal lands with it), scheduled /due digest (P8.5).

## P8.3 Estimates module — the estimator becomes a document — 2026-10-17

- Sandbox VM was reset before this batch (the /tmp runtime, php-wasm rig and composer mirror were
  wiped). Full rebuild in ~4 minutes from tools/sandbox/setup.sh (GitHub-based package resolver —
  no mirror needed); sync.sh auto-applies the wasm TestCase rig fix; preview restored, owner
  password reset to the preview credential. The tools snapshot now lives only in the repo
  (tools/sandbox) — /home/user/tools did not survive the reset.
- New module: persistent, versioned estimates (docs/19 §7, owner decisions: internal surface, all
  6 estimator services, headline + assumptions + catalog lines, EST- numbering). estimates table
  (service_key, inputs, catalog_lines, frozen computed snapshot with effective date, assumptions/
  exclusions, contingency bp, status draft/reviewed/converted, two-way quote link via
  quotes.estimate_id) + Estimate model (activity-logged, morph-mapped as 'estimate').
- One server-side pricing engine (ComputeEstimate) shared by the builder's live summary, the saved
  snapshot and the conversion: chosen-finish headline from EstimatorRate, honest essential→premium
  band, rate book lines priced at today's rates, explicit contingency in basis points. The
  snapshot freezes what was computed — later rate changes never rewrite a saved estimate.
- Actions: SaveEstimate (stable EST-{year}-{seq} number allocated once; converted estimates refuse
  edits), ReviewEstimate (deliberate reviewed_by/at act), ConvertEstimateToQuote (idempotent —
  converting twice returns the same quotation; creates a quote DRAFT with the headline line + a
  rate book items section + range/assumptions/exclusions in the internal notes; furniture service
  → WF numbering, everything else → WI; requires a client).
- Screens: Sales → Estimations (/admin/estimations list with status filter; /admin/estimations/new
  and /{id} builder with live pricing card). Permissions estimates.view/estimates.manage wired to
  owner, admin, sales_manager, sales, designer_pm (manage) and accountant (view).
- Live proof: EST-2026-0001 (interior design, 1200 sqft, standard, 5% contingency) = Rs 315,000,
  range Rs 180,000–480,000 → converted to WI-10103 draft. Gate: 88/88 files green (921 passing),
  Pint 630 files PASS, Larastan 0 errors, 13 new tests / 59 assertions (EstimatesTest).
- Not in this batch (per owner decisions): client-facing estimate PDFs, full BOQ builder,
  kitchen/wardrobe estimate definition (no rate source yet). P8.3's brand/template layer was
  already active from P8.0 (description library, document designer, rate book with pictures,
  quote templates).

## P8.4 Sales tracking boards + client statements — 2026-10-17

- Owner decisions (ask_user, 4/4 recommendations taken): grouped LIST boards, not drag-and-drop
  Kanban; the quote board is EVENT-DRIVEN (reuses the auto-chaser rules, no new columns or states);
  the invoice board carries the real actions (inline Collect via P8.2 CollectPayment,
  consent-gated Remind via P8.2 SendPaymentReminder, statement/PDF links); client statements are
  a branded PDF DOWNLOAD only (no sending this phase).
- Quote tracking (/admin/sales/tracking/quotes, quotes.view): six groups exactly mirroring
  ChaseQuotations — follow-up due (sent > SalesSettings.quote_follow_up_days ago, <3 follow_up
  events, last chase older than the window), awaiting reply, stale (≥3 unanswered chases since
  sent_at — an answer in itself), expiring soon (valid_until within quote_expiry_warning_days),
  recent wins (accepted in 30 days), lost (declined/expired in 30 days). Group cards with counts +
  value; Log-chase action writes a 'follow_up' QuoteEvent via LogQuoteEvent (quotes.manage) with a
  note — same event the chaser counts, so board and automation can never disagree.
- Invoice tracking (/admin/sales/tracking/invoices, invoices.view): overdue > due ≤7d > current
  priority, partially-paid overlay group, recently-settled (last 10). Inline collect = the P8.2
  one-act CollectPayment (records + verifies + numbers a receipt + posts the ledger entry), gated
  behind payments.record+verify; reminds are consent-gated by SendPaymentReason's own rules with
  the honest reason toasted on refusal.
- Client statements: GET /admin/clients/{contact}/statement.pdf?from=&to= (invoices.view) renders
  live (never stored) a branded letterhead PDF (clone of the receipt template) — every issued/
  partial/paid invoice and receipted payment in the period with a running balance, totals,
  outstanding and aging buckets (Current/1-30/31-60/61+ by due_date). Statement button on the
  contact page header; per-row statement link on the invoice board.
- REAL BUG the tests caught: 'date'-cast columns (payments.received_on, invoices.issue_date) store
  'Y-m-d H:i:s' strings, so raw-SQL comparisons against bare 'Y-m-d' strings silently miss
  same-day rows (a statement window ending today excluded an invoice issued today; a payment
  received today was invisible). ClientStatementPdf now compares against Carbon day boundaries.
  DuePayments was safe (whereDate), the boards group in PHP on cast carbons.
- No migrations, no new statuses, no new permissions — the boards read existing truth. 13 tests /
  50 assertions (TrackingBoardsTest): grouping incl. time travel, stale-after-3, chase logging +
  permission 403, invoice grouping, end-to-end collect (receipt + journal entry), over-balance
  refusal, payments permission gate, consented + refused remind (FakeWhatsAppGateway), statement
  numbers/aging/filename/inline disposition/404/permission, period filter.
- Sandbox notes: second VM reset mid-build (recovered: fetch + reset --soft; staged diff was
  exactly the P8.4 files + stale public/build deletions that the setup npm build regenerates).
  shard-gate.sh was wiped with /tmp — recreated and now PERSISTED in tools/sandbox/ so the next
  reset can restore it. Screenshots of the three screens saved to /home/user/p84-shots/.
- Gate: 89 files / 934 passed / 1 env-conditional skip / 0 failed (shards), Pint clean on all
  touched files, Larastan 0. The gate also exposed a latent suite hazard: CollectStatementTest and
  TelegramCommandCentreTest both declared global commandUpdate()/sendCommand() helpers — fatal
  whenever one process loaded both. Renamed the command-centre pair to centreUpdate()/centreSend()
  (identical bodies) and TrackingBoardsTest's helper to trackedQuote() so any shard order is safe.
  Live preview: both boards + statement.pdf (with ?from period) 200 as owner; INV-LIVE-1 visible
  on the board; Statement button present on the contact page.
- Not in this batch (per owner decisions): statement sending (consent-gated) — a later phase;
  drag-Kanban and manual follow-up-date columns explicitly rejected.

## Production database update stuck — resumable migrations — 2026-10-17

- Production (u128159657_woodexint, MySQL/Hostinger) hit "Base table or view already exists:
  1050 Table 'payment_method_accounts' already exists" on the one-click Database update. Root
  cause: MySQL DDL is NOT transactional — an interrupted first attempt (timeout/aborted
  request/racing clicks) had already created the table but never wrote its migrations row, so
  the retry re-ran CREATE TABLE. Pending at the time: 160000 payment_method_accounts,
  170000 estimates.
- Fix: both pending migrations are now RESUMABLE — up() guards each step (hasTable for the
  creates, hasColumn for quotes.estimate_id), so a retry records the migration and moves on
  instead of fatalling. The table could only have been created by the same migration's DDL, so
  its shape is already right. New rule for every future migration: guard DDL steps (MySQL has
  no transactional DDL; the one-click update must always be re-runnable).
- DatabaseUpdates::apply() now also seeds ChartOfAccountsSeeder after permissions/sequences:
  it is firstOrCreate keyed on stable account codes and method names, never overwrites owner
  edits, and it is what gives production the payment-method → ledger mappings P8.2 needs to
  post receipts (a database that got the accounts TABLES from an earlier update but never the
  seeded rows — possible, apply() never seeded them — receives the defaults now).
- Regression test tests/Feature/ResumableMigrationsTest.php reproduces the exact stuck state
  (tables present, migrations rows deleted, a pre-existing mapping row) and proves migrate
  exits 0, re-records both rows, preserves data, and the next run is a plain no-op.
  DatabaseUpdatesTest's wording assertion updated for the new log line. Verified: Installer +
  DatabaseUpdates + SystemHealth + Estimates + DuePayments + TrackingBoards + Telegram collect
  all green (50 passed in the focused run), Pint clean, Larastan 0, and a real `woodex:update`
  on the runtime DB reports "Nothing to migrate … up to date".
- Owner recovery: redeploy the branch on hPanel (if auto-deploy is off) → open the site →
  Apply the database update — it should now complete, seed permissions/sequences/chart, clear
  caches, and the staff "updating" notice clears on its own.

## P8.5 part 1 — automation backbone + control dashboard — 2026-10-17

- Owner decisions (ask_user, 4/4 recommendations taken): build order = backbone first then
  flows; chat widget = own web_chat channel in the Conversations inbox (WhatsApp handoff later,
  nothing waits on Meta); new-client alert = Telegram to the lead owner + sales manager with an
  email fallback, nothing client-facing without consent (A0); monthly report = Telegram to the
  owner on the 1st 09:00 + email fallback + a saved screen under /admin/automation.
- Automation model (Domain/System/Models): the CATALOG is code (key, name, description, cadence,
  toggleable) — 4 business automations (sla-check, reminders, quote-follow-ups, review-requests)
  + 5 platform jobs shown read-only (webhook recovery, queue worker, heartbeat, nightly backup,
  housekeeping). A missing row means ENABLED (fail-safe, zero-seed: fresh installs and un-seeded
  production updates are correct by construction). Rows appear only on a toggle, carrying
  toggled_by/toggled_at. enabled() caches 60s; toggle() clears its own key.
- Schedule gates: the 4 business commands in routes/console.php now carry
  ->when(fn () => Automation::enabled(...)) — a paused automation is skipped by the scheduler
  (fires ScheduledTaskSkipped, so no false run rows); toggles land within a minute.
- Screen: /admin/automation (Automation → Automations, permission automation.manage = owner,
  admin, sales_manager) — On/Paused badges, cadence, description, last run + runtime from
  ScheduledTaskRun (the scheduler's own record — the screen cannot claim health it did not
  observe), pause/resume with activity log. Platform jobs section read-only by design (a 403,
  not a hidden button, if someone posts at them). The P4 "Follow-up Sequences" nav placeholder
  became this live item.
- Migration 2026_10_17_180000_create_automations follows the new resumable rule (hasTable guard).
- 7 tests / 38 assertions (AutomationsDashboardTest): default-enabled, toggle + attribution +
  activity row + resume, permission gates, non-toggleable + unknown keys 403, run-history
  rendering, the schedule filter actually flips (Schedule events ->filtersPass(app())) and
  flips back, and a guard that every toggleable catalog key has its gate in routes/console.php.
- Sandbox gotcha (new): running `artisan view:clear` while the preview server is serving breaks
  the running process (wasm FS stat cache serves dead compiled-view paths → 500s on every
  Blade page). Fix: restart the preview process. Do not view:clear under the live preview.

## Production update stuck again on automations — serialized updates — 2026-10-17 (night)

- The 01a836f resumable fix worked (payment_method_accounts and estimates recorded — they left
  the pending list), but the new 180000 automations migration hit "the same error" on production.
  The deployed file HAD the hasTable guard, so a sequential retry could not fatal — the only
  remaining window is CONCURRENCY: two Apply clicks in flight (double-click, two tabs, two
  admins) both pass hasTable() before either commits CREATE TABLE, the loser fatals 1050 and can
  leave table-created-without-row. That race is also the most plausible cause of the ORIGINAL
  160000 incident. Production's engine also accepted the INT→BIGINT FKs in estimates, so the
  engine is the lenient kind (MariaDB) and the FK was never the blocker.
- Hardening shipped:
  1. DatabaseUpdates::apply() is now SERIALIZED by an atomic cache lock (10-minute TTL, released
     in finally). A second click while an update runs gets an honest "An update is already
     running — wait, then reload" instead of racing. A cache store without lock support must not
     block updates at all (lock failures degrade to running unprotected).
  2. The automations migration drops its foreign key on toggled_by (kept as a correctly-typed
     foreignId column, no constraint): a constraint on a handful of audit rows is not worth a
     failed deploy. The rule stands — the one-click update must survive anything.
  3. ResumableMigrationsTest now covers 180000 too; new DatabaseUpdatesTest case proves the lock
     blocks a second update and that apply() releases its own lock (this test caught a real bug:
     inside DatabaseUpdates, `Cache` is the Repository interface alias — the lock call threw and
     was swallowed by the lock-failure fallback, silently running unprotected. Now CacheFacade).
- Manual escape hatch if anything still fails on production: the automations table holds only
  toggle overrides (production has none yet) — DROP TABLE automations in phpMyAdmin, then Apply.

## P8.5 part 2 — new-enquiry alerts + ask-for-number — 2026-10-17 (night)

- Owner decision implemented (from the 4/4 ask_user): instant alert to the lead's OWNER AND the
  sales managers (both, always — previously owner-only), Telegram + in-app, EMAIL for staff
  without a Telegram link; nothing client-facing without consent.
- SendLeadAlert (existing TGM-002 job) now: gated by Automation 'woodex:new-lead-alert';
  recipients = active sales managers + the lead's owner (deduped); unassigned leads still fall
  back to NotificationSettings.unassigned_lead_roles (sales_manager + owner). NewLeadNotification
  gains a 'mail' channel + toMail() when the recipient has no Telegram route (the admin action
  button only appears on https URLs, same rule as the Telegram buttons).
- Ask-for-number (new): listener AskForMissingNumber on LeadCaptured → job AskForPhoneNumber.
  When a NEW enquiry arrives with an email and not one phone number anywhere (phone/whatsapp/
  alt), the job opens a follow-up task (ManageTask, due at SLA, assignee = lead owner) and sends
  ONE branded reply (AskForNumberMail, mail/ask-for-number.blade.php) asking for a WhatsApp
  number — a service response to the client's own enquiry, never marketing. The task is the
  exactly-once anchor: it exists → the ask already went; queue retries can never double-send.
  A System activity records the ask on the lead timeline. Gated by Automation
  'woodex:ask-for-number' so the owner can silence the client-facing reply independently.
- Automation catalog: entries now carry a 'scheduled' flag — the two new flows are event-driven
  ("On every new enquiry" / "On enquiries without a number") and are gated where they fire; the
  schedule-consistency test only checks scheduled+toggleable keys now.
- Tests: NewLeadAlertFlowTest (7) — owner+manager alert, unassigned fallback (capture
  auto-assigns, so the test nulls the owner first — a real behaviour worth remembering), email
  fallback via() proof, paused automation silence, ask-once idempotency across a retry, no ask
  with a phone or when paused, no ask for returning enquiries. PublicLeadApiTest still green.
- Live: /admin/automation shows both new rows with their event-driven cadences.

### 44. P8.5 part 3 — web chat widget (own `web_chat` channel, rule-based intake) — 17 Oct (evening)
- The studio's own chat bubble on the public site: no WhatsApp number, no gateway, no Meta rules.
  A visitor opens the bubble → rule-based intake asks name → WhatsApp number (or email) → what
  they need done → CaptureLead (single entry point, source WebsiteChat) creates the lead and the
  LeadCaptured pipeline fires part 2's owner+manager alert automatically. From there the thread is
  a normal conversation in the existing Conversations inbox, channel "Website chat"; the widget
  polls every 5s while open and shows the team's replies. WhatsApp handoff stays a later step
  (needs the business number approval).
- A0 on-switch: `site.chat_widget_enabled` defaults FALSE; the toggle lives in Website Settings
  ("Web chat widget on the public site"). Saving the screen drops the whole published-page cache
  (PageCache::invalidateAll) — without it visitors would keep yesterday's HTML for a day.
- Serving split the site actually has (docs/11 §2): the 142 imported v1 pages are REAL static
  index.html files served by Apache's DirectoryIndex before Laravel ever runs — so the bubble is
  injected twice: (a) layout include for CMS/v2 pages (gated, zero cost when off), (b) a loader
  appended to public/assets/site.js (the progressive-enhancement bundle every static page links).
  The loader always fetches /chat/widget.js; with the widget off that URL answers a no-op comment
  (never a 404, so a cached page degrades silently) and the data endpoints 404 for real.
- Endpoints (public group, stateless): POST /api/public/chat/start|send, GET /api/public/chat/
  messages?after=; new rate limiters chat-write (20/min, 500/day per IP) and chat-poll (90/min).
  Token = 40 random chars in the visitor's localStorage; an unknown token is adopted, never
  refused (works after a DB wipe); adoption regex-blocks junk tokens. Migration adds
  conversations.widget_token (unique) + widget_state; both guarded for resumability.
- Consent discipline: typing a number into a chat box is NOT WhatsApp consent (consent_whatsapp
  stays false). Email-instead-of-number path: the chat itself asks for the number; the follow-up
  task AskForPhoneNumber::TASK_TITLE (now public) is created as the exactly-once anchor so the
  part-2 email job skips — the client is asked once, never twice. A System activity explains it.
- SendConversationMessage: channel branch for WebChat — no gateway, no consent assert, no window
  (the visitor opened this thread); stores Out + touchLastMessage. Conversation::
  allowsFreeFormReply() keeps the inbox composer enabled for web_chat threads (isInWindow alone
  would disable it — no window exists there). Bot turns are Out messages with meta bot=true, so
  the widget can tell the studio's greeter from a human reply.
- Conversation $fillable gained widget_token/widget_state (mass assignment silently drops
  unknown columns — the token came back null until this was spotted; worth remembering).
- Tests: WebChatWidgetTest (13) — off = invisible + no-op script + 404 data endpoints; script +
  greeting + token resume; full intake → lead + live thread + no WhatsApp consent; email path →
  anchor task + no AskForNumberMail; invalid number re-ask; staff reply without gateway + poll
  returns it; unknown token adopted; live-thread messages still stored; 2000-char bound (422
  above); static-page loader shipped; cache drop on toggle (through the real WebsiteSettings
  screen); unread counting; inbox shows the thread + composer works. Gate: 93 files / 963 passed.
- Live: verified in a real browser on the static home page — bubble, resumed thread with the
  staff reply, zero JS errors — and on the CMS-rendered /furniture/ page. Widget left ON in the
  sandbox preview for the owner to try; production default remains off until switched on.

### 45. P8.5 part 4 — monthly CRM report (1st 09:00, owner Telegram + email fallback, archived) — 17 Oct (night)
- The owner's 17 Oct decision, shipped: on the 1st at 09:00 the month that just ended reaches the
  owner in one message, and every report is archived under System → Automations.
- GenerateMonthlyReport (System action): period = previous calendar month by default (or any
  month passed as its first day); payload reuses the reports kit the screens use — SalesFunnelReport
  headline + bySource (RPT-002) and RevenueCollectionsReport totals (RPT-005) — plus overdue
  invoices (count + oldest age) and website-chat thread/message counts. Message and dashboards
  can never disagree because they are the same queries.
- One row per month: monthly_reports.period_start is UNIQUE; regenerating a month (late import,
  correction, the "Build last month now" button) updates the row in place. The archive list shows
  period, sent/archived badge and headline chips; a row expands to the full detail grid.
- MonthlyReportNotification: Telegram when the owner has a link, email when not, database copy
  always (bell menu → the archive); action buttons only on https URLs, same rule as every
  notification. Lakh-formatted money via Money + SalesSettings.lakh_grouping.
- Schedule: woodex:monthly-report monthlyOn(1, '09:00') gated by Automation::enabled, catalog
  entry 'woodex:monthly-report' (scheduled + toggleable, so pausing the row on the dashboard
  stops the monthly send). Run history lands on the dashboard via the existing ScheduledTaskRun
  listener. The command takes --month=Y-m-01 and --no-send for debugging.
- "Build last month now" on the dashboard: explicit human action, so it runs even while the
  automation is paused; same computation, sends like the scheduled run.
- Gotchas met: (a) date columns store datetimes — query with whereDate() or Carbon objects, a
  bare 'Y-m-d' string binding does not match 'Y-m-d 00:00:00' in sqlite; (b) a Livewire public
  property and a render variable of the same name collide — the blade sees the component property
  (renamed to $expandedReport).
- Tests: MonthlyReportTest (9) — default previous month + send, period boundaries (September
  counts September, August is the comparison), one-row-per-month regeneration, --no-send,
  Telegram/email routing, no-owner safety, first-day validation, scheduled command + switch
  honouring, dashboard archive + on-demand build. Gate: 94 files / 972 passed.
- OPERATIONAL LESSON: the full gate raced the live preview — the owner's open admin tab polls
  Livewire every few seconds, each poll boots wasm PHP from the same /tmp/woodex-run/app, and a
  manifest rebuild there deleted bootstrap/cache/services.php mid-shard (78 phantom failures).
  Stop the preview before running the gate; restarted after. Code was never at fault.
- Live: September 2026 archived and sent on the preview (3 enquiries, 1 overdue invoice);
  /admin/automation shows the card, the archive row and the expand.

### 46. P8.5 part 5 — dormant SEO/social agent stubs; P8.5 COMPLETE — 17 Oct (night)
- The last piece of the owner's 17 Oct order: two agents shipped DORMANT — off by default,
  switched on by the owner alone (A0), rule-based only (an LLM layer can start from the same
  lists later, if ever).
- Dormancy is now a catalog concept: Automation entries may carry default_enabled=false;
  enabled() falls back to the catalog default when no row exists (the missing-row fail-safe
  still means ON for everything else), and toggle() creates the row AT the default so the
  first click on a dormant agent is "Switch on", not a pause. The dashboard shows a gray
  "Dormant — off by default" badge and "Has never run — it is dormant until you switch it on".
- woodex:seo-agent (Sundays 08:00, dormant): CheckSeoHealth — a read-only sweep of published
  pages (missing meta descriptions, titles over 65 chars) and redirects that point at no
  published page; the owner gets a short list, the agent never edits or publishes anything.
  A clean site gets "Everything looks healthy".
- woodex:social-agent (Mondays 09:30, dormant): CollectSocialPostIdeas — the week's handovers
  signed, pages published or refreshed, projects won, as post ideas for the owner alone. It
  never posts, never drafts client-facing copy, never names a client; a quiet week says so.
- AgentReportNotification: one shared shape for agent digests (kind, title, lines, url) with
  the standard routing — Telegram when linked, email otherwise, in-app copy always; buttons
  only on https URLs.
- Both commands are gated in routes/console.php like every scheduled business automation, so
  the catalog/schedule consistency test covers them; ScheduledTaskRun records their runs once
  the owner wakes them.
- Tests: AgentStubsTest (8) — dormant defaults with everything else still fail-safe ON,
  dashboard Dormant badge + Switch-on flow, schedule skips dormant agents until toggled, real
  SEO findings (bare page, long title, broken redirect), clean bill, post ideas with the
  40-day-old handover and the client's name excluded, quiet week, Telegram/email routing.
  Gate: 95 files / 980 passed / 0 failed.
- Live: /admin/automation shows both rows as Dormant with Switch on buttons; verified in a
  real logged-in browser. P8.5 is COMPLETE: control dashboard → new-enquiry alerts +
  ask-for-number → web chat widget → monthly report → dormant agents, all shipped green.

## §47 — P10.1: sidebar consolidation + the lead tracking board (17 Oct, evening)

Owner decisions (two ask_user rounds, all four answered):
- **Overview ✓ proposed:** Dashboard page gets tabs (Overview · My Day); Approvals keeps its own
  entry (action queue); the old Reports group folds into Overview as a Reports page
  (Sales funnel · Revenue & collections).
- **Sales ✓ proposed (the owner's own two examples applied to the group):** Estimations ·
  Quotations (All · Tracking · Templates) · Invoices (All · Tracking) · Payments
  (All · Dues · Receivables) · Documents (Descriptions · Document Design · Rate Book).
- **CRM:** "CRM is simple — need a complete tracking system, simple Leads need tracking." The
  Leads page gets a third tab: Tracking. Board groups by follow-up state (Uncontacted · Due
  today · Overdue · Stale · Snoozed), cards carry Log follow-up (note + next action) / open /
  WhatsApp, alerts are passive only (A0). Contacts (People · Companies) and
  Tasks & Visits (Tasks · Site Visits) merge as tabs.
- **Rest ✓ keep unchanged:** Accounts, Projects, Inbox, Automation, Marketing, Website, System.

What shipped:
- `app/Livewire/Admin/Hubs/Hub.php` — one abstract workflow-hub base: tabs in the URL (?tab=),
  linkable/back-button safe, permission per tab (hidden from the bar; a directly requested
  hidden tab aborts 403 — the same promise the old route middleware made). Nine hubs:
  Dashboard, Reports, Leads, Contacts, Tasks, Quotations, Invoices, Payments, Documents. The
  existing screens mount unchanged inside them (livewire:dynamic-component); no screen code
  was rewritten.
- 14 old URLs became 301 closures that keep their route names (route()-based, so the admin
  prefix travels with config): /my-day → /admin?tab=my-day, /pipeline, /companies, /site-visits,
  /quote-templates, /sales/tracking/quotes, /sales/tracking/invoices, /sales/dues, /receivables,
  /rate-book, /description-library, /document-design, /reports/sales-funnel,
  /reports/revenue-collections → their tabs. Bookmarks, notifications and Telegram buttons
  keep working; internal links (leads list "Board view", dashboard Pipeline, My Day visits,
  company breadcrumbs, global search, description-library rate-book link, CompanyShow
  redirect) now point straight at the tabs.
- `LeadTrackingBoard` — event-driven like the P8.4 quote board: reads first_response_at,
  next_action_at, last_activity_at; no new states or columns. Log follow-up =
  LogLeadActivity + SetNextAction (first touch sets first_response_at). Gate: leads.update.
- Sidebar: AdminNavigation map 49 → 36 live entries, 11 → 10 groups (Reports folded in);
  collapsible groups (persisted in localStorage 'wx.groups', Alpine store groupOpen/toggleGroup);
  live badges — Inbox unread conversations and pending quote approvals (counts only, A0).
- Tests: P10SidebarHubsTest (42) — map, all 22 hub renders, 14 redirects, tab permission 403s,
  badges, board classification (frozen clock; healthy leads stay off the board), follow-up
  logging. Eight older tests repointed from old URLs to hub tabs (same permission assertions).
  Gate: **96 files / 1022 passed / 0 failed** · Pint clean · Larastan 0.
- Live: preview verified logged-in — sidebar shows the new map with the Inbox badge, quotes hub
  tabs (tracking selected), leads tracking board groups, /admin/my-day → 301 → /admin?tab=my-day.
  Shots in /home/user/p101-shots/ (sidebar-dashboard, quotations-hub, lead-tracking-board,
  my-day-tab), each DOM-verified in the same script.

Gotchas hit: (1) Alpine `:class="$store…"` on a Blade component tag (<x-icon>) compiles as PHP —
wrap in a plain HTML span; (2) CaptureLead dedupes by phone, so tests need distinct numbers per
lead; (3) `now()->addHours(3)` crosses midnight near end of day — freeze the clock in board tests;
(4) the preview's php -S needs `-t public` with a router that checks `is_file(public.$uri)` or
hashed assets 404 and the Alpine store never registers (sidebar goes blank).

## 48. P10.2 — Live website chat runtime (17–18 Oct 2026 night)

Owner's ask: know a website visitor is texting the bubble from any admin page, answer from
there, no page reloads, never auto-open anything. Shipped as a header bell + drawer.

- `LiveChatBell` component (`app/Livewire/Admin/Conversations/LiveChatBell.php`) mounted in the
  admin header before the notification bell: unread badge (counts web_chat conversations with
  unread > 0), green dot while a visitor messaged within the last 2 minutes, `wire:poll.10s.visible`
  refresh, and a slide-over drawer listing live web_chat threads (open/pending, newest first, 30 max)
  with the full message thread and an inline reply box. The whole blade is guarded by
  `canUse` (inbox.view): staff without it render an inert empty root — the drawer markup never
  reaches their page (test asserts `Website chat` is absent).
- Toast, not interruption: each render compares `newestUnread()->last_message_at` against the
  per-user session baseline `livechat.seen_at` (set on first render — history never replays) and
  dispatches `chat-alert {name, preview, sound}`. admin.js pushes an 8-second toast with an
  **Open chat** button that fires the `open-live-chat` window event → drawer opens, newest thread
  auto-selected, `markRead` claims it (unread → 0, teammates' badges fall too). A teammate reading
  the thread first suppresses the toast for everyone. The drawer NEVER opens by itself (owner
  decision P10-C).
- Reply path: `send()` runs SendConversationMessage with the new `web_chat` branch — message
  stored direction=out/status=sent, no external transport, conversation touched. Verified in the
  browser: reply appears in-thread instantly, draft clears.
- Sound: studio-wide `notifications.chat_alert_sound` (settings migration
  `2026_10_18_000100`, **default off**). Toggled from the drawer footer (settings.manage gate,
  403 otherwise) or Settings → Notifications checkbox. When on, the browser plays
  `public/sounds/chat-alert.wav` (0.35s two-tone ding) on chat-alert; autoplay blocks are caught.
- Tests: `P10LiveChatTest` — 12 green: permission wall, badge + live dot, 2-minute quiet window,
  web_chat-only drawer, no history replays, toast-once-then-baseline, teammate-read suppression,
  drawer-open marks read, inline reply, sound default/permission, sound round-trip, rides-along
  on every admin page. Gate: **97 files / 1034 passed / 0 failed** · Pint clean · Larastan 0.
- Live: preview E2E in a real browser — visitor message injected mid-session; poll carried it,
  toast showed "Website chat — Bilal Akhtar: …" with Open chat, drawer opened on click, badge
  dropped 2 → 1, reply sent. Shots in /home/user/p101-shots/ (live-chat-bell, live-chat-toast,
  live-chat-drawer, live-chat-reply).

Gotchas hit: (1) assertSeeHtml on a `<template x-teleport>` drawer — Livewire's test html()
strips template contents, so the drawer renders inline (no teleport; fixed positioning works,
header subtree has no transform ancestors); (2) Livewire action aborts surface as
`assertStatus(403)` on `->call()`, not `->throws(HttpException)`; (3) DB timestamps are
second-precision — seeding a baseline conversation BEFORE the first render (and nudging the new
message a second into the future) avoids the same-second `gt()` trap; (4) a silent python
str.replace miss left admin.js unedited while everything else shipped — verify edits with
grep before building; (5) the toast lives 8 seconds — E2E checks must wait for the event,
then assert immediately.

## 49. P5 S4+S8 — digests and quiet hours that queue (18 Oct 2026)

The owner picked "finish the command centre" from the pending list. Recon showed docs/16's
"S3–S8 remain" was stale: S3 (callback bus + 4 action commands), S5 (health completion), S6
(change-requests board) and unlink-on-deactivation (TGM-007, in ManageStaff::setActive) were
already shipped on 17 Oct. What genuinely remained: S4 digests and S8's quiet-hours batching.

- `woodex:digest {--period=daily|weekly}` (SendDigest): daily 09:00 and Mondays 09:00, one
  message per **linked** chat. `DailyDigest` (app/Domain/System/Telegram/Digests): today's
  visits, the member's open tasks, follow-ups due (visibleTo, open, next_action_at ≤ today),
  yesterday's leads/quotes/money-in, approvals waiting — each line only for roles that may see
  it (payments.view, quotes.approve…). `WeeklyDigest`: last week's leads + top sources, won/
  lost, quotes sent/accepted, collected, still-to-collect with overdue count. Delivered via
  `DigestNotification` (SendsToDatabaseAndTelegram): Telegram copy and the in-app bell copy are
  the same composed text. Switches: woodex:daily-digest, woodex:weekly-digest (Automation
  catalog, default ON).
- **Quiet hours queue (TGM-008)**: `telegram_pending_messages` table + `PendingTelegramMessage`
  (cap 20 per chat, oldest kept; delivered rows pruned after a week). `TelegramChannel` is now
  the single delivery decision point: muted chat + normal notification → queued; a notification
  may declare `isCritical(): true` to bypass the mute (none do yet — classification arrives with
  the alert-rules engine, S7/other lane). via() checks `User::telegramChatIdIgnoringMute()` so a
  muted link still reaches the channel (it used to exclude it — alerts were silently dropped,
  and the mail fallback fired instead). `woodex:telegram-flush` every minute
  (FlushPendingTelegramMessages) delivers the backlog oldest-first once the mute has actually
  ended; a failed send stays queued. The digest waits out a mute like any alert — "quiet hours
  apply to the daily digest" from the S4 spec.
- Tests: `TelegramDigestTest` — 9 green (content + permission split, weekly window anchored on
  startOfWeek, mute→queue→flush round-trip, buttons survive the queue, critical bypass, cap 20,
  catalog entries). Gate: **97 files / 1040 passed / 0 failed** · Pint clean · Larastan 0.
- Ops note: the sandbox reset wiped /tmp AND reset the repo checkout to d77016b with an empty
  index; history was restored from the pushed branch (`git fetch` + `reset --soft` + `add -A`)
  — the working tree was never at risk. The gate script was rebuilt at /tmp/woodex-run/shard-gate.sh
  (Feature shards + tests/Unit).
- Honest gap: no live-phone verification yet — the owner's Telegram is not linked in the
  preview (needs /start CODE from his profile). Everything is covered by the fake-gateway tests.

Gotchas: (1) `test()->artisan()` / `$this->artisan()` in this suite return the **exit code
(int)**, not a PendingCommand — `->assertSuccessful()` does not exist; (2) name the migration's
table to match the model's convention BEFORE debugging phantom "no such table" errors — the
migration created `telegram_pending_messages` while the model derived
`pending_telegram_messages`, and copy-pasting the wrong name into debug probes made two
different-looking states; (3) `withoutOverlapping` on the flush is unnecessary (minute cadence,
fast command) but harmless; (4) Eloquent timestamps: a table without created_at/updated_at
needs `$timestamps = false` or the insert fails on sqlite.

## 50. P4 inbox quick wins — mentions, conversation SLA, WhatsApp health (18 Oct 2026)

The owner picked the batch from the pending list. Four of the five shipped; media sending
(CNV-008) is the one left, by design — it is a gateway-upload + composer piece of its own.

- **@mentions in internal notes (CNV-003b)**: `SendConversationMessage::note()` now runs the
  CRM's mention matcher (`NotifyMentions::mentions`) over the note body, stashes the mentioned
  ids in message meta and notifies each teammate (in-app + Telegram, `ConversationMentionedNotification`,
  link straight to the inbox). The author is never notified; a plain note stays plain.
- **Conversation reply SLA (CNV-007)**: new columns `reply_due_at` / `reply_sla_breached_at` /
  `reply_sla_escalated_at` on conversations. Every inbound WhatsApp message arms the clock —
  the first reply gets `conversation_first_reply_minutes` (default 30), later ones
  `conversation_next_reply_minutes` (default 15) — and any staff reply (text/template) clears
  it. `woodex:sla-check` now also runs `CheckConversationSla`: breach → assignee +
  unassigned-lead roles; still unanswered after `sla_escalation_minutes` → the final roles,
  once, with atomic claims. Website chats are deliberately not armed — the lead SLA and the
  live-chat bell already cover them; double alerts train people to ignore alerts.
- **WhatsApp quality watch (WAP-010)**: `CheckWhatsAppQuality` runs in the new daily
  `woodex:whatsapp-health` (09:10, Automation catalog): stores Meta's rating, alerts
  integrations managers the day it drops (GREEN→YELLOW→RED). Honest gap kept in the code
  comment: Meta does not expose the messaging-limit tier on the phone-number node, so that
  half waits for a real API surface. The Integrations card now shows "quality green".
- **Cost meter (WAP-008)**: Settings → WhatsApp gained Cost per message / per template /
  Monthly budget (rupees, 0 = count only). `CheckWhatsAppUsage` (same daily command) counts
  this month's successfully sent messages — failed sends, notes and website chats excluded —
  prices them, and alerts integrations managers once per month when the estimate crosses the
  budget. The messages table is the truth; no rollup table to keep in step. The Integrations
  card shows "this month: N sent · est. Rs X".
- Tests: `InboxQuickWinsTest` — 6 green. Gate: **88 files / 1046 passed / 0 failed**
  (947 Feature + 99 Unit) · Pint clean · Larastan 0.

Gotchas: (1) new datetime columns must be added to the model's casts — a raw string
`reply_due_at` breaks `diffInMinutes` the moment a test touches it; (2) Conversation has no
`open()` scope (unlike Lead) — filter by the status column; (3) Carbon 3's diffInMinutes is
signed — `now()->diffInMinutes($future)` is positive, the other way round is negative;
(4) `App\Models\User`, never `App\Domain\System\Models\User` — the wrong import only
explodes at runtime, Larastan passes it silently.


## 51. CNV-008 — media back to the client (18 Oct 2026)

The last quick win, in its own wave as planned. One rule held throughout: media is a free-form
message — consent and the 24-hour window apply exactly as for text, no exceptions for pictures.

- **Gateway**: `uploadMedia()` (multipart POST to /{phone-number-id}/media, logged as
  `media.upload` with only mime/name/bytes — never the token, never the file) and `sendMedia()`
  (image/document/audio/video by Meta id, caption under images/docs/clips, filename on
  documents). Fake gateway records both legs.
- **Send**: `SendConversationMessage::media()` — classifies the mime (image/audio/video/PDF;
  anything else is a ValidationException, sizes capped at Meta's limits 5/16/16/100 MB), keeps
  the studio's copy on the `local` disk FIRST (a failed Meta upload still lands in the thread as
  failed, like text), then upload → send → message row with media_* filled. `last_preview` says
  "Photograph — caption"; a successful media reply clears the CNV-007 reply clock like any reply.
- **Composer**: paperclip on WhatsApp threads only (web chat stays text), instant feedback on a
  type/size Meta would refuse, the typed text becomes the caption, chip preview with remove.
- **The thread finally renders media — both directions.** Inbound photographs were stored since
  WAP-003 but invisible; now images, audio, video and document links render through
  `/admin/conversations/media/{message}` (`ConversationMediaController`): private disk, per-
  conversation `inbox.view` authorization, inline for image/audio/video/PDF, download for the
  rest, `X-Content-Type-Options: nosniff`.
- Tests: `ConversationMediaTest` — 8 green (action, refusal, window, file serving permissions,
  composer upload flow, WhatsApp-only attach). Gate: **100 files / 1057 passed / 0 failed** ·
  Pint + Larastan clean.

Scope notes: in-browser voice recording and location sending are not in — an audio file can be
attached as a voice note, and location needs a picker worth its own slice. The web-chat widget
stays text-only (v1 widget).

Gotchas: (1) Livewire 3's temp upload class is
`Livewire\Features\SupportFileUploads\TemporaryUploadedFile` — it extends Laravel's
UploadedFile, so it flows into actions typed against `Illuminate\Http\UploadedFile`, but the
old `Livewire\TemporaryUploadedFile` namespace is v2 and Larastan rightly rejects it;
(2) `x-icon` names: `paperclip` and `x` exist, `close` does not; (3) the rebuilt sandbox keeps
node on PATH — shard-gate.sh now honours `$NODE` and falls back to `command -v node`.


## 52. AUT-001/002/005/006 — the follow-up sequences engine (18 Oct 2026)

The biggest single piece of P4 after the bot: the engine that turns one-off chases into the
studio's whole follow-up rhythm, with the safety limits shipped as part of it because "must
exist before any sequence can send" was the requirement's own ordering.

- **Schema**: `automation_sequences` (trigger, exits_on_reply, active, builtin) →
  `automation_steps` (position, kind, payload per kind) → `automation_enrolments` (lead,
  status, enrolled_at, end_reason) → `automation_step_runs` (scheduled_at, ran_at, attempt,
  note, error). Nothing is ever deleted: pause/resume/cancel only flip statuses.
- **Engine**: `woodex:automation-run` every minute (Automation catalog, toggleable) →
  `RunSequences` claims due runs atomically, walks **bounded passes** so a wait that ends now
  runs its next step in the same invocation, checks exits first (lead won; lead lost unless
  the sequence IS the lost-nurture; client replied after enrolment; opt-out), then executes:
  wait / send the **mapped** template (moment → WhatsAppTemplate::forEvent, never a Meta name
  in code; no mapping = a skipped run with the reason, not a guess) / create task
  ({first_name} rendered) / move stage. Failed steps are logged and the sequence continues.
- **AUT-006 safe limits** (`AutomatedSendGuard`, Settings → Sales): nothing automated between
  21:00–09:00 (configurable) **unless the client wrote in the last hour** (answering a person
  is a reply, not marketing); max N automated messages per client per day (default 2) across
  ALL sequences, counted from messages with no staff author. A held send is `holdUntil`:
  status stays pending, scheduled_at moves to 09:05 / tomorrow morning, attempt+1, reason in
  the note — visible as held, never dropped, never forced.
- **Triggers wired**: lead captured (listener on LeadCaptured, fresh leads only), lead lost
  (listener on LeadStageChanged), quotation sent (SendQuoteByWhatsApp, only on a real send),
  first quotation view (RecordQuoteView), invoice issued (RaiseAdvanceInvoice), visit
  completed (ManageTask::complete, type visit). Manual enrolment = the screen, later.
- **AUT-002 built-ins** seeded editable (AutomationSequenceSeeder → ProductionSeeder): lead
  not reached · quote not opened · quote seen but silent · invoice reminders · after a site
  visit · lost-lead check-in. The review request stays its own command on purpose.
- **Screen**: Automation → Sequences — sequence list with on/off, steps as the timeline the
  engine runs, the 20 latest enrolments with per-run chips (done/sent, held, skipped, failed)
  and pause/resume/cancel. Route admin.automation.sequences, automation.manage.
- Tests: `SequenceEngineTest` — 7 green (walk, reply exit, quiet hours hold+release, daily
  cap, unmapped-template skip, won/lost exits with nurture exception, pause/resume/cancel).
  Gate: **101 files / 1064 passed / 0 failed** · Pint + Larastan clean.

Gotchas worth their weight: (1) **the builder-claim/stale-model trap** — claiming a row via
`Model::query()->update()` does not touch the in-memory model, so a later `forceFill` back to
the same hydrated value (pending → held → pending) compares clean against `original` and is
SILENTLY DROPPED from the UPDATE; the runner now `syncOriginal()`s after every claim;
(2) tests must target enrolments by sequence_id — the seeded built-ins share triggers and
enroll alongside the test's own sequence; (3) `describe()` in blade got a `?Lead` after the
`new Lead`-in-view smell; (4) lazy loading is disabled in tests — eager load `runs.step`
before filtering by step kind; (5) the sidebar item count test (P10SidebarHubsTest) counts
live nav items — update it when nav grows (now 37).


## 53. BOT-002/003/006/007/008/010 — the website assistant, keyless first (18 Oct 2026)

The bot suite, built the way it should ship: **the machinery is complete and working today with
no API key** — "none" is a real provider that answers honestly ("a human will reply") and hands
the thread to the team. The day the owner puts a key in Settings → Bot, the same machinery starts
thinking; nothing else changes.

- **BOT-002 engine**: `App\Domain\Bot` — a `BotProvider` contract with four brains:
  KeylessProvider (default), AnthropicProvider (Claude, default pick), OpenAIProvider,
  GeminiProvider. Key encrypted at rest (`EncryptedStringCast`, never shown back), model
  name editable, system prompt editable with a sensible built-in studio persona.
- **BOT-003 knowledge**: `bot_knowledge` table + `SyncBotKnowledge` (mirrors every published
  CMS page — title, excerpt, block text, tags stripped; re-runs refresh, unpublished pages'
  mirrors go) + manual Q&A entries in Settings. Retrieval is deliberate term-scoring, not
  vector search: the studio's knowledge is small, and a wrong answer costs more than a hand-off.
- **BOT-007 guardrails, in code**: every reply passes `BotGuardrails::violations()` before the
  visitor sees it — no timeline promises, no discounts, no price the knowledge does not carry
  (digit-normalised, so "150,000" covers "Rs 150000"), no phone numbers or emails. A violation
  is replaced with the safe sentence, recorded, and handed over.
- **BOT-008 cost control**: `bot_usages` — one row per day/channel/provider (requests, replies,
  tokens, hand-offs, guardrail hits). Daily token cap and per-thread bot-reply limit; when
  either trips, the honest fallback + hand-off. The tab shows today's numbers live.
- **BOT-006 hand-off**: the thread goes Pending and `BotHandoffNotification` reaches the
  hand-off roles in-app and on Telegram: no key, model failure, guardrails, turn limit, or
  studio hours (BOT-010's evenings-only mode — the team answers during the day).
- **BOT-001/004** were already done by P8.5's widget + rule-based intake — the pending list is
  corrected. BOT-005 (booking from chat) stays blocked: booking is switched off platform-wide.
  BOT-009 is the usage counters on the tab; a dedicated analytics screen waits until there is
  traffic worth charting.
- Wire-in: `ReceiveWebChatMessage`'s live branch consults the bot inside try/catch — a failed
  brain never loses the visitor's message.
- Tests: `BotEngineTest` — 8 green (keyless, thinking+knowledge+tokens, guardrails stop,
  guardrails allow, turn limit, studio-hours silence, model failure, settings render+save).
  Gate: **102 files / 1073 passed / 0 failed** · Pint + Larastan clean.

Gotchas: (1) `BusinessHours::isOpen()` takes a **CarbonImmutable** — `now()` is mutable Carbon;
the TypeError was swallowed by the webchat's own try/catch and looked like "no hand-off";
(2) the keyless provider's `isConfigured()` is true by design ("honest is always available") —
the hand-off branch keys off `name() === 'none' || !isConfigured()`, not isConfigured alone;
(3) spatie settings + `@var` on properties conflicts with the encrypted cast — the BotSettings
docblock records the lesson; (4) `EncryptedStringCast` lives in `App\Settings\Casts`, not
`App\Support`.

## 54. V2-3 frontend v1 — click-to-edit (18 Oct 2026, `frontend-v1` import wave)

Owner directive (18 Oct): the marketing repo (`marketingwoodex-cloud/-marketingwoodex`, branch
`arena/8a776c65-marketingwoodex`) holds "frontend v1": a visual flat-file builder on Hostinger PHP
(builder.js/blocks.js + api/builder.php, P17→P38), the v26 static design (3-slide hero slider,
4-column numbered mega menu) and the P22 flat-PHP admin. Four owner answers set the wave:
(1) **port capabilities into Woodex OS** — no system mount (two CMSs = content drift; builder.php
edits flat HTML the OS does not serve); (2) **frontend-v1 v26 is the final design** — the OS site
matches it, no design changes unless the owner asks; (3) click-to-edit = jump-to-form, not inline
typing; (4) build order: **builder UX first**, then nav/slider/header-footer.

Landed first (F4): **click-to-edit**.
- Migration `2026_10_18_151000`: `page_blocks.block_key` (nullable string 40) + backfill of every
  existing row — a stable per-section identity that survives saves, reorders and restores.
- `SavePage` persists the key (mints one when absent — the restore path sends blocks without keys)
  and snapshots it, so a restored page keeps its sections clickable.
- `site/page.blade.php` wraps every section include in `<div data-wx-key="…">` (CSS-neutral
  wrapper; only when the block has a key).
- `SiteRouter::editingPreview()` — `?preview=1` **and** a signed-in editor with `website.edit`:
  the response gets one injected script (`data-wx-editor-tools`) that outlines the hovered section
  and postMessages `{type:'wx-edit-section', key}` to the parent on click. That request is never
  cached (signed-in user), and guests / share-token previews / plain editor visits never see it.
- PageEditor: `editSectionFromPreview(key)` validates the key against its own blocks (a section
  deleted since the draft was saved is ignored, not guessed at), sets `activeBlock`, dispatches
  `scroll-to-block`; the card gets `id="blk-{key}"`, a brand highlight ring and smooth-scrolls into
  view. Preview iframe: `sandbox="allow-same-origin allow-scripts"` (the tools need scripts);
  parent-side bridge uses `@this.call(...)` behind a `window.__wxClickToEdit` guard so Livewire
  redraws never double-bind the listener.
- `reload()` now hydrates the persisted key (`$block->block_key ?? newKey()`) — without this the
  editor's keys and the preview's keys drifted apart after every save.

Lesson: python `str.replace(count=1)` hit the FIRST `'key' => $this->newKey()` (in `addBlock`),
not the one in `reload()` — the failing assert (random key where the persisted one belonged)
pointed at it. When patching repeated snippets, anchor the replacement on surrounding context.

Tests: `ClickToEditTest` — 4 green (key on public page; editor tools only for previewing editors,
never baked into the cached page; jump + unknown-key ignore + card id; key persistence and minting).
Regression: Website suite 92 passed. Gate: **103 files / 1077 passed / 0 failed** · Pint + Larastan clean.

## 55. V2-3 F1 — the menu tree: dropdowns and the mega panel (18 Oct 2026)

The v26 header rebuilt in Woodex OS: a top link with children opens a dropdown on hover and on
keyboard focus; its **mega switch** (owner answer of 18 Oct) turns the dropdown into the wide
numbered panel — one column per child (01/02/03…), the child's own links under it, and a
"everything →" line back to the hub page. On the phone the drawer folds each parent into a
`<details>` disclosure.

- Migration `2026_10_18_152000`: `navigation_items.is_mega` (bool, default false).
- `Menus::tree($menu)` — recursive (three levels used), cached `website.menu.tree.{menu}` for a
  day; `flush()` clears flat + tree caches. Fallback: the built-in defaults as a flat tree.
  Laravel gotcha: `groupBy('parent_id')` keys null-parent rows under `''`, not null — the tree
  groups under the string `'0'` instead.
- `MenusIndex` rewritten: add with "Sits under" (top / a top link / a dropdown link — a fourth
  level is refused with a toast), remove cascades children (the FK already did), `move()` swaps
  positions **inside the link's own level only**, `toggleMega()` works on top links only. Rows
  render through the new `menus-row` partial, indented by level, with a "＋ add under" action
  that preselects the parent and focuses the form.
- `header.blade.php` rewritten: `group` hover + `group-focus-within` open the panels (keyboard:
  tab into the trigger, the panel opens, children are tabbable), `aria-haspopup`, chevron rotates,
  mega panel is centred and fluid (`min(72rem, 100vw-3rem)`) with a 4-column grid on desktop,
  2 on small screens; mobile drawer keeps the accordions.
- `layouts/site.blade.php` passes `Menus::tree('header')` to the header; the footer stays flat
  until F3.

Tests: `MenusTreeTest` — 7 green (tree shape, cache + dashboard flush, flat fallback, add under
parent + fourth-level refusal, level-scoped move, mega on top links only, site rendering of
dropdown + mega + disclosures). Regression: Website suite 99 passed. Gate: **104 files /
1084 passed / 0 failed** · Pint + Larastan clean.

## 56. V2-3 F2 + F3 — the v26 hero slider and the structured header/footer (18 Oct 2026)

Deep-search follow-up (owner directive of 18 Oct): the old admin v2.1 pro's Website section was
mapped item by item against Woodex OS and the gaps became plan rows F5–F11 in docs/14 §5a (page
templates, shared FAQ/testimonial/logo libraries, team, SEO agent, speed, site health, theme
presets). Owner answers: **central libraries · keyless-first SEO agent · internal speed checks
with PageSpeed behind a key · confirmed order**.

**F2 — the v26 hero slider (reusable section + home conversion).**
- `BlockType::Slider` — one `slides` repeater (photo, small label, heading, text, big display
  word, two buttons). Joined the section library automatically; category Hero.
- `site/sections/slider.blade.php` — Alpine carousel, no library: autoplay 6s that pauses on
  hover and focus and never starts for `prefers-reduced-motion`; arrows, dots and a polite
  counter; arrow-key control; the first photo is the LCP `<img>` with fetchpriority, later ones
  lazy; fade transitions. H1 policy: a real Hero block wins, otherwise the FIRST slide carries
  the page's only H1 (`$hasHeroBlock` computed in the section from the shared `$blocks` scope).
- `PageRenderer`: `hasHero` now also counts a slider **that has at least one photo** (an empty
  slider must leave the page its fallback title), and `mediaFor` collects image ids from
  `slides` rows (`id` or `image_id`) — the gap that made slide photos vanish.
- `woodex:home-slider` (SeedHomeSlider): guarded — skips a home that already has a slider, skips
  slides whose photo is missing, refuses to run without any photo. Existing sections move one
  row down. Seeded the 3 v26 slides (INTERIORS / WORKPLACE / ARCHITECTURE) — the runtime DB now
  has them (home id 144; media ids 106/124/107 were already imported).
- Home H1 note: the home page had NO blocks before this — the seeder was the first content it got.

**F3 — the structured header/footer editor (owner answer: structured, not drag-drop).**
- `SiteSettings` (migration `2026_10_18_153000` in database/settings/): header_cta_label/url,
  social_links (facebook/instagram/linkedin/youtube/tiktok), footer_tagline, footer_show_services
  /cities/contact switches, footer_copyright. Defaults keep today's footer untouched.
- Website Settings screen: three new cards (Header & social / Footer / Tracking) in one
  `display:contents` form; social URLs must be https; the footer card links to Menus for columns.
- `header.blade.php`: the button's text and target come from settings.
- `footer.blade.php` rewrite: brand column (tagline override, NTN, social icon buttons with
  hand-drawn SVG paths), **link columns from Menus::tree('footer')** — a top link with children
  is one column (title = the link, links = its children); plain top links stay in the bottom row,
  so an unfilled menu keeps the old flat footer; services/NAP columns and cities row toggle;
  copyright overridable.
- Layout passes `SiteSettings` to header and footer; footer switched from `items()` to `tree()`.

Gotchas: (1) spatie/laravel-settings requires a SettingsMigration row per new property — adding
class properties with defaults is NOT enough (MissingSettings on save); database/settings/ is not
in sync.sh's copy list — copy those by hand and `artisan migrate`. (2) Blade comments must open
with exactly `{{--` — a stray `{--` leaks the whole comment into the HTML (three files hit this).
(3) The wasm sandbox keeps compiled views across syncs — `rm storage/framework/views/*.php` after
blade edits that tests assert against. (4) Test page cache: after changing settings in a test,
`PageCache::invalidateAll(published)` like the dashboard does.

Tests: `SliderSectionTest` 3, `HeaderFooterEditorTest` 5. Regression: Website suite 107 passed.
Gate: **106 files / 1092 passed / 0 failed** · Pint + Larastan clean.

## 57. V2-3 F5 — page templates (18 Oct 2026)

The old system's page-template contract, kept (docs/14 §5a): **the layout decides which sections a
page opens with and in what order — never the text.** Three flows:

- **Start from one** — Pages shows "N page templates — start from one" above the table; each card
  carries the type, the section count, a description and section chips. `createFromTemplate`
  mints a draft page (unique slug, template name as the title) with the template's sections —
  each with a `block_key`, so the page is click-to-editable from its first save — and jumps
  straight into the editor.
- **Apply to the page being edited** — a Templates card in the page editor lists every template;
  Apply (behind a wire:confirm) replaces the section list in memory only. Nothing reaches the
  database until Save draft, so a template applied by mistake is one click from gone.
- **Save this page's layout as one** — modal in the editor stores the current sections (type +
  data, keys stripped) as a template for the page's type; an empty page is refused.

- Migration `2026_10_18_154000`: `page_templates` (name, page_type, description, is_default,
  sections json, created_by). `PageTemplate::toBlocks()` mints editor blocks and cleans section
  data against `BlockType::fields()` — a template saved before a section gained or lost a field
  still applies cleanly.
- `PageTemplatesSeeder` (in ProductionSeeder, idempotent by name): 8 layouts — Service full/short,
  City, Insight article, Project study, Home — v26, Landing enquiry, About. One default per type.
  Seeded into the preview DB alongside the migration.
- Tests `PageTemplatesTest` — 4 green (seeder validity incl. one-default-per-type + data-fits-type;
  start-from-template with unique slugs; apply replaces in memory without touching the DB;
  save-as-template round-trip + empty-page refusal). Gate: **107 files / 1096 passed / 0 failed**.

Next in the confirmed order: **F6 central FAQ/testimonial/logo libraries** → F8 SEO agent
(+F10 health) → F7 team → F9 speed → F11 theme presets.

## 58. V2-3 F6 — the shared content library (18 Oct 2026)

FAQs, testimonials and client logos are studio assets, not page furniture — one answer edited once
should stand corrected on every page that quotes it (docs/14 §5a, owner's call: central libraries).
The library lives under **Website → Content** with three tabs (FAQs / Testimonials / Client logos);
entries are added, removed and reordered there. The library's order is the order everywhere — a
page picks **members**, never sequence.

- `content_entries` (`kind` faq|testimonial|logo, `data` json in the exact row shape its section
  renders, `position`). `ContentLibrary` service caches id-lists per kind for a day (menus
  pattern) and is flushed by the dashboard screen on every change. `pick()` returns entries in
  library order regardless of the order they were picked in.
- The FAQ, Reviews and Clients sections grew a `library` field (`library_ids`) — a multi-select of
  the kind's entries, next to the section's own rows. Rendering merges **library rows first, then
  the section's own rows**; empty library pick = manual only. Per-page manual rows stay possible.
- The kind decides, not the id: a Reviews section handed a FAQ id simply shows nothing for it.
- `PageRenderer`: FAQPage rich results include library FAQs; the media map resolves library logo
  photos (`mediaFor` follows Clients `library_ids` → entries → `media_id`).
- Tests `ContentLibraryTest` — 4 green (dashboard add/move/validation; FAQ+testimonial merge with
  ordering; logos with photo resolution; editor offers the pick-list). Gate: **108 files / 1100
  passed / 0 failed**. P10SidebarHubsTest sidebar count 37 → 38 (new Content item).

Gotchas recorded: blade component attributes must never carry PHP strings containing quotes —
`:hint="'…it\'s…'"` compiles to broken PHP; build the string in `@php` and pass a plain variable.
Scope docblocks need the concrete class (`Builder<ContentEntry>`), not `<self>`. Livewire
`validate()` throws, so a "return null when not ready" branch is dead code to phpstan.

Next in the confirmed order: **F8 SEO agent keyless-first (+F10 health folded in)** → F7 team →
F9 speed → F11 theme presets.

## 59. V2-3 F8 + F10 — the SEO agent (18 Oct 2026)

Website → **SEO Agent** (docs/14 §5a). Keyless-first, owner's binding call: the deterministic sweep
always runs — no key, no model, every finding a rule a person can re-check; one-click fixes mend
what a rule can mend; AI proposals wake only when the studio's model key exists (the one key, from
Settings → Chat Bot — the BOT pattern) and land only by the owner's click.

- **Page audit** (`SeoAudit`, `app/Domain/Website/Seo/`): missing meta description (fixable —
  written from the page's own first prose), long (>65) and short (<20) titles, duplicate titles,
  slug hygiene (fixable — tidied with an automatic Redirect from the old address), empty image alt
  texts (fixable — filled from the media library's title/alt/file name), thin content (<120 words,
  advice). Worst page first, counts in the header.
- **Website health tab (F10 folded in)**: broken redirects (fixable — removed), dead internal
  links (checked against every page slug AND every concrete route URI — links to /contact-style
  routes never false-positive), orphan pages (published, linked from no menu and no page).
- **AI proposals** (`ProposeSeoCopy`): anthropic/openai/gemini, JSON-only prompt (≤60-char title,
  ≤155-char description), shown as a proposal card — Apply/Discard buttons; nothing is written
  until Apply. Keyless: the card is replaced by an "AI proposals are asleep" note linking to
  Settings. Toast wording mirrors the BOT screens.
- The weekly `woodex:seo-agent` digest (P8.5) is untouched — its three checks are a subset of the
  audit's, and its output shape is load-bearing for the digest notification.
- Tests `SeoAgentTest` — 5 green (rules + keyless card; description/slug+redirect/alt fixes with
  the page dropping off the audit; F10 sweep + redirect removal; proposal → apply roundtrip with
  Http::fake; empty-key stays silent). Gate: **109 files / 1105 passed / 0 failed**. Sidebar count
  38 → 39 (SEO Agent item).

Gotchas: toasts are dispatches (`assertDispatched('toast', message: …)`), not HTML. `audit` is a
render() param — assert against the rendered chips, not `$component->get()`. PageFactory fills
`excerpt` with lorem — tests wanting the description fix must pass `excerpt: ''`. mb_substr
expectations: count your characters. Route::getRoutes() needs `->getRoutes()` before foreach for
Larastan. Livewire array props need `@var array{...}` docblocks; empty default needs optional keys.

Next in the confirmed order: **F7 team manager** → F9 speed dashboard → F11 theme presets.

## 60. V2-3 F7 — the team manager (18 Oct 2026)

The studio's people (docs/14 §5a): members with a photo, a role and a short bio, managed once under
Website → **Team**, and a **Team section** any page's editor adds from the shelf. The library
contract from F6 now spans two tables — `LibraryEntry` (entryId/label/toRow) is implemented by both
`ContentEntry` (FAQs/testimonials/logos) and `TeamMember` (F7's own `team_members` table, per the
spec) — so sections, the editor's pick-lists and the media resolver treat both identically.

- `team_members` (name, role, bio, media_id FK website_media, position). CRUD screen with up/down
  ordering (siblings swap); the studio's order is the order everywhere, like the content library.
- `BlockType::Team`: heading + library pick (kind `team`) + manual repeater (photo/name/role/bio) —
  a guest collaborator can be written on the page without joining the library.
- Section renders member cards (photo, name, role, bio) — library members first in studio order,
  then the page's own rows; **renders nothing when both are empty** (no orphan headings).
- `PageRenderer::mediaFor` follows Team picks → members' photos (Clients/Team share one branch).
- The About template now carries the Team section (seeded templates re-run on the runtime DB).
- Tests `TeamSectionTest` — 4 green (CRUD + order; members with photos then a page-written guest in
  order; empty section renders nothing; editor pick-list + About template carries Team).
  SiteRenderingTest's all-sections fixture grew the Team row. Gate: **110 files / 1109 passed /
  0 failed**. Sidebar count 39 → 40 (Team item).

Gotchas: every exhaustive `match` on BlockType (label/description/category) must gain the new case
— SiteRenderingTest's fixture too. Interface ids: use `entryId()`, never `getIdAttribute()` — that
name is Eloquent's accessor magic for `id` and collides.

Next in the confirmed order: **F9 speed dashboard** → F11 theme presets.

## 61. V2-3 F9 — the speed dashboard (18 Oct 2026)

Website → **Speed** (docs/14 §5a). The owner's binding call, kept: internal checks always run —
keyless, at home, nothing asked of anyone; Google's PageSpeed API behind the studio's own key.

- **Internal audit** (`InternalSpeedAudit`): every published page — image count, total image weight
  (file sizes straight from the disk), images over 300 KB flagged heavy, images not in
  webp/avif/svg flagged old-format, cached HTML KB when the page cache holds it; heaviest page
  first; site totals + cache on/off chip. Files that are gone weigh nothing (the SEO agent's
  health sweep reports missing images — not double-counted here).
- **PageSpeed** (`PageSpeed` service): runs Google's v5 API per page/strategy, extracts score +
  the worded highlights (paint times, total weight, render-blocking, modern formats, unused JS).
  90-second timeout; failures toast plainly. **`pagespeed_runs`** keeps every run — the score is a
  line over time, not one number.
- **The key** (`SpeedSettings`, encrypted cast, saved on the Speed screen itself): empty → the
  score buttons stay hidden and nothing leaves the server; saved → awake; removable. Never shown
  back. Drafts are refused ("Google cannot see drafts").
- Tests `SpeedDashboardTest` — 4 green (keyless weighing incl. heavy + old-format flags with
  Storage::fake; key saved encrypted + never echoed + removable; run against the page's canonical
  URL stored as history with metrics; drafts refused with nothing sent). Gate: **111 files / 1113
  passed / 0 failed**. Sidebar count 40 → 41 (Speed item, new lucide 'gauge' icon).

Gotchas: spatie settings migrations extend `Spatie\LaravelSettings\Migrations\SettingsMigration`
(the Migrations namespace matters — and the file must be copied to the runtime's database/settings
by hand, sync.sh does not). `PageSpeedRun` → default table would be `page_speed_runs`; the
migration says `pagespeed_runs`, so the model pins `$table`. PageFactory's default state IS a
draft — no `draft()` helper.

Wave F now stands at F1–F10 (F5 pages, F6 library, F8+F10 SEO/health, F7 team, F9 speed). One left:
**F11 theme presets**.

## 62. V2-3 F11 — theme presets (18 Oct 2026) — Wave F complete

The Theme Builder grew a **Presets** card above the token groups (docs/14 §5a): three complete looks
beside the studio's own v1 — **Gallery serif** (warm paper, deep ink, serif headings, frames almost
square), **Modern slate** (cool architectural blues, steel accent, softer corners), **Warm walnut**
(walnut browns, brass, room-warm surfaces). Together with "Back to v1 palette" the shelf now holds
the four the plan named.

- `ThemePresets` (static, like ThemeTokensSeeder): each preset is **only the token keys it
  changes** — every other token keeps its value. No code, no files: applying one is the same as
  editing every token by hand, so undo is always one click (resetToV1).
- `applyPreset(name)`: validates against the shelf, writes through the same `normalise()` as a
  hand-edit (hex canonicalised uppercase), flushes the CSS, reloads the editor state. Unknown
  names toast and change nothing.
- Preset swatch strips render the four anchor colours, drawn from the preset data itself.
- Tests `ThemePresetsTest` — 3 green (every preset's keys exist + values well-formed; Warm walnut
  roundtrip incl. the stylesheet carrying the colour and resetToV1 undoing it; unknown names
  refused). Gate: **112 files / 1116 passed / 0 failed**.

Gotchas: `$this->dispatch('toast', 'msg')` positional vs `assertDispatched('toast', message: …)`
named do not match in tests — dispatch with named params (`message:`, `type:`); ThemeBuilder's own
save/reset dispatches were converted to named for consistency. Apostrophes in single-quoted PHP
strings bite every time — double-quote or reword.

### Wave F closed (V2-3)

F1 menus/mega · F2 slider · F3 header/footer editor · F4 click-to-edit · F5 page templates ·
F6 shared content library · F7 team manager · F8+F10 SEO agent + website health · F9 speed
dashboard · F11 theme presets. The v26 static design stayed untouched throughout (owner's rule).
Next per the marathon order: **P5 completion → V2-2 leftovers → V2-5 → P7 → P9**.

## 63. P5 S7 — the alert-rules builder (18 Oct 2026) — P5 complete

The last P5 build step (AUT-003, TGM-002, docs/02 §8): **System → Alert Rules** (settings.manage).
Nothing is pre-seeded — out of the box every alert behaves exactly as before this screen existed.

- **`AlertCatalogue`**: the 22 events the OS raises today (leads, SLAs, tasks, visits, quotes,
  payments, projects, mentions, chats, system reports, WhatsApp), each labelled with the routing it
  ships with. The catalogue drives the screen's dropdown — no staling.
- **`AlertRouter`** — the single door: 24 call sites (every `Notification::send`/`->notify` that is
  a staff alert) now go through `app(AlertRouter::class)->send($event, $defaults, $notification)`.
  No rule for the event → `Notification::send($defaults)` — byte-for-byte yesterday's behaviour.
  Rules → each takes over: recipients (roles + named people; empty = silenced), channels
  (in-app/Telegram/email, filtered to what the person can receive), quiet hours, priority.
- **`RoutedAlert`** (queued wrapper): delegates toArray/toMail/toTelegram to the wrapped
  notification unchanged; `isCritical()` (priority critical OR quiet-hours off) makes the Telegram
  channel deliver into a muted chat at once instead of queueing. Router eager-loads
  `telegramLink` (lazy loading is disabled here).
- Skipped on purpose: StaffInvitation (a personal invite, not an alert), DigestNotification (its
  own quiet-hours machinery, S4).
- Tests `AlertRulesTest` — 6 green (catalogue integrity incl. a recursive scan proving every wired
  event key exists; default routing unchanged; rule takeover with channel filtering; critical
  wrapper; screen add/pause/edit/remove + empty-recipient refusal; 403 for non-managers).
  Gate: **113 files / 1122 passed / 0 failed**. Sidebar count 41 → 42.

Gotchas: `app(AlertRouter)` without `::class` is a *constant reference* in PHP — every call site
needed `app(AlertRouter::class)`. `GLOB_BRACE` does not exist in the wasm PHP build — use
RecursiveDirectoryIterator. Notifications under rules arrive as the `RoutedAlert` wrapper, so
`assertSentTo(X::class)` in old tests would break — none did (default path sends the inner class
unwrapped). Larastan: the base Notification contract has no channel methods — the wrapper guards
with `method_exists` (which phpstan understands) instead of ignores.

**P5 closed**: S1–S8 all done (command bus + 17 commands, callbacks, digests, health completion,
change-requests board, alert rules, hardening). Exit criterion — a full day of approvals from
Telegram — is now mechanically possible; the owner's 1-hour task (staff linking their chats)
remains.

Next per the marathon order: **V2-2 leftovers** → V2-5 → P7 → P9.

## 64. V2-2 closed — the campaign free canvas (18 Oct 2026)

The V2-2 row's two remainders are done: inline text editing shipped as F4's click-to-edit, and the
campaign canvas (D-107, docs/12 §5.3) landed now.

- **The canvas**: a page whose `template` is `canvas` renders `pages.canvas_html` — hand-placed
  HTML, full width, no section chrome, no fallback h1. In the editor, switching to the canvas
  replaces the section library/sections cards with one big HTML area (starter scaffold on first
  switch); the **sections stay untouched underneath**, so switching back restores the page exactly.
  The switch and the HTML are editor state until Save draft, like applying a template. Pages list
  flags canvas pages with a badge; SeoAudit skips its thin-content rule for them (a landing page is
  short by design).
- **The guardrail** (`CanvasHtml::clean`, applied in `SavePage` on save): script blocks, on-event
  attributes and javascript:/data:/vbscript: URLs (including entity/whitespace tricks) never reach
  a visitor. Layout, inline styles, iframes and embeds are the studio's own hand.
- Theme tokens stay linked (harmless) but the canvas is excluded from re-skins by design — its
  layout is hand-placed and cannot be re-flowed.
- Tests `CampaignCanvasTest` — 4 green (raw render incl. dormant section and single h1; the
  sanitiser keeps iframes/styles/good links; editor switch → save → site, and back again; Pages
  list badge). Gate: **114 files / 1126 passed / 0 failed**.

Gotchas: a new column on Page needs `Page::$fillable` — mass assignment drops it silently
otherwise (both the factory and `SavePage`'s fill are mass assignment).

Next per the marathon order: **V2-5 — the conversion itself** (docs/13 parity gate) → P7 → P9.

## 65. V2-5 — the conversion tooling (18 Oct 2026)

The conversion itself (docs/13) is the owner's gated, group-by-group sequence on the live server:
each group needs the parity gate green-or-accepted, the owner's Go, a file deletion on the host, and
a settle week. What this session built is every piece of it that can be built from here:

- **`tools/website/compare.php`** — the parity gate (docs/13 §5, "to be written in V2-1", now
  written). For each published page that still has a v1 file, it renders the CMS page through the
  HTTP kernel and compares against the file: h1, h2 sequence, section count, internal links,
  images (alt + width/height), title, description, canonical, JSON-LD types, robots. Output:
  summary + `tools/website/compare-report.json`. **Decisions**: differences the studio accepts are
  recorded in `tools/website/parity-decisions.json` (`{slug: {check: reason}}`); a page is green
  (zero diffs), accepted (every diff decided) or red — a group with a red page does not switch.
  Computed styles at 375/768/1440 need a real browser and stay a host-side step before each Go.
- **`woodex:conversion-status`** — the eight groups of docs/13 §4 in order, each with published
  pages, v1 files still on disk, CMS-served count and state; honest by construction, because the
  switch is file existence. `--files-root` lets it run against any tree.
- **`App\Domain\Website\Conversion\ParityCompare`** — the gate's engine, a plain class the thin
  script wraps (so it is Pest-tested directly).
- **Baseline run (this sandbox)**: 0/142 green, 1 CMS-only page — the honest starting point. The
  systematic differences are known and mostly host-dependent or deliberate improvements:
  canonical shows `http://localhost/` here (APP_URL on the host is woodex.com.pk); v2 ships a
  richer JSON-LD set (BreadcrumbList, LocalBusiness, WebSite) and explicit `robots` — decisions to
  record on the host, exactly what the decisions file is for.
- Tests `ConversionParityTest` — 3 green (identical renders green; every difference recorded
  without judging; conversion status reads file existence honestly, incl. `--files-root`).
  Gate: **115 files / 1129 passed / 0 failed**.

What remains of V2-5 is per-group, on the host, behind the owner's Go: run the gate per group,
record decisions, fix the reds, delete the group's files, deploy, settle a week (docs/13 §6–7).
Then V2-6 (AI SEO assistant is effectively landed as F8's proposals half; Sveltia bridge stays
deferred by decision).

## 66. P7 — the social planner, keyless-first (18 Oct 2026)

Marketing → **Social Planner** (SOC-005/006/009/010). P7's external half — Meta app review, LinkedIn
access, page tokens, webhooks (SOC-001…004, 007, 008) — is the owner's to start (docs/16 §8, weeks
to approve); the internal half is now whole, so the day the approvals land only the transports
remain.

- **Composer** (`social_posts`: EN + UR captions, link, first comment, hashtags, media_ids from the
  shared website media library — SOC-010 — and targets facebook/instagram/linkedin). The walk:
  draft → in review → approved → scheduled → published/failed; **an editor cannot approve their own
  post** (someone else's eyes are the point). Withdraw returns a post to draft.
- **AI assist** (SOC-009, `ProposeCaptions`): EN + natural-UR caption drafts behind the studio's
  one model key (the BOT pattern) — keyless, the button hides; the studio keeps the pen.
- **Scheduler** (`woodex:social-publish`, every 15 min): due posts hand to `SocialPublisher`.
  `connectedAccounts()` is honestly empty until SOC-001 lands, so due posts **wait** — the command
  says so and nothing is churned into failure by a clock. The transport slots in behind that one
  method.
- The month-grid calendar with drag-to-reschedule ships with the connected accounts (noted on the
  screen); until then the plan lives in the list.
- Tests `SocialPlannerTest` — 5 green (compose + shared media + no-network refusal; review flow
  incl. self-approval refused; schedule → scheduler waits; 403 gate; AI keyless silent + key path).
  Gate: **116 files / 1134 passed / 0 failed**. Sidebar count 42 → 43 (Social Planner went live).

Gotchas: two `Http::fake()` calls in one test do not stack — the first (empty) fake keeps
answering; declare one fake with patterns and let both keyless and keyed paths run against it.
When a component's methods all throw "user() does not exist", the `use InteractsWithAdmin;` line
inside the class body went missing (the import alone is not the trait). In python heredocs, `\$`
is a literal backslash — write plain `$`.

**Owner's P7 homework (the only blocker left):** start the Meta app review and LinkedIn product
access; connect the pages when approved. Then: account store + tokens (SOC-001), inbox/webhooks
(SOC-002/003), Lead Ads (SOC-004), LinkedIn (SOC-007), insights (SOC-008), calendar grid.

Next per the marathon order: **P9** (security review, load pass, restore drill, Urdu templates,
training, final v2.1 sync — docs/16 §9).

## 67. P9's buildable half — privacy tools, shortcuts, cost tracking (18 Oct 2026)

The marathon's last phase. P9 is mostly owner-side drills (security pass, load pass, restore
drill, training videos, final v2.1 sync) — those now have an executable checklist in
**docs/28-P9-HARDENING-RUNBOOK.md**. What could be built, was:

- **SYS-013 privacy tools**: a contact's whole record as one JSON download (`Export data` on the
  contact page, `leads.view`); **anonymise** (eye-slash, `leads.delete`) — the row stays for the
  books but the person leaves it (name → 'Anonymised contact', phones/email/address/notes gone,
  lead messages + activity bodies + chat transcripts → '[removed]', `anonymised_at` set, a
  warning banner stays on the page); and **`woodex:privacy-retention`** daily at 02:30 — lost
  leads with no quotation untouched for 30 days are deleted, non-lead chat transcripts go 12
  months after their last message. `--dry-run` reports without touching.
- **GLB-016 keyboard shortcuts**: `N` new lead (lands on `/leads?new=1`, composer open), `Q`
  new quote (only with `quotes.manage`), `/` opens the search palette, `G then D` the
  dashboard. Alpine component `wxShortcuts` in `resources/js/admin.js`; never fires while
  typing; only the routes the user may use are wired into the DOM.
- **PRJ-009 cost tracking**: `project_expenses` (description, category material/labour/
  transport/other, qty+unit, amount, date, author) + the **Costs** card on the project page —
  the quote's internal cost (honestly blank when the quote carries none: the column defaults
  to 0, all-zero means never set), spent so far, margin (quote − spent) with an over-cost
  warning. `projects.manage` records and removes; `projects.view` sees.
- Tests: PrivacyToolsTest 5, ProjectCostsTest 4, LeadsIndexTest +2. Gate: **118 files /
  1145 passed / 0 failed.**
- Still open, owner-gated: SYS-009b off-site backup destination, SYS-011 API tokens (build
  when an integration needs one), QUO-018/WEB-014 Urdu content, and the drills in docs/28.

Gotchas: two stacked `Http::fake()` in one test — the first keeps answering, declare one with
patterns; Livewire actions need `use InteractsWithAdmin;` inside the class body, the import
alone is not the trait; a multi-line `@php … @endphp` block in a Blade file that also uses
inline `@php(…)` directives corrupts the compiled view downstream (the inline regex goes
greedy across the block) — compute in the component and pass props instead; `assertSee`
HTML-escapes apostrophes, pass `false` to see raw text; `?->` on a never-null relation is a
phpstan error even with `?? 0` — use `->prop ?? 0`; Quote is `number` not `ref`; route name
is `admin.quotes.create`, not `.new`; a query param read in `mount()` persists across
`Livewire::test()` calls within one Pest test — split the assertion into its own `it()`.

**The marathon is now code-complete**: every pending task that can be built without the
owner's accounts/approvals has shipped (CNV-008, AUT, BOT, Wave F, P5, V2-2, V2-5 tooling,
P7 internal, P9 buildable). What remains lives with the owner: Meta/LinkedIn approvals (P7
external), off-site backups + the drills (docs/28), the host-side conversion groups (V2-5),
and the parked owner-gated items (CNV-006, WAP-007, BOT-005, model key/budget).

## 68. The master audit + QA pass (18 Oct 2026)

The owner's ask: analyse the complete project, run the audit plan and QA, and report. Everything
below ran against the live preview (server + seeded demo DB + real browser), not the plan docs.

**Battery — all green:** smoke 64 routes · `browser-check.mjs SUBMIT=1` (charts, sidebar,
wire:navigate roundtrip, builder qty edit 325k→850k→325k, approvals, WhatsApp send, staff PDF,
client portal desktop+phone accept, mobile drawer, phone builder, CTA, contact form no-JS/JS/
SUBMIT, 0 failed same-origin requests, 0 console errors) · `iframe-check.mjs` both cookie
policies · `home-check.mjs` desktop/phone/no-JS · `page-check.mjs` interior-design+about+contact
desktop+phone · `assets-check.sh` · full Pest gate.

**Three real defects found and fixed:**
- **Bell drawer morph race** (dashboard `drawer is not defined` ×2): the bell's children
  referenced the entangled `drawer` as a bare Alpine scope var; during a Livewire morph the
  children's scope initialises before the root `x-data` and the reference throws. Children now
  use `$wire.drawer` (per-component magic, morph-safe); the root keeps the entangle for
  server-side `openDrawer` reactivity. Pattern to reuse everywhere.
- **About page phone overflow** (595px on a 390px screen): `#about-story .ab-stats` in the shared
  site CSS (ID specificity) forces three stat cards even inside its own 560px breakpoint,
  outranking the page's own phone rule — a defect the original v1 site carries too. Fixed in
  `ThemeCss` via a documented FIXES repair layer (the stylesheet every v1 page loads last);
  scrollWidth 595→390. Content-neutral, phone-only, code-side (survives regeneration).
- **Six time-bombed tests** (SequenceEngineTest ×5, TelegramDigestTest ×1): the gate that ran
  after 21:00 PKT failed them because the ENGINE was right (quiet hours held the automated
  sends; the digest's +3h "today" windows rolled past midnight) while the tests assumed a
  daytime run and never pinned their clocks. Both files now pin mid-morning in `beforeEach`
  (the codebase's own `QuoteWhatsAppDeliveryTest` pattern); no engine code changed. The suite
  is green at any wall-clock time now.

**Check-script repairs (tools the release gate reuses):** browser-check reports only same-origin
request failures (external embeds are the sandbox's boundary) and carries the page URL on
pageerror; mobile-open settle 600→1200ms; iframe-check's pipeline assertion expects the P10 301
(`/admin/leads`); home-check's no-JS section matches the live v1 home (CTA/menu/hero); page-check
counts a missing alt ATTRIBUTE (WCAG) instead of penalising the correct decorative `alt=""` —
the 8 flagged images were all correctly-marked decorative (brand mark beside its wordmark inside
an aria-labelled link, hero backgrounds).

**Static/data audits (all clean):** DB `integrity_check` ok, 0 FK violations, 94 tables, 0
orphans across 10 spot-checked relations, journals balance, 143 pages / 143 distinct slugs,
8 roles / 44 permissions / 160 assignments; every admin route gated (route `can:` or in-mount
policy or 301 to a guarded target; private media derives + gates its owner); API endpoints
throttled; security headers layered per surface (admin full set + noindex; OAuth no-referrer +
no-store; HSTS prod-HTTPS; XFO configurable, empty in preview for the embedded demo). Preview
DB's 82 migration rows vs 63 files = pre-squash history, benign — the fresh-install path is the
authoritative check and passes.

**Report:** `docs/29-MASTER-AUDIT-QA-REPORT.md` (executive summary, master frontend/backend/
database audit, the planned checks per area, pending tasks + workflow routes + UI/UX, and the
five-system overview). Remaining owner-gated items unchanged (Meta/LinkedIn, SYS-009b backups +
drills, Turnstile keys, WEB-007b slots, CNV-006 provider, model key/budget, conversion groups,
final v2.1 sync).

## 69. The old-admin Website parity — four screens, old names, the January sheet (19 Oct 2026)

The owner's ask, in their words: the old woodex admin v2.1 pro's Website section still wasn't
in Woodex OS — Integrations, page builder, Pages & builder (All pages · Page builder · Section
library · Header & footer · Hero slides · Redirects), Content (Media · SEO · SEO agent · Speed ·
Site health · Theme). Analysis first, questions second (the owner chose: flat sidebar with the old
names, Page builder opens a new page, Hero slides manage the home slider, import the attached CSV),
then the build.

**Four new screens** (all behind `website.edit`, all Livewire like their siblings):
- **Section library** (`/admin/website/sections`): the saved sections standalone — rename, retire,
  the type's label and blurb. The page builder keeps save/insert; this is the librarian.
- **Hero slides** (`/admin/website/heroes`): the home page's Slider block on its own screen — photo,
  kicker, heading, text, display word, buttons per slide; add/remove/reorder; save draft or publish
  through the same SavePage the builder uses (snapshot, versions, cache, menus flush). Creates the
  slider as the home page's first section when none exists; honest empty state when there is no
  home page. Publishing needs `website.publish`; moves to Won-style stages go through
  MoveLeadToStage's own rules (owner override only for historical fact).
- **SEO** (`/admin/website/seo-overview`): every page's search listing in one table — meta title
  and description with length verdicts (title ~65, description ~165), the source of each
  (set / from the page title / from the excerpt / missing), robots rule, published/draft filters,
  and the two listing fields editable in place (writes the same `seo` array the editor writes,
  invalidates the page cache). The SEO agent keeps its own route and screen.
- **Site health** (`/admin/website/health`): the whole-site findings from the same SeoAudit —
  broken internal links, redirects to nowhere, orphan pages, pages with images missing alt text,
  robots/sitemap routes live — each with a one-line hint and a link to the screen that fixes it.

**Old names restored** (labels only, routes untouched): Pages → **All pages**, Menus → **Header &
footer** (screen title too), SEO & Redirects → **Redirects**, Content → **Content library**,
Theme Builder → **Theme**; plus new items **Page builder** (→ the builder with a new page) and the
four above. One flat Website group, fifteen items, the owner's starred old-admin order.

**Tests:** WebsiteAdminParityTest (7): the sidebar's Website labels item-for-item, each screen's
behaviour (rename/delete, slide add/move/remove/save incl. the publish permission gate and the
no-home empty state, listing edit, health findings with a planted broken redirect), and all four
routes 403 without `website.edit`. P10SidebarHubsTest's live count 43 → 48. Website suite + hubs:
**179 passed (2290 assertions)**. Smoke: **68 pages** (was 64). Browser probe: sidebar complete,
six screens titled right, zero console errors.

**The January 2025 lead sheet** ("Jan 2025 - Lead Mamagment (2).csv", 26 rows): imported through
CaptureLead's historical path (`source=import`, `historicalAt` = the sheet's date — no
notifications, no SLA, no assignment). The sheet's own quirks, handled: the header's "Lead Source,
Status" columns are swapped versus its data rows (col 7 is the status, col 8 is
Furniture/Project/Interior — read what the rows hold); undated rows filed at the sheet's month end
with that said in the record; Won rows and existing clients → stage Won with the sheet's date
(owner override — the historical fact needs no accepted quotation); Hold → On hold; messy phones
through Phone::normalize (one unrecognisable number skipped); companies firstOrCreate and linked.
**21 imported (12 furniture, 7 interior, 2 project; 8 new, 2 on hold, 11 won), 5 skipped with
reasons** (2 duplicate people the system rightly refuses to double-import, 1 unrecognisable phone,
2 rows with neither phone nor email). Verified on /admin/leads in the browser.

Gotchas: `Route::livewire` pages 404 (not 500) when a component's `mount()` hits `firstOrFail` —
mount defensively if the thing might not exist; `x-admin.modal` wants a boolean property name +
`submit=`, not a `:show`; `x-admin.badge` takes `color=` not `tone=`; `PageCache` lives under
`Domain\Website\Services`, not `Support`; the trait `use InteractsWithAdmin;` inside the class body
is separate from the import — the method-missing error appears only when an action runs.


## 19 Oct 2026 — Phase 0 audit accepted; Phase 1 truth & safety closed

The platform audit (`docs/audit/`, nine documents + raw evidence) was accepted with the phase order
audit → safety → builder → cutover → modules → polish → QA → deploy. Phase 1 landed:

- **S-03 fixed.** `CanvasHtml` is now a DOM allow-list, not a regex. Unquoted `onerror`, entity-encoded
  `javascript:`, backtick URLs, `srcdoc`, SVG handlers, forms and `@import` are all neutralised; the
  studio's legitimate section/table/img/iframe/style markup is preserved. Free HTML is owner-only
  through the new `website.raw_html` permission, checked in `SavePage` (server) and hidden in the editor.
- **S-01 partially closed.** 26 admin routes gained route-level permissions; `AdminRouteAccessTest`
  probes all 66 parameterless admin GETs as the support role (7×200, 4×301, 55×403) and signed-out
  (all → login).
- **Q-03/Q-05/Q-04/Q-08 closed; Q-02 ready.** CI workflows are committed locally in `.github/workflows/` (push pending GitHub workflows permission); Pint passes 817 files;
  PHPStan 0 errors; status numbers corrected; builder check ignores aborted beacon requests.
- **B-05 resolved.** The v2.1 reference is `marketingwoodex-cloud/-marketingwoodex@720d817`, branch
  `arena/8a776c65-marketingwoodex` — `frontend-v1/builder/*`, including `templates-v26.js` and the
  per-breakpoint style model. See `docs/audit/09`.

Tests: **1,212 passed / 1 skipped / 0 failed** across 125 files (8,475 assertions). No migrations.
`composer.lock` remains open until the first CI run resolves and prints it.
