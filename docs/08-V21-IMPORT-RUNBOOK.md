# 08 · Importing Woodex v2.1 data (runbook)

Moves the CRM data from the old system (marketingwoodex v2.1, MySQL tables `wx_*`) into Woodex OS.
The importer is **one-way**, **read-only on the old database**, **safe to re-run** and supports a **dry run**.
Decisions: D-25, D-32 (docs/07). Field mapping: docs/04-DATABASE-SCHEMA.md §8.

| Imported now (P1) | Imported with their module | Never imported |
|---|---|---|
| Staff users (`wx_users`), clients (`wx_clients`), leads (`wx_leads`), lead notes (`wx_lead_notes`), bookings (`wx_bookings`) | Templates + quotations (P2), invoices + payments (P3), chats (P4), projects (P8) | Passwords, API keys, `_private/*.json` secrets, throttle/cache tables, Telegram/WhatsApp runtime state |

---

## 1. Before you start

1. **Rotate the leaked credentials first** (P-06). Do not copy any old secret into the new `.env`.
2. In hPanel → **Databases**, create a **read-only MySQL user** for the v2.1 database (privilege: `SELECT` only).
3. Add the connection to the new app's `.env` (never commit it):

   ```dotenv
   LEGACY_DB_HOST=localhost        # same Hostinger account → localhost
   LEGACY_DB_PORT=3306
   LEGACY_DB_DATABASE=u123456789_woodex
   LEGACY_DB_USERNAME=u123456789_readonly
   LEGACY_DB_PASSWORD=…
   ```

4. Make sure the new app is installed and seeded (`php artisan woodex:install`), so roles, pipeline stages and
   settings exist. Check **Settings → Sales → Budget bands**: the importer matches old budgets against these.
5. Take a backup of the new database (`php artisan backup:run --only-db`).

You need SSH for the commands below (P-05). Without SSH, add a one-off cron job in hPanel with the same command
and read the output in the report files.

## 2. Dry run

```bash
php artisan woodex:import-v21 --dry-run
```

Everything runs inside a transaction that is rolled back, so **nothing is saved**. The command prints a table:

| Step | Source | Created | Merged | Skipped | Failed |
|---|---|---|---|---|---|
| users | rows in `wx_users` | new staff accounts | matched an existing email | already imported | invalid rows |
| clients | rows in `wx_clients` | new contacts | matched an existing phone/email | already imported | |
| leads / notes / bookings | … | … | | already imported, or note for a lead that was not imported | |

and writes a report to `storage/app/private/imports/<timestamp>-dry-run/`:

- `report.json`: counts per step, totals, time taken
- `warnings.csv`: every row that needs a look (invalid phone, unknown stage, budget that did not match a band,
  assigned user not found, …)
- `duplicates.csv`: v2.1 rows that were merged into an existing contact or user

**Check before continuing:**

- [ ] `Source` counts equal the row counts in phpMyAdmin (`SELECT COUNT(*) FROM wx_leads` etc.).
- [ ] `Failed` is 0, or every failure is understood (for example a staff row with no valid email).
- [ ] Warnings are acceptable. Fix data in v2.1 if needed and repeat the dry run.

## 3. Real run

```bash
php artisan woodex:import-v21
```

- Runs in one transaction: if anything unexpected stops it, **nothing** is saved and you can run it again.
- **Re-running is safe.** Each imported row is recorded in `legacy_refs`, so a second run skips it and only new
  v2.1 rows are added. Keep re-running until cut-over day while the team still works in v2.1.
- Run single steps with `--only`, e.g. `--only=users,clients` (steps run in dependency order:
  users → clients → leads → notes → bookings).
- `--connection=` points at another database connection if needed (default `legacy`).

## 4. After the import

1. **Staff sign-in.** Old passwords are not imported. In **System → Users & roles**, check each person's role, then
   use **Resend invite**: they get an email to set a new password and then turn on two-factor sign-in.
   Unknown v2.1 roles were imported as `support`.
2. **Unassigned leads.** Leads whose v2.1 owner was not imported are unassigned. They appear on the managers'
   My Day under "Unassigned leads".
3. **Duplicates.** Open contacts listed in `duplicates.csv` and use **Possible duplicates → Merge** where needed.
4. **Spot-check** five leads against v2.1: stage, owner, budget, size, next follow-up, notes timeline and visits.
5. **Cut-over day:** stop data entry in v2.1, run the importer one last time, then switch the team over.

## 5. How values are converted

| v2.1 | Woodex OS |
|---|---|
| Lead number `#123` | Reference `V21-00123` (bookings with no enquiry get `V21-B00045`) |
| Stage `new / contacted / visit / quote / hold / won / lost` | New / Contacted / Site visit / Quotation / On hold / Won / Lost (unknown → New + warning) |
| Size `10 marla`, `1 kanal`, `2,500 sq ft`, `100 sqm` | Square feet: 1 marla = 225 sq ft, 1 kanal = 20 marla = 4,500 sq ft |
| Budget `25 lakh`, `1.5 crore`, `Rs 30–40 lakh` | Budget band (upper figure of a range). No match → kept in the lead details + warning |
| Deal value (rupees) | Expected value (stored in paisa) |
| `next_at` / `followup` | Next action (follow-up date without a time → 11:00) |
| Source `facebook`, `instagram`, `tiktok` | Social media (organic), with the network kept as the source detail |
| Source `google`, `portal`, `event`, `outreach` | Google search / Maps, Property portal, Exhibition / event, Outreach |
| Source `architect`, `builder` | Referral, with the detail kept |
| Note kinds `call / whatsapp / visit / meeting / email / note`, `sys = 1` | Timeline entries of the same type; system notes stay system entries |
| Call outcome text ("no answer", "busy", "switched off", "call back") | Call outcome; other text is kept at the top of the note |
| Booking `pending / confirmed / done / cancelled / noshow` | Visit scheduled / confirmed / completed / cancelled / no-show (Asia/Karachi time) |
| Booking type `office` / `online` | Visit address "Woodex studio (office meeting)" / "Online call" |

Imported leads **never start a response-time (SLA) timer** and send **no alerts**. Leads past "new" count as already
contacted. No audit-log entries are written for imported rows.

## 6. Troubleshooting

| Problem | Fix |
|---|---|
| `SQLSTATE[HY000] [2002]` / access denied | Check the `LEGACY_DB_*` values. On the same Hostinger account the host is `localhost`. |
| `Table wx_bookings not found; step skipped` | That v2.1 feature was never used. Nothing to import. |
| Many "Phone … is not a valid number" warnings | The contact is still imported (the original number is kept in its notes). Fix the number in v2.1 or on the contact. |
| The run stops with an error | Nothing was saved (one transaction). Send the error and `warnings.csv` to the developer and re-run after the fix. |
| Time limit on hPanel cron | Run per step with `--only=…`. |

The test suite covers this importer against a v2.1-shaped database (`tests/Feature/ImportV21Test.php`) and the
value conversions (`tests/Unit/V21MapTest.php`).
