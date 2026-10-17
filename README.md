# Woodex Interior · Woodex OS

The operating system of Woodex Interior (Lahore): CRM, quotations, invoicing, WhatsApp and Telegram, social media
and the website, built with Laravel 13, Livewire 4, Tailwind 4 and Preline for Hostinger shared hosting.

| Folder | What it is |
|---|---|
| `platform/` | The Laravel application ([platform/README.md](platform/README.md), progress in [platform/STATE.md](platform/STATE.md)) |
| `docs/` | Analysis, master plan, PRD, system prompt, schema, design system, deployment, decisions, import runbook |
| `woodex/` | The earlier static studio site (design reference) |
| `tools/` | `deploy/` (Hostinger Composer hook) and `sandbox/` (AI development sandbox) |

## Deploy on Hostinger (no SSH)

hPanel → the website that owns the target folder → **Advanced → Git** → this repository → **Root directory = the
site folder** (test site: the `interior.woodex.com.pk` folder, not the main `public_html`) → Deploy. Then open the
site: the installer guides through the database and the owner account. Step by step:
[docs/06-DEPLOYMENT-HOSTINGER.md §2](docs/06-DEPLOYMENT-HOSTINGER.md).

The root `.htaccess` serves only `platform/public`; the root `composer.json` installs the PHP packages into
`platform/vendor`; settings and uploads live outside the site folder in `~/woodex-os/<site>/`.
