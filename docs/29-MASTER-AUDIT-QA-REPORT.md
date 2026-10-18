# 29 · Master audit + QA — the full WOODEX OS pass

_18 Oct 2026. Scope, in the owner's words: complete project analysis, the audit plan and QA; Executive
summary; Master Frontend, Backend & Database Audit; planned checks — Frontend, Backend, Database,
Security, Business workflows; all pending tasks, workflow routes and UI/UX design; and the module
overview — CRM Sales, Accounts, Projects, Inbox Automation, Marketing Website System._

_Everything below was verified on the live preview build (the running server, the seeded demo database,
the real browser), not read off a plan. Evidence scripts live in `tools/sandbox/` and can be re-run any
time; the browser battery is the same one the release gate uses._

---

## 1 · Executive summary

**The platform is green.** The full battery passed on the live preview:

| Check | Result |
|---|---|
| Smoke — 64 routes (admin, public, portals) | **OK** |
| Browser battery (SUBMIT run — real forms submitted) | **OK** — 0 failed same-origin requests, 0 console errors |
| Third-party-cookie / iframe embedding (both cookie policies) | **OK** |
| Home page — desktop, phone, no-JavaScript | **OK** |
| Public pages — layout, headings, alt text, JSON-LD, links (desktop + phone) | **OK** |
| Assets — every stylesheet/script/font answers | **OK** |
| Full Pest gate — 118 files, 1145 tests | **GREEN after this audit's test fixes** (see §2.4) |
| Database integrity — 94 tables, 2,783 rows | **OK** — 0 FK violations, 0 orphans, journals balance to the paisa |
| Route → permission coverage | **OK** — every admin route gated (route `can:` or in-component policy) |
| Security headers | **OK** — see §3.4 for the layer-by-layer picture |

**Three real defects were found and fixed in this pass** (details in §2):

1. The dashboard bell drawer threw `drawer is not defined` (Alpine/Livewire morph race) on every admin
   dashboard load — fixed in the component (§2.1).
2. The About page overflowed phones by 205px (595px of content on a 390px screen) — a defect the v1
   stylesheet itself carries; fixed through the theme layer, which every page already loads (§2.2).
3. Six tests in two files were **time-bombed** — they only passed when the suite ran before 21:00
   Karachi time. The gate that ran after 21:00 failed them (the engine was right: it was quiet hours;
   the tests were not pinning their clocks). Fixed with the codebase's own pinned-clock pattern (§2.4).

**What remains is owner-side, and nothing in it blocks the code**: Meta/LinkedIn approvals, off-site
backup destination + drill, Turnstile keys, booking-slot configuration, the broadcasting provider
choice, the assistant's model key + budget, and the conversion-group cut-over decisions (§4). The
platform code has no known open defects.

---

## 2 · Master Frontend, Backend & Database Audit

What "audit" meant here: load the real system, click through it as each role, break it where possible,
read the data it produced, and only then read the code that produced it. Findings are listed with their
fixes; everything else in each area passed.

### 2.1 Frontend — the live-chat bell morph race (FOUND + FIXED)

Symptom: two `drawer is not defined` page errors on every `/admin` dashboard load, caught by the
browser battery's console-error rule after the failure was traced to its URL. Root cause: the bell
component's children referenced the entangled `drawer` state as a bare Alpine scope variable
(`x-show="drawer"`), but during a Livewire morph the children's scope can initialise before the root
`x-data` that defines it — the reference throws mid-morph.

Fix (the pattern, worth reusing): inside a Livewire component, Alpine directives should reference
entangled state through the `$wire` magic (`x-show="$wire.drawer"`, `@click="$wire.drawer = false"`)
— `$wire` is injected per-component and is safe mid-morph. The root keeps the entangle for
server-side reactivity. Verified: zero page errors across the full battery, bell drawer opens/closes
through the 40-second poll cycle, and the builder qty-edit flow (325k → 850k → 325k) runs clean.

### 2.2 Frontend — the About page's phone overflow (FOUND + FIXED)

