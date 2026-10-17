# Woodex OS — P8 Master Plan: Complete Business Operating System

**Date:** 17 Oct 2026 · **Status:** APPROVED by owner (six module decisions below). Builds on the
merged agent export. **Companions:** docs/16 (pending list), docs/18 (Sales+Accounts ERP plan),
docs/19 (Documents & Estimation PRD), docs/22 (agent automation architecture), docs/23 (backlog).

## Owner decisions (17 Oct, via ask_user)

1. **GO** on the merged agent export — land, push, deploy.
2. **One legal entity** — Interior and Furniture are business-line dimensions, not separate books.
3. **MySQL for production** after the ledger core (GL) is stable, before 2025 financial staging;
   SQLite keeps running the sandbox preview. ETL script + rollback backup.
4. **Rule-based automation first** — deterministic flows, free, testable; optional LLM layer later
   behind an API-key setting.
5. **Web chat widget now** — agent asks for the number if missing, hands to the team, creates the
   lead; switches to full WhatsApp Cloud API replies when the business number is approved.
6. **Jan-2025 leads imported now**; financial staging waits for the owner's 2025 files
   (invoices, receipts, supplier bills, expenses, outgoing payments, bank statements, balances).

## Workstreams (each ships behind the full gate + owner sign-off)

- **P8.0 Foundation:** deploy the merge → in-dashboard PDF preview (quotes + invoices, no new tab) →
  Jan-2025 lead import (16 contacts + 16 leads expected, 8 held for review) → activate the dormant
  document core (Description Library, Document Designer, Rate Book, Quote Templates) → P5 exit day +
  V2-2 part 2 drawing tools.
- **P8.1 Accounts ERP** (docs/18 batches C→H): chart of accounts, periods, balanced journal
  posting/reversal → receivables + receipts to GL → suppliers/bills/expenses/payments made →
  recurring expense drafts (never auto-pay) → statements/aging/cash-bank/reports → 2025 staging.
- **P8.2 Payments & cash:** due-payment management, advanced notifications (Telegram /due digest +
  consented client reminders), payment accounts from the GL, Telegram /collect /statement.
- **P8.3 Documents & templates (two brands):** brand profiles, quote/invoice/estimation templates,
  description library per brand + section, rate book with pictures per item (furniture quotation
  style), estimation for the four service lines (Interior Design / Fit Out / Renovation /
  Architecture) → one-click convert to quotation.
- **P8.4 Sales tracking:** quotation tracking board (follow-up dates, stale alerts) and invoice
  tracking board (issued → delivered → partial → paid → overdue), client statements.
- **P8.5 Automation & agents:** new-client alert + ask-for-number flow, web chat widget, Telegram
  master control expansion, monthly CRM report on the 1st, SEO/content + social agents (dormant
  until credentials), automation control dashboard (on/off/health per automation).
- **P8.6 Runtime, portal & handover:** MySQL migration, projects board, client portal, handover pack.
- **P9 (tail, unchanged):** security review, load pass, restore drill, Urdu templates, training,
  final v2.1 sync.

## Route hierarchy

```
/admin/sales/…            leads, quotes, invoices, payments (+ tracking boards, PDF preview, dues)
/admin/accounts/          overview · chart · journals · periods · receivables · payables · income ·
                          expenses (+recurring) · payments received/made · cash-bank · reports
/admin/documents/         designs · templates · descriptions · rate-book (two brands)
/admin/estimations/       estimator (4 service lines) → convert to quote
/admin/automation/        agents · workflows · live-chat · monthly report
Telegram: /due /collect /statement /report + the existing 15-command centre
Public site: chat widget + existing forms/estimator
```

## The full funnel (P8 target)

form/WhatsApp/phone → capture (+ ask number if missing) → alert to Telegram → qualify (Kanban, SLA)
→ estimate (service line) → quote (rate book + descriptions + pictures for furniture) → approval →
send (WhatsApp/email/portal, frozen PDF) → track follow-ups → accept (revision-bound) → project →
milestone invoice → deliver → due alerts → payment (proof upload, locked verification, GL posting)
→ receivables/statement → reconciliation → monthly report to the owner's phone.

Nothing auto-posts money, messages a client without consent, or enables an agent without an explicit
owner on-switch — the A0 safety rules (docs/23) stay law throughout P8.
