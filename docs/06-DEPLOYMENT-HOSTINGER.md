# Woodex OS: Deployment on Hostinger (shared / cloud hosting)

**Version:** 1.0 · **Date:** 15 October 2026 · **Target:** Hostinger hPanel with SSH, LiteSpeed, MySQL, cron
**Rule:** no Node.js on the server, no daemons, no secrets in Git (D-10, D-17, D-22)

---

## 1. What you need (one time)

| Item | Where | Notes |
|---|---|---|
| Plan with **SSH + cron + MySQL** | Hostinger (Business or Cloud recommended) | Confirm P-05 |
| **PHP 8.4** for the site | hPanel → Websites → Dashboard → **PHP Configuration** | Hostinger offers PHP 8.2–8.5 (8.3 is the default). Select 8.4. |
| PHP extensions | hPanel → PHP Configuration → Extensions | `bcmath`, `ctype`, `curl`, `dom`, `fileinfo`, `gd` (or `imagick`), `intl`, `mbstring`, `openssl`, `pdo_mysql`, `tokenizer`, `xml`, `zip`, `exif` |
| Subdomains | hPanel → Domains → Subdomains | `admin.woodex.com.pk` (P0–P5) · `staging.woodex.com.pk` |
| SSL | hPanel → Security → SSL | Free Let's Encrypt for apex + subdomains |
| MySQL databases | hPanel → Databases | `woodex_os` (production) · `woodex_os_staging` · read-only user on the **old v2.1** DB for the importer |
| SSH key | hPanel → Advanced → SSH Access | Port is usually **65002** |
| Email DNS | hPanel → DNS | SPF, DKIM, DMARC for `woodex.com.pk` |

> **Before the first deploy:** rotate every credential that appeared in the old public repository (DB
> passwords, SMTP, WhatsApp token, Telegram bot token, AI keys, Meta app secret). See risk R5 / P-06.

---

## 2. Folder layout on the server

```
/home/uXXXXXXX/
├── apps/
│   └── woodexinterior/                 ← git clone of this repository (outside public_html)
│       └── platform/                   ← Laravel application root
│           ├── .env                    ← created on the server only (chmod 600)
│           ├── public/                 ← the ONLY web-visible folder
│           ├── storage/  bootstrap/cache/   ← writable (775)
│           └── vendor/                 ← composer install (on server) or uploaded
└── domains/woodex.com.pk/public_html/
    ├── (current live site stays here until the P6 website cut-over)
    └── admin  →  symlink to /home/uXXXXXXX/apps/woodexinterior/platform/public
```

If hPanel created the subdomain in its own folder (for example `domains/admin.woodex.com.pk/public_html`),
replace **that** folder with the symlink instead.

**If symlinks are not allowed:** copy the contents of `platform/public/` into the subdomain folder, then edit
the copied `index.php` so its two `require` paths point to the app:

```php
require __DIR__.'/../../../apps/woodexinterior/platform/vendor/autoload.php';
$app = require_once __DIR__.'/../../../apps/woodexinterior/platform/bootstrap/app.php';
```

Then set `APP_PUBLIC_PATH` in `.env` so asset paths resolve (see `bootstrap/app.php`). Adjust the number of
`../` segments to match your folder depth.

---

## 3. First installation (SSH)

```bash
# 1) Get the code
mkdir -p ~/apps && cd ~/apps
git clone https://github.com/woodex420/woodexinterior.git
cd woodexinterior/platform
git checkout <release-tag-or-branch>

# 2) PHP version check (CLI can differ from the website version)
php -v                       # must say 8.4.x; otherwise use: /opt/alt/php84/usr/bin/php
composer -V                  # Hostinger ships Composer 2

# 3) Dependencies (production only; uses composer.lock, light on memory)
COMPOSER_MEMORY_LIMIT=-1 composer install --no-dev --optimize-autoloader --no-interaction

# 4) Environment
cp .env.example .env && chmod 600 .env
nano .env                    # fill in the values from section 4
php artisan key:generate

# 5) Front-end assets (built in GitHub Actions or locally, never on the server)
#    Upload the built folder to platform/public/build/ (rsync, scp or File Manager zip upload).

# 6) Database + storage
php artisan migrate --force
php artisan db:seed --class=Database\\Seeders\\ProductionSeeder --force
php artisan storage:link
php artisan woodex:install    # creates the owner account (asks for name, email, password) and checks the setup

# 7) Optimise
php artisan optimize          # config, routes, views, events cache
```

