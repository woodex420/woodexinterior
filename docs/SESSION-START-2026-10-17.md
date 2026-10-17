# Session start — 17 October 2026

_What is running, what the recap claims, what this repository actually contains, and where to start.
Every statement below is checked against the code at `arena/a2a9b311-woodexinterior` (HEAD `6080d97`), the
GitHub branches, or the running preview — not against the recap._

---

## 1 · The server is running

| | |
|---|---|
| Preview | `https://8080-im9ds2blgrpervzt8c3e0.e2b.app` (sandbox only — not a production deployment) |
| How | `sh tools/sandbox/setup.sh` → php-wasm PHP 8.4 + `resolve.mjs` vendor + `npm run build` + SQLite demo DB; served by `tools/sandbox/server.mjs` |
| Runtime | `/tmp/woodex-run/` (`app/`, `php`, `vendor/`, `database/database.sqlite`, `requests.log`, `shots/`) — rebuilt in ~2 min after a VM reset |
| Sign in | `owner@woodex.test` / `Woodex-Preview-2026` (sandbox demo account) |

Checked while signed in as the owner:

| Path | Result |
|---|---|
| `/` | 200 (131 KB) |
| `/interior-design/` | 200 (119 KB) |
| `/admin` (signed out) | 302 → `/admin/login` |
| `/admin`, `/admin/quotes`, `/admin/integrations`, `/admin/system/audit` | 200 |
| `/admin/description-library`, `/admin/document-design` | **404 — not in this codebase** |

Database in the preview: 70 tables, 26 contacts, 26 leads, 4 demo quotations.

**Caveat:** php-wasm is a faithful PHP 8.4, but it is not Hostinger. No MySQL, no concurrent workers, no
queue daemon. Passing here is necessary, not sufficient.

---

## 2 · Headline finding

The recap describes a large body of work — App Connections, the MCP bridge, Google sign-in, the Description
Library, Document Design, payment/receipt hardening, WhatsApp batching, the January-2025 import and a 744-test
gate — **that is not in this repository and not on GitHub.** It lived only in the closed sandbox; the only copy
is the Drive export (`woodexinterior-all-work-2026-10-17.zip`, 42 MB, SHA-256 `d85094…07d`).

Evidence:

