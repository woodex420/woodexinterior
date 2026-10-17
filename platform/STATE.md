# Woodex OS: build state

_Update at the end of every task (docs/03 Part A §7)._

## Resume here (project memory, saved 17 Oct 2026 PKT, after the master pending list P1→P7)

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
