# 07 · Security, data and permissions review

This is a **read-only review of the checkout**, not a penetration test. No scanning tool, load test or
credential attack was run. Every claim below is tied to a file or a command result; anything I could
not establish is marked **Not tested**.

Threat model: a single-studio CRM/CMS on shared hosting. Users are the owner, 8 staff roles, and
clients who receive signed links or six-digit codes. There is no multi-tenant boundary.

---

## 1. Authentication

| Control | State | Evidence |
|---|---|---|
| Password hashing | bcrypt via `Hash::make`; `BCRYPT_ROUNDS` configurable | `config/hashing.php`, portal code hash |
| Login throttling | Fortify limiter keyed on email+IP | `FortifyServiceProvider:42` |
| Two-factor | **Mandatory** middleware on every admin route; TOTP + recovery codes | `EnsureTwoFactorIsEnabled`, `OwnerTwoFactorPolicyTest` |
| 2FA rate limit | 5/min per pending login | `FortifyServiceProvider:48` |
| Google sign-in | OAuth with `state` verified by `hash_equals`, PKCE challenge, `no-referrer` on callback, `no-store` | `GoogleOAuthController`, `SecurityHeaders` |
| Session | database driver, `http_only` true, `same_site=lax`, `secure` from env | `config/session.php` |
| Session fixation | Laravel regenerates on login (Fortify) | framework behaviour |
| Installer | Every URL 404s once the site is configured; also requires the one-time setup code | `InstallController:32,48,148` |
| Password reset | Fortify routes, throttled | `bootstrap/app.php:70` |

**Gap (S-02, P2):** recovery-code regeneration, lost-device lockout and owner-recovery procedure are
not covered by a test. The owner is the only role that can grant `users.manage`; if the owner loses
their 2FA device the documented path is shell access, which does not exist on shared hosting. Phase 1
must document and test a break-glass route (e.g. one-time CLI code printed by the installer).

---

## 2. Authorization

- 107 admin routes; **53 carry `Authorize:<permission>` at route level**; the remaining 54 are
  Fortify/OAuth endpoints (expected) or authorize inside `mount()`. Spot checks: Contacts ✓, Tasks ✓,
  Leads via policy ✓. **S-01 remains open**: a full route × role HTTP matrix is a Phase 1 deliverable.
- 8 policies registered in `AppServiceProvider:120–127`; 144 `authorize(` calls.
- Roles: owner 44 permissions, admin 41, sales_manager 27, sales 16, accountant 12, designer_pm 11,
  support 6, editor 3. The editor role has website-only rights — consistent with the design.
- Backend enforcement exists where it matters most: quote approval, publish, payment posting, user
  management. Menu hiding is *additional*, not the only control.
- MCP bridge: bearer token → abilities (`status.read`, `leads.read`, …) + `Gate::authorize('viewAny')`
  and `visibleTo($user)`, so a token cannot widen a user's scope. Read-only controller.

**Gap (S-01):** 54 routes without a route-level permission are *probably* all covered, but "probably"
is not evidence. Until the matrix runs, treat the claim "permissions are enforced everywhere" as
**Observed, not Verified**.

---

## 3. Input, output and injection

| Risk | Control | Verdict |
|---|---|---|
| SQL injection | Eloquent/query builder; 35 raw expressions, all with bound parameters (`whereRaw('… LIKE ?', [$x])`) | **Verified by inspection** of every `whereRaw`/`selectRaw` hit |
| Stored XSS (rich text) | `App\Support\Html` allow-list (`p,br,strong,em,ul,ol,li,h3,h4,a[href]`) for quotes/PDF | Good |
| Stored XSS (HTML block) | `CanvasHtml::clean()` at save **and** render, output raw in `site/sections/html.blade.php` | **Bypassable — S-03 below** |
| Stored XSS (mentions) | body escaped with `e()` before the mention regex | Good |
| Blade escaping | 14 `{!! !!}` sites; reviewed each: icons (static), 2FA SVG (library), PDF (sanitised HTML), mentions (escaped), canvas (cleaned) | Good except S-03 |
| CSRF | `web` group; only `api/*`, `webhooks/*` and `install` excluded, each with its own auth/signature | Good |
| Mass assignment | Form objects/`$fillable`; no `guarded = []` found | Good (spot check) |
| File uploads | Media: `mimes:jpg,jpeg,png,webp,avif`, 5 MB, private disk. Project photos: `jpg,png,webp,heic`, 15 MB. Documents: extension allow-list, 20 MB | Good; **SVG is not accepted** (correct) |
| Path traversal on media | `Media` route-model binding; file read from the record's stored path, not the URL | Good |
| SSRF | PSI/Search Console calls use fixed Google endpoints; no user-supplied URL fetch found | Not tested |
| Open redirect | Redirects are stored by staff and served by `SiteRouter`; no `redirect()->to(request())` pattern found | Good |

### S-03 · The HTML-block cleaner can be bypassed (P1 for the website module)

`CanvasHtml::clean()` removes quoted `on…="…"` attributes and quoted `javascript:` URLs with regexes.
Unquoted attributes, backticks and HTML entities get through. Executed against the sandbox build:

```
<img src=x onerror=alert(1)>                  → unchanged
<svg onload=alert(1)>                         → unchanged
<a href=javascript:alert(1)>x</a>             → unchanged
<a href=`javascript:alert(1)`>x</a>           → unchanged
<a href="java&#115;cript:alert(1)">x</a>      → unchanged (entity is stripped for the check,
                                                which turns it into "javacript:", so the test passes —
                                                but the browser decodes it back to "javascript:")
```