| Check | Result |
|---|---|
| Branches on GitHub | `master`, `arena/8be318a2-woodexinterior` (PR #1, open), `arena/fe1287c9-woodexinterior` (PR #3, open) |
| Tip of PR #3 | `08490e98`, **16 Oct 22:12 UTC** — nothing dated 17 Oct |
| PR #3 size | 33 commits, 1,253 files, +137,234 / −0 |
| `master` | `d77016b`, 10 Sep — the static marketing site only |
| Tests declared in `platform/tests` | **499** `it()` / `test()` cases; `STATE.md`'s last recorded gate is **569 passed / 3,856 assertions** — not 744 / 4,500 |
| `MCP`, `description-library`, `document-design`, `batched`, OAuth/Google sign-in, App Connections hub | **0 references** in `app/`, `routes/`, `resources/` |
| `docs/20` checklist | not in the repository |

Consequence: any new work on those areas would collide with the unpublished branch at merge time, and none of
the recap's numbers can be verified from here. **Recovery is the first task, not a formality.**

---

## 3 · Claim-by-claim compliance

| Claim in the recap | Evidence in this repository | Verdict |
|---|---|---|
| Searchable App Connections hub | `Admin/System/Integrations.php` exists (Telegram/WhatsApp connectors); no hub, no search | **Missing** |
| Read-only MCP bridge with scoped keys | no `mcp` code, no keys/tokens table | **Missing** |
| Google company-identity + existing-staff sign-in | no Socialite/OAuth provider; `google` appears only as a lead source, Maps link and review URL | **Missing** |
| Telegram diagnostics, webhook/sender security, private staff linking | 66 files under `Domain/System/Telegram`, `System/Health`, audit log; tests present | **Present** |
| Owner 2FA enrollment preserving challenges | `OwnerTwoFactorPolicyTest`, `Profile/Security` | **Present** |
| Consent / STOP protection | `CaptureLead`, `SaveContact`, `ReceiveWhatsAppMessage` implement it | **Present** |
| Payment-allocation validation | `PaymentAllocation`, `PaymentsTest`, allocation sums enforced | **Present** |
| Receipt identity + rollback safeguards | receipts flow + numbers in `NumberSequence`; rollback not separately tested | **Mostly present** |
| WhatsApp batched webhook processing, duplicate protection | no `batch` code; single-event webhook path only | **Missing** |
| Quotation editing locks, revision checks, approval-content binding | `QuoteVersion`, `ReviseQuote`, `RequestQuoteApproval` / `DecideQuoteApproval` (fingerprint in `meta.approval`) | **Present** |
| Milestone-invoice uniqueness, invoice numbering | `QuoteMilestone`, `number_sequences`, `NumberingTest` | **Present** |
| January-2025 preview import (16 + 16, 2 dupes, 8 held) | `V21Importer` is idempotent (`legacy_refs`), `ImportV21Test`; no January-2025 dataset or preview mode | **Not verifiable / missing** |
| Description library with section/brand filters | none | **Missing** |
| Description copying into quotes / new rate-book items | `RateBook`, `CatalogItem` exist; no description library to copy from | **Missing** |
| Brand name, logo, accent, header/footer customization | `ThemeTokens` + Theme Builder cover colours/fonts/radii; no logo/header/footer builder | **Partial** |
| In-window quotation/invoice PDF previews | dompdf (`QuotePdf`), `quotes.pdf` / `invoices.pdf`; no in-window preview | **Partial** |
| Frozen document protection | sent `quote_versions` are frozen by design and test | **Present** |
| 744 tests / 4,500 assertions / 1 skip | 499 cases declared; last gate in `STATE.md` is 569 / 3,856 | **Not matched** |
| Preview refresh preserved 66 tables | preview DB here has 70 tables; no refresh test | **Not verifiable** |

---

## 4 · What is actually shipped on this branch

- **P1 CRM** — leads, contacts, companies, pipeline, tasks, visits, SLA, global search, saved views.
- **P2 Quotations** — builder, templates, rate book, approvals, sent-version freezing, client portal, WhatsApp fallback.
- **P3 Invoices & payments** — payments, client proof, receipts, advance invoice from acceptance; reports kit
  (`admin.reports.funnel`, `admin.reports.revenue`, `admin.reports.receivables`, CSV + print).
- **P4 Conversations** — inbox, WhatsApp connection + templates, canned replies.
- **P5 Telegram** — S1/S2 framework and reads, S3 actions/buttons, S5 health, S6 change-requests board; S4/S7/S8 open.
- **P6 Website** — 142 legacy URLs in the CMS, V2-1 theme tokens + Theme Builder, V2-2 part 1 section library/page builder.
- **System** — audit log, recycle bin, users, settings, assignment rules, data import, health, installer, DB update.

Counts: 54 Livewire components, 36 migrations, 70 tables in the preview DB, 142 legacy `index.html` pages.

---

## 5 · Where to start — recommended order

### Step 0 — Recover the unpublished work (blocking)
Get the export into the repository before writing anything in the same areas.

1. Attach `woodexinterior-all-work-2026-10-17.zip` in the chat (Arena writes it into the workspace), or publish
   a link the sandbox can reach.
2. Extract to a scratch dir, verify the SHA-256 manifest, then copy into a branch and **diff against `6080d97`**
   — do not overwrite; the export may be based on a different tip.
3. Restore plan `docs/20` (or whatever the checklist is called) alongside it.

### Step 1 — Baseline gate (do now, in parallel, no collisions)
Run the project's own gate on this branch and record the numbers — this is the reference point for every later
merge:

```sh
cd /tmp/woodex-run/app && /tmp/woodex-run/php vendor/bin/pest --colors=never
cd platform && ./vendor/bin/phpstan analyse && ./vendor/bin/pint --test
npm run build
sh tools/sandbox/smoke.sh
node tools/sandbox/browser-check.mjs
node tools/sandbox/iframe-check.mjs
```

Acceptance: every number written into `STATE.md` with the commit it was measured on.

### Step 2 — Decide the merge path
PR #3 contains the whole application (master still has only the static site). Owner sign-off is required.
If approved: review → merge → deploy per `docs/06` → one-click Database update → verify theme CSS + admin.

### Step 3 — First feature work
Only after Step 0/1, and only in areas the unpublished branch does not already own. Two candidates:

- **Accounting spine** — balanced ledger, journals, periods, expenses, payables, financial reports. Largest
  value, but also the largest scope; it needs a written design (chart of accounts, posting rules, period lock)
  before code. Invoices/payments/receipts already exist, so the data to post against is there.
- **BOQ / furniture specification** — pictures, item-name and dimension/specification columns, BOQ templates,
  saved service estimates, estimate → quotation. Smaller, self-contained, and independent of the unpublished
  work apart from the quote builder.

My recommendation: **BOQ/furniture slice first** (2–3 days, verifiable end-to-end, no accounting design debt),
then the accounting spine as a designed phase. The Description Library and Document Design should wait for the
recovered branch, because the unpublished session already built them.

---

## 6 · Decisions needed from the owner

1. **Export handover** — attach the zip here, or provide a link the sandbox can reach?
2. **PR #3 → master** — review and merge so `master` is no longer the static site, or keep working on branches?
3. **First track** — BOQ/furniture (recommended) or accounting spine?

---

## 7 · Known limits of this environment

- No packagist, no Debian repos, no Docker: PHP comes from php-wasm and dependencies from GitHub via
  `tools/sandbox/resolve.mjs`. Never commit a lock file produced by it.
- No MySQL and no concurrency: native races, queue workers and Hostinger deploy remain unverified here.
- The repository is public — no secrets, tokens, staff numbers or bank details in any commit.