Symptom: `page-check` measured 595px of content on a 390px phone viewport — a horizontal scroll on the
studio's own About page. Root cause, layer by layer: the page's own stylesheet stacks the three stat
cards on phones (`.ab-stats { grid-template-columns: 1fr }` inside its 600px breakpoint) — but the
shared site stylesheet carries an **ID-specificity** rule `#about-story .ab-stats` that forces three
columns, and its own 560px breakpoint keeps **three** columns (`1fr 1fr 1fr`). The ID outranks the
page's class rule at every width. This is a defect the original v1 site carries too — it was imported
faithfully.

Fix: the theme layer (`ThemeCss`) gained a documented FIXES section — a repair layer the v1 pages
already load last — that stacks the band inside 560px. Verified: scrollWidth 595 → **390** on a phone.
Content-neutral, phone-only, and survives regeneration because it lives in code, not in an edited
import file.

### 2.3 Frontend — accessibility of the imported pages (AUDITED, no defect)

The first page-check run flagged 8 images "without alt text" across `/interior-design/`, `/about/`,
`/contact/`. Inspection showed all 8 carry `alt=""` — the **correct** marker for decorative images: the
brand mark sits beside its wordmark inside an `aria-label`ed link (so a screen reader says "Woodex
Interior home" once, not twice), and the hero backgrounds are ambience. The check itself was wrong
(counting empty alt as missing); it now counts only images with no alt attribute at all — the actual
WCAG failure — and reports zero. Every content image on the checked pages carries real alt text,
width/height (no layout shift) and lazy-loading beyond the fold.

### 2.4 Backend — the time-bombed tests (FOUND + FIXED)

The full gate run at 22:07 PKT failed 6 tests in two files, all time-related. Diagnosis with the clock
probe: the engine was behaving **correctly** — it held automated WhatsApp sends during quiet hours
(21:00–09:00, the designed safe-limit) and the daily digest's "today" windows (a visit at now+3h)
legitimately rolled past midnight. The tests were assuming a daytime run and never pinned their clocks
— the codebase's own `QuoteWhatsAppDeliveryTest` already does this correctly.

Fix: `SequenceEngineTest` now pins 11:00 and `TelegramDigestTest` pins 10:00 in `beforeEach`, each with
a comment explaining why. Both files pass at any wall-clock time; the quiet-hours and cap tests pin
their own times explicitly and still exercise the real engine paths. **No engine code was changed —
the holds were correct behaviour.**

### 2.5 Backend — automation engine behaviour (VERIFIED)

Beyond the suite, the quiet-hours hold, the daily per-client cap, the reply-grace window (a client's
message makes any hour a fair hour for 60 minutes), the morning-slot release with its 5-minute buffer,
and pause/resume were all re-verified live. Enrolment exits on client reply and on won/lost leads;
every step is logged per run.

### 2.6 Database — integrity audit (OK)

On the live demo database: `PRAGMA integrity_check` **ok**; `foreign_key_check` **0 violations**; zero
orphaned rows across ten spot-checked relations (leads→stages, quote items, invoice items, payments,
projects, page blocks, activities, journal lines); all 143 CMS pages have distinct slugs; no negative
amounts or quantities anywhere; the ledger's debits equal its credits; 8 roles / 44 permissions / 160
role assignments consistent with the gates. One benign note: the preview database records 82 migration
rows against 63 current files — pre-squash history from earlier in the project; the fresh-install path
(the one that ships) is the authoritative check and passes.

### 2.7 What the browser battery covered (all green, one line each)

Charts render on the dashboard; sidebar hub navigation holds state; wire:navigate round-trips without
reload; the estimate builder's qty edit persists (325k → 850k → 325k); approvals flow; the WhatsApp
send uses the mapped template; staff PDFs freeze correctly; the client portal works desktop + phone
including quote acceptance; the mobile drawer opens and settles; the phone builder is usable; the
marketing CTA lands; the contact form works no-JS, with JS, and end-to-end with a real submit; every
same-origin request succeeds; the console stays clean.

---

## 3 · Planned checks — the QA plan, executed

### 3.1 Frontend checks

| Check | Tool | Result |
|---|---|---|
| 64-route smoke (auth, portals, public, webhooks) | `tools/sandbox/smoke.sh` | **OK** |
| Full browser battery incl. real form submission | `tools/sandbox/browser-check.mjs` (`SUBMIT=1`) | **OK** |
| Iframe embedding + third-party-cookie policies (both ways) | `tools/sandbox/iframe-check.mjs` | **OK** |
| Home page: desktop / phone / no-JS | `tools/sandbox/home-check.mjs` | **OK** |
| Key pages: overflow, one h1, heading scale, alt, lazy, JSON-LD, canonical, 35 internal links | `tools/sandbox/page-check.mjs` | **OK** |
| Assets all resolve, correct MIME, cached sanely | `tools/sandbox/assets-check.sh` | **OK** |
| Zero console errors / page errors / failed same-origin requests | browser battery rules | **OK** (after §2.1) |
| No horizontal scroll at 390px & 1440px | page + home checks | **OK** (after §2.2) |
| Hero + CTA + menu visible with JavaScript off | home-check no-JS section | **OK** |

### 3.2 Backend checks

| Check | Result |
|---|---|
| Full Pest suite, 118 files / 1145 tests / 6,939 assertions (sharded gate) | **GREEN** (after §2.4) |
| Route → permission coverage (§3.2.1) | **OK** |
| Public API endpoints rate-limited (leads, chat, legacy bridge) | **OK** — dedicated throttles |
| Signed, expiring portal links (quotes/invoices/projects) | **OK** |
| Webhook signature + idempotency | **OK** (probed earlier waves; unchanged) |
| Sequences engine safe-limits live behaviour (§2.5) | **OK** |

**3.2.1 Route → permission coverage.** Every route in `routes/admin.php` falls into exactly one
bucket: (a) hub screens behind route-level `can:` (quotes.view, invoices.view, accounts.view,
projects.view, inbox.view, website.edit, settings.manage, users.manage, …); (b) detail screens
authorising through model policies in `mount()` (`LeadShow`, `ContactShow`, `CompanyShow`,
`QuoteBuilder`, `ProjectShow`) or `Gate::authorize` in the controller (quote/invoice PDFs, statements,
data export); (c) 301 redirects to a guarded target (the pre-P10.1 URLs); (d) self-scoped screens
(dashboard, notifications, profile/security). Private media derives its owner model and gates on it.
Nothing admin-facing is ungated.

### 3.3 Database checks

Integrity + FK + orphans + balance + slugs + role matrix: §2.6 — all clean. Number sequences
contiguous; the recycle bin soft-delete path intact; media served through the authorized route.

### 3.4 Security checks

| Layer | Finding |
|---|---|
| Headers — admin | `nosniff`, `Referrer-Policy: strict-origin-when-cross-origin`, `Permissions-Policy` (camera/mic/geo/payment off), `X-Robots-Tag: noindex` on admin, HSTS in production HTTPS, `no-referrer` + `no-store` on OAuth callbacks |
| Headers — public pages | served by the static layer with `nosniff`; marketing pages intentionally indexable, no admin surface |
| X-Frame-Options | configurable (`SECURITY_FRAME_OPTIONS`); empty in the preview so the embedded demo works, `SAMEORIGIN` default in production |
| Sessions/cookies | HttpOnly + secure + SameSite on the app session; CSRF token per session; preview adds `partitioned` for iframe support |
| AuthN/AuthZ | optional owner 2FA, active-user check, role/permission gates per §3.2.1, login/OAuth throttles |
| Input | throttled public endpoints, signed portal URLs, webhook signature verification, idempotent processing |
| Known accepted boundaries (documented, not defects) | Turnstile off until the owner's keys; backups run but no off-site destination yet (SYS-009b) |

### 3.5 Business-workflow checks (run as the roles, in the browser)

Lead capture (public form + chat widget intake) → assignment → estimate builder → quote → approval →
send → client acceptance on the portal → invoicing → payment + verification → ledger posting → project
board → handover checklist: each hop was exercised either in this battery or in the wave gates that
shipped it, and the smoke run touches every screen. The follow-up sequences engine, digests, SLA
escalations and alert rules were re-verified this pass (§2.4–2.5). Nothing regressed.

---

## 4 · Pending tasks, workflow routes, UI/UX — the complete list

### 4.1 Pending — code-side

**Nothing known.** The audit's three code findings are fixed and gated. The backlog below is either
deliberately parked or owner-gated.

### 4.2 Pending — owner decisions / owner actions (the real remaining list)

| Item | What it needs | Impact while pending |
|---|---|---|
| Meta WhatsApp credentials + template approvals | Owner's Meta access; screens already built (Settings → WhatsApp, Templates) | Automated WhatsApp keeps configured fallbacks; quotation link uses the mapped template once approved |
| LinkedIn app for social planner | Owner's LinkedIn account (P7) | Social planner shows connect prompts only |
| SYS-009b — off-site backups + restore drill | Owner's Drive/S3 account; `backup:run` already schedules | Backups run locally only |
| Turnstile keys | Owner account (TUR) | Public forms run without spam protection |
| WEB-007b — booking slots | Owner content: slots per area/day | Booking bridge answers "paused"; BOT-005 waits on it |
| CNV-006 — real-time broadcasting driver | Provider choice (Pusher/Ably/Soketi) | Inbox keeps 5–10s polling (works, just not instant) |
| Assistant model key + budget | Owner picks provider + cap (Claude/OpenAI/Gemini) | Bot answers honestly that a human will reply; team gets pinged |
| WAP-007 broadcasts / CNV-006b marketing sends | Owner decision | Parked by design |
| Conversion-group cut-over (V2-4/5 gates) | Owner content review per group + Go/No-Go | Preview stays the source of truth until cutover |
| Final v2.1 sync + v1 retirement (P9 §6) | Owner, last step after cutover | Documented in docs/28 |

### 4.3 Workflow routes (how work flows today, with the URLs it lives at)

- **Capture**: `/` (home) · `/contact/` (form, no-JS safe) · chat widget (site.js loader) ·
  `/api/public/leads` + legacy bridge `forms.php` (throttled) · WhatsApp inbound webhook.
- **CRM**: `/admin/leads` (List · Pipeline · Tracking) → `/admin/leads/{id}`; contacts & companies
  `/admin/contacts`; tasks & visits `/admin/tasks`; recycle bin `/admin/recycle-bin`.
- **Sales**: `/admin/estimations` → `/admin/quotes` (All · Tracking · Templates) → builder
  `/admin/quotes/{id}` → approvals `/admin/approvals` → send (WhatsApp/email/PDF) → client portal
  `/q/{public-id}` (signed) → invoices `/admin/invoices` → payments `/admin/payments`
  (All · Dues · Receivables) → documents hub `/admin/sales/documents`.
- **Accounts**: `/admin/accounts/chart` · `/admin/accounts/journals` · `/admin/accounts/periods` ·
  `/admin/accounts/methods`; invoices/receipts post to the ledger automatically.
- **Projects**: `/admin/projects` → board `/admin/projects/board` → `/admin/projects/{id}` →
  client portal `/p/{public-id}` (diary, documents, receipts, handover sign-off).
- **Inbox & automation**: `/admin/inbox` (+ header bell drawer everywhere), `/admin/automation`,
  `/admin/automation/sequences`, canned replies `/admin/settings/canned-replies`, templates
  `/admin/integrations/whatsapp-templates`.
- **Marketing website**: `/admin/website/pages` (+ builder `/admin/website/pages/{id}`), media,
  menus, content library, team, redirects, SEO agent `/admin/website/seo`, speed, theme
  `/admin/website/theme`, settings; social planner `/admin/marketing/social`.
- **System**: dashboard `/admin` (Overview · My Day), reports `/admin/reports`, users, settings,
  integrations, health `/admin/system/health`, audit `/admin/system/audit`, change requests,
  data import, updates `/admin/system/updates`.

All pre-hub URLs 301 to their tab (P10.1) — verified by smoke.

### 4.4 UI/UX design — state of the interface

The admin runs the hub design: 36 sidebar entries (down from 49), one page per workflow with tabs
inside, live count badges on Inbox and Approvals, collapsible groups remembered per browser. The
browser battery exercised it at desktop and phone widths, including the mobile drawer and the
phone-width builder. The public site is the v1 design (Plus Jakarta Sans, the navy/paper palette,
45-image home) with the v2 vocabulary available through theme tokens — the phone overflow fix (§2.2)
was the only layout defect found; headings scale correctly at both widths (h1 83.5px → 50.7px on the
checked pages), heroes load without JS, and the no-JS experience shows CTA, menu and hero.

---

## 5 · Overview — the five systems, one paragraph each

**CRM & Sales.** Leads arrive from the website form, the chat widget, WhatsApp and the legacy bridge;
assignment rules route them; the pipeline Kanban and the tracking board (Uncontacted · Due today ·
Overdue · Stale · Snoozed) keep them moving; estimates build into quotations with revisions,
approval flow and frozen PDFs; sending goes out on WhatsApp with the mapped approved template; the
client accepts on a signed portal link; invoices, milestone invoicing, payments with proof upload and
locked verification, receivables with consent-gated reminders, and the funnel/revenue reports close
the loop. Invoices and receipts post to the double-entry ledger automatically.

**Accounts.** A real ERP core: chart of accounts with business-line dimensions, monthly periods with
open/close, immutable balanced journal entries with two-way linked reversals, integer-paisa amounts,
idempotent posting, and the OPEN-period rule. The audit's balance check sums every journal line to
zero.

**Projects.** Board + detail views, milestones, expenses, updates/diary, document and receipt delivery
through the client portal, and the handover checklist the client signs. Portal links are signed or
six-digit code (no client passwords exist anywhere — D-90).

**Inbox & Automation.** One inbox for WhatsApp and website chat: media both directions, internal notes
with @mentions, canned replies with variables, 24-hour window awareness with template fallback, reply
SLA with escalation. The sequences engine walks wait → mapped template → task → stage-move steps on a
per-minute heartbeat with quiet hours (21:00–09:00) and a per-client daily cap — re-verified live this
audit. Telegram is the command centre: 15 commands with inline buttons, morning/weekly/monthly
digests per role, muted-chat queuing, critical bypass. Alert rules, the daily WhatsApp health check
(cost + quality rating), and the automation dashboard with per-flow switches (fail-safe: missing
switch = enabled) round it out.

**Marketing Website System.** The v1 site lives inside the OS: 143 CMS pages (142 imported URLs +
home), drag-reorder blocks, media library, menus, redirects, SEO agent, speed screen, theme tokens
that re-skin the live site on the next load, and the export/import bundle that carries pages and
photographs to the host. The chat widget, forms and the bell drawer connect the site to the inbox.
Parity is gated by `tools/website/compare.php` with the recorded-decisions file; the public battery
(this audit) is the live-side proof.

---

## 6 · How to re-run this audit

```bash
sh tools/sandbox/smoke.sh                          # 64 routes
node tools/sandbox/browser-check.mjs               # SUBMIT=1 to submit real forms
node tools/sandbox/iframe-check.mjs                # both cookie policies
node tools/sandbox/home-check.mjs                  # home: desktop / phone / no-JS
node tools/sandbox/page-check.mjs /interior-design/ /about/ /contact/
sh tools/sandbox/assets-check.sh /
bash tools/sandbox/shard-gate.sh                   # full Pest gate (copy to /tmp/woodex-run first)
```

The checks are quiet on success and name the page, viewport and cause on failure. Today's battery:
smoke OK · browser OK · iframe OK · home OK · page OK · assets OK · gate 118 files / 1145 tests green.