The save and render sites both call the same function, so cleaning twice does not help. The output is
printed raw (`{!! $html !!}`) on public pages and in the builder preview.

**Who can exploit it:** a signed-in user with `website.edit` (editor, admin, owner) or anyone who can
import a page. It is stored XSS against visitors and — because the builder preview is framed in the
admin — potentially against an owner session.

**Fix (Phase 8, small):** replace the regex with a DOM allow-list walk (`DOMDocument`: drop
`script/style/iframe[on*]`, keep an allow-list of tags/attributes, reject `javascript:` after entity
decoding and whitespace normalisation), and add the four payloads above to `CampaignCanvasTest`.
Until fixed, restrict the HTML block to the owner role or disable it.

---

## 4. Webhooks and public endpoints

| Endpoint | Authentication | Replay/abuse control |
|---|---|---|
| `POST /webhooks/whatsapp` | `X-Hub-Signature-256` HMAC with `WHATSAPP_APP_SECRET`, `hash_equals` | Verify token on GET; every item persisted; processed idempotently |
| `POST /webhooks/telegram` | Secret token header, `VerifyTelegramWebhook` | Idempotent event rows; per-actor rate limit |
| `POST /api/public/leads` | Turnstile **or** `X-Woodex-Key` bridge secret (`hash_equals`); CORS restricted to `PUBLIC_LEADS_ALLOWED_ORIGINS` | 5/min + 50/day per IP |
| `POST /api/chat/*` | Public by design (visitor chat) | 20/min, 500/day per IP; 90/min poll |
| Portal codes | Six-digit code, bcrypt hash, 15-minute life, single use | 5/min send, 10/min enter |
| Signed quote/invoice/project links | `signed:relative`, expiring | 10/min on writes |

No endpoint trusts a client-supplied user id. The bridge key is compared with `hash_equals` and is
empty by default (feature off).

---

## 5. Secrets and configuration

- `.env` is **not tracked**; `.env.example` holds names with empty values.
- No key, token or password appears in the repository. The sandbox demo password is in `DemoSeeder`
  and is documented as preview-only; it must not ship to production (installer creates the owner).
- The separate `woodex420/woodex` repository **does commit a `.env`** containing a Supabase anon key,
  a Stripe publishable key and a custom-domain URL. Those are public-client keys, but committing any
  `.env` is a bad pattern; if that project is ever deployed, rotate and move to platform env vars.
- `TRUSTED_PROXIES` is empty by default with a correct warning in the config comment.
- **Gap (Q-01):** no `composer.lock` — an unreviewed dependency update can reach production. This is
  a supply-chain control, not a style issue.
- `composer audit` was **not run** (no `composer` binary in the sandbox). CI should run it.

---

## 6. Data protection and operations

| Topic | State | Gap |
|---|---|---|
| PII inventory | contacts, leads, messages, quote/invoice PDFs, portal codes | No data-classification table in docs; Phase 1 |
| Export / erasure | `ExportContactData`, erasure that keeps financial records auditable | `PrivacyToolsTest` (13 assertions) |
| Retention | `woodex:privacy-retention` daily 02:30 with `--dry-run` | Rules are code, not documented policy |
| Audit trail | `activity_log`, 58 rows, no delete path in the audit screen | Log rotation/retention undefined |
| Backups | DB daily 02:30, full weekly, clean 03:30, monitor 04:00 | **No off-site destination configured** (SYS-009b) — a host failure loses both live data and backups |
| Restore drill | Documented in `docs/06` | **Not executed** in this audit (would need a separate DB); release gate item |
| Database | sqlite in sandbox; MySQL planned | No FK-orphan check on production; sandbox shows 0 orphans |
| Transport | HSTS only when `isProduction() && isSecure()` | HTTPS redirect is **commented out** in `public/.htaccess`; relies on Hostinger's setting — verify on the host |
| Error pages | Branded 404/403/419/500 | Only statuses sampled; content not reviewed |

---

## 7. Privacy of this audit

No customer data was exported. Counts and schema names only. Seed data in the sandbox is synthetic
(`DemoSeeder`), except where a real CSV was imported for testing in earlier phases; this audit did not
read message bodies, phone numbers or addresses from it.

---

## 8. Findings added by this review

| ID | Severity | Finding | Fix |
|---|---|---|---|
| S-01 | P1 | Authorization not proven route-by-route | Phase 1 HTTP matrix |
| S-02 | P2 | No tested break-glass path if the owner loses 2FA | Document + test a recovery route |
| S-03 | P1 (website module) | `CanvasHtml` allow-list bypass → stored XSS via HTML block | DOM-based sanitiser + payload tests; interim: owner-only |
| S-04 | P2 | No off-site backup destination | Configure S3/SFTP target, run a restore drill |
| S-05 | P3 | HTTPS redirect commented out in `public/.htaccess` | Enable at the host or in the rewrite rule |
| S-06 | P2 | No `composer.lock` / dependency audit in CI | Commit lock, run `composer audit` in CI |
| S-07 | P3 | Activity-log retention undefined | Set a retention window, document it |

None of these were fixed in Phase 0. S-03 is the only one I would treat as **release-blocking for the
website module** if the HTML block remains available to non-owner editors before launch.
