# Woodex OS (platform)

The Laravel 13 application for Woodex Interior: CRM, quotations, invoices, conversations (WhatsApp,
chat bot, social), Telegram command centre and, from phase P6, the public website with theme and page builders.

**Plans and rules:** [`../docs`](../docs/README.md) · **Progress:** [`STATE.md`](STATE.md) · **AI agents:** [`AGENTS.md`](AGENTS.md)

## Stack

PHP 8.4 · Laravel 13 · Livewire 4 (class components) · Tailwind CSS 4.3 · Preline 5 · TailAdmin layout ·
MySQL 8 / MariaDB (SQLite for tests) · Pest 5 · Vite 8. Runs on Hostinger shared hosting: database queue drained by
cron, no Node.js on the server ([deployment guide](../docs/06-DEPLOYMENT-HOSTINGER.md)).

## Local development

```bash
cd platform
composer install
cp .env.example .env && php artisan key:generate
touch database/database.sqlite
php artisan migrate --seed          # reference data + local demo users (passwords printed once)
npm install && npm run build        # or: npm run dev
php artisan serve                   # http://localhost:8000/admin
```

Run the owner setup instead of the demo seeder on a real server: `php artisan woodex:install`.

## Useful commands

| Command | Purpose |
|---|---|
| `vendor/bin/pest` | Test suite (unit, feature, architecture) |
| `vendor/bin/pint` | Code style (Laravel preset) |
| `vendor/bin/phpstan analyse` | Static analysis (Larastan) |
| `php artisan woodex:install` | Seed reference data and create the owner account |
| `php artisan woodex:telegram:set-webhook` | Register the Telegram bot webhook |
| `php artisan schedule:run` | What the hPanel cron runs every minute |

## Lead capture API

`POST /api/public/leads` (JSON). Required: `name` + `phone` (or `email`). Optional: `service`, `business_line`
(`interior`/`furniture`/`project`), `city`, `area`, `area_sqft`, `budget_band`, `timeline`, `message`, `source`
(`website_form`/`estimator`/`booking`/`website_chat`), UTM fields, `gclid`, `fbclid`, `page_url`, `consent`,
`turnstile_token`, `form_started_at`. Returns `201 {reference, whatsapp_url}` (or `200` when the enquiry joins an
existing open lead). The live site can call it server-to-server with the `X-Woodex-Key` bridge header.