Permissions: `chmod -R 775 storage bootstrap/cache`. No file in the repository needs 777.

---

## 4. Production `.env` (values are examples; never commit this file)

```dotenv
APP_NAME="Woodex OS"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://admin.woodex.com.pk
APP_TIMEZONE=Asia/Karachi
ADMIN_DOMAIN=admin.woodex.com.pk
ADMIN_PATH=
PUBLIC_SITE_URL=https://woodex.com.pk

LOG_CHANNEL=daily
LOG_LEVEL=warning

DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=uXXXX_woodex_os
DB_USERNAME=uXXXX_woodex
DB_PASSWORD=********

# Read-only connection to the old v2.1 database (importer only; remove after cut-over)
LEGACY_DB_HOST=localhost
LEGACY_DB_DATABASE=uXXXX_woodex_v21
LEGACY_DB_USERNAME=uXXXX_v21_ro
LEGACY_DB_PASSWORD=********

SESSION_DRIVER=database
SESSION_SECURE_COOKIE=true
SESSION_DOMAIN=admin.woodex.com.pk
QUEUE_CONNECTION=database
CACHE_STORE=database
BROADCAST_CONNECTION=log        # switch to "pusher" when the Pusher/Ably keys are added (P4)

MAIL_MAILER=smtp
MAIL_HOST=smtp.hostinger.com
MAIL_PORT=465
MAIL_SCHEME=smtps
MAIL_USERNAME=noreply@woodex.com.pk
MAIL_PASSWORD=********
MAIL_FROM_ADDRESS=noreply@woodex.com.pk
MAIL_FROM_NAME="Woodex Interior"

PUBLIC_LEADS_ALLOWED_ORIGINS=https://woodex.com.pk,https://www.woodex.com.pk
TURNSTILE_SITE_KEY=
TURNSTILE_SECRET_KEY=
TELEGRAM_BOT_TOKEN=
TELEGRAM_WEBHOOK_SECRET=       # long random string; also used in the webhook URL path
```

Integration keys (WhatsApp, Meta, LinkedIn, AI, Pusher) can also be entered later in
**System → Integrations**, where they are stored encrypted. Either place works; `.env` wins if both are set.

---

## 5. Cron (the heartbeat of the system)

hPanel → Advanced → **Cron Jobs** → "Custom", every minute (`* * * * *`):

```
/usr/bin/php /home/uXXXXXXX/apps/woodexinterior/platform/artisan schedule:run >> /dev/null 2>&1
```

Use the PHP 8.4 binary path if `/usr/bin/php` is a different version (for example `/opt/alt/php84/usr/bin/php`).

The scheduler (`platform/routes/console.php`) then runs:

| Task | Frequency | Purpose |
|---|---|---|
| `queue:work --stop-when-empty --max-time=50 --tries=3` | every minute (no overlap) | Sends WhatsApp/Telegram/email, builds PDFs, syncs |
| `woodex:heartbeat` | every minute | Writes the cron heartbeat. The health screen and Telegram alert if it stops. |
| `woodex:sla-check` | every minute | SLA breaches → escalations (P1) |
| `automations:tick` | every minute | Sequences, reminders (P4) |
| `backup:run --only-db` | daily 02:30 | DB backup (P0) |
| `backup:run` | weekly Sun 03:00 | DB + files |
| `backup:clean` + `backup:monitor` | daily | Retention + Telegram alert on failure |
| `model:prune`, `queue:prune-failed --hours=720`, log cleanup | daily | Keep the disk small |
| `woodex:digest daily` | 09:00 | Telegram digest (P5) |

Critical alerts (new lead) are **not** delayed by cron. They are sent right after the HTTP response.

---

## 6. Updating (every release)

```bash
cd ~/apps/woodexinterior/platform
php artisan backup:run --only-db            # safety copy before migrating
php artisan down --secret="<random>"        # optional; maintenance with bypass URL
git fetch --tags && git checkout <new-tag>
COMPOSER_MEMORY_LIMIT=-1 composer install --no-dev --optimize-autoloader --no-interaction
# upload the new public/build (from the GitHub Actions artifact or local build)
php artisan migrate --force
php artisan optimize
php artisan up
```

**Rollback:** `git checkout <previous-tag>` → `composer install …` → `php artisan optimize`. Migrations are
written forward-only; when a migration must be undone, restore the pre-deploy DB backup (step 1).

### 6.1 Optional: one-click deploy from GitHub Actions

`docs/ci/deploy-hostinger.yml`, activated by copying it to `.github/workflows/` (see `docs/ci/README.md`).
It runs from the manual "Run workflow" button:

1. Builds the assets (`npm ci && npm run build`).
2. Uploads `platform/public/build` with rsync over SSH.
3. Runs the update commands from §6 over SSH.

Repository secrets needed: `HOSTINGER_HOST`, `HOSTINGER_PORT` (65002), `HOSTINGER_USER`, `HOSTINGER_SSH_KEY`
(private key whose public part is added in hPanel), `HOSTINGER_APP_PATH`
(`/home/uXXXXXXX/apps/woodexinterior/platform`). Nothing runs until these secrets exist.

### 6.2 File Manager fallback (no SSH)

Possible but not recommended. Upload a release zip from GitHub Actions (with `vendor/` and `public/build/`),
extract it to `apps/woodexinterior/platform`, then run migrations from a protected one-time route
(`/admin/system/maintenance`, owner only, 2FA). Use SSH whenever possible.

---

## 7. Web server notes (LiteSpeed)

- `platform/public/.htaccess` = Laravel default + HTTPS redirect + security headers + `Options -Indexes`.
- Admin subdomain: **no page cache**, `X-Robots-Tag: noindex, nofollow`.
- Public website (P6): LiteSpeed cache for GET pages without session cookies. Cache purged by tag on publish.
  Public forms and chat use stateless JSON endpoints, so cached pages never contain CSRF tokens.
- Upload limits (PHP Configuration): `upload_max_filesize = 32M`, `post_max_size = 40M`, `max_execution_time = 60`,
  `memory_limit = 512M` (PDF generation).

---

## 8. Webhook URLs to register

| Service | URL | Verification |
|---|---|---|
| WhatsApp Cloud API | `https://admin.woodex.com.pk/webhooks/whatsapp` | Verify token (GET) + `X-Hub-Signature-256` |
| Meta (Messenger, IG, Lead Ads) | `https://admin.woodex.com.pk/webhooks/meta` | Verify token + signature |
| Telegram | `https://admin.woodex.com.pk/webhooks/telegram/{TELEGRAM_WEBHOOK_SECRET}` | `X-Telegram-Bot-Api-Secret-Token` header |
| LinkedIn | `https://admin.woodex.com.pk/webhooks/linkedin` | Challenge + HMAC signature |
| Live-site bridge (P1) | `POST https://admin.woodex.com.pk/api/public/leads` | CORS allow-list + Turnstile |

`php artisan woodex:telegram:set-webhook` registers the Telegram webhook automatically.

---

## 9. Go-live checklist

- [ ] All old credentials rotated (P-06). The new values exist only in `.env` / Settings.
- [ ] `APP_DEBUG=false`, `APP_ENV=production`, HTTPS forced, `SESSION_SECURE_COOKIE=true`
- [ ] Owner + admin accounts have **2FA** enabled. Default/demo users do not exist.
- [ ] Cron heartbeat is green on **System → Health**. A queued test job ran.
- [ ] Test email received (SPF/DKIM pass). Telegram test message received.
- [ ] Backup ran and was downloaded once. **Restore drill** done on staging.
- [ ] Error pages (404/500/503) show the Woodex design. Logs rotate daily.
- [ ] Admin subdomain sends `noindex`. robots.txt is correct for each domain.
- [ ] v2.1 import: final run report has equal checksums. v2.1 is set to read-only.
- [ ] (P6) 142-URL parity test green on production. Sitemap submitted. 404 log watched for 14 days.

---

## 10. Troubleshooting

| Symptom | Fix |
|---|---|
| HTTP 500 right after deploy | `storage/` and `bootstrap/cache/` not writable → `chmod -R 775`. Check `storage/logs/laravel-*.log`. |
| "No application encryption key" | `php artisan key:generate` (only once; keep the key safe; changing it breaks encrypted settings) |
| CLI uses another PHP version | Use the full binary path (`/opt/alt/php84/usr/bin/php`) in SSH and cron |
| `composer install` killed (memory) | `COMPOSER_MEMORY_LIMIT=-1`. Always install from `composer.lock` (never `composer update` on the server). |
| Styles missing | `public/build/manifest.json` not uploaded → upload the build folder from CI |
| Queue not processing | Cron missing or wrong path → check the heartbeat; run `php artisan schedule:run` manually and read the output |
| Symlink 403 | LiteSpeed needs "SymLinksIfOwnerMatch". Owner of the symlink and target must match; otherwise use the copy method in §2. |
