# 28 · P9 — hardening + handover runbook

_The studio's operating manual for the last mile: what "safe, documented, trained" means in
practice, who does what, and how each drill is proven. Written 18 Oct 2026 when P9's
sandbox-buildable half landed (SYS-013 privacy tools, GLB-016 keyboard shortcuts, PRJ-009
cost tracking). The rest is owner-on-host work — this file is its checklist._

Legend — **BUILT** shipped and tested in the platform · **OWNER** needs the owner's accounts,
decisions or hands on the server · **DRILL** an exercise to run, not a feature to build.

---

## 1 · Security review (OWNER, one pass each release)

- [ ] Rotate the app key is NOT routine — but confirm `APP_KEY` is set in `.env` on the host and
      backed up with the backups (losing it loses encrypted data).
- [ ] Confirm every `.env` secret is a real production value, not a sandbox/test placeholder:
      `APP_URL=https://woodex.com.pk`, mail, WhatsApp token, Telegram bot token, model key.
- [ ] Run `php artisan woodex:security-review` if present, otherwise walk this list manually:
      admin force-HTTPS, `robots` on admin, session cookie `secure`+`httponly`+`samesite`,
      `register` closed (users invited from Admin → Users only).
- [ ] Users audit: no ex-staff accounts active; every active user has a role; owner accounts
      use real names (audit trail readability).
- [ ] Backups: `woodex:backup` scheduled and completing (Admin → System → Backups shows runs).
- [ ] Permissions spot-check with the tests as the reference: the gate (below) is the proof.

## 2 · Load / performance pass (OWNER, on the host)

- [ ] Enable OPcache + the host's PHP 8.3 defaults; confirm `php artisan optimize` runs in the
      deploy (config + routes + views cached).
- [ ] `php artisan queue:restart` after deploy so queue workers pick up new code.
- [ ] Watch the slowest real pages first: Leads list (biggest table), Pipeline board, Inbox,
      Website preview. If any page feels slow, check `System → Speed` (PageSpeed runs are
      stored) before changing code.
- [ ] The database grows only as fast as the business does; at this scale the indexes shipped
      in the migrations are the pass. Revisit only if a page is measurably slow.

## 3 · Backup restore drill (DRILL — monthly, OWNER; the whole point of backups)

The promise: any day's data back within an hour. Prove it the first Sunday of each month.

1. Download last night's backup archive from the host (Admin → System → Backups, or the
   off-site destination once SYS-009b is wired).
2. On a scratch folder (NOT the live site): create an empty database, import the dump.
3. Point a scratch copy of the `.env` at it, `php artisan key:check` — the app key must be the
   production one.
4. Open the scratch site: log in, open one lead, one quotation, one invoice, one conversation.
   Each must show real data.
5. Record pass/fail and the time it took in the studio's operations log. A failed drill is an
   incident, not a note.
6. **Off-site (SYS-009b, still open):** backups currently land on the host only. The owner's
   Google Drive or S3 account is the missing destination — `config/backup.php` destination
   list + a failure alert through the Alert Rules screen once wired.

## 4 · Urdu templates (OWNER decision + content, mechanics BUILT where possible)

- Client-facing PDFs already carry Urdu where the content is Urdu (quote bodies, portal
  updates). What remains is **content work**: QUO-018 (Urdu terms/heading variant for
  quotations) and WEB-014 (Urdu RTL landing pages) need the studio's own Urdu copy, not code.
- The campaign canvas (V2-2) accepts hand-placed RTL HTML today — an Urdu landing page can be
  pasted and published without waiting for a block-level RTL feature.
- mPDF fallback for dompdf shaping problems: test the first real Urdu quotation PDF before
  promising it to a client.

## 5 · Training (OWNER + studio; videos are the owner's to record)

- The runbook order that worked for the team so far: Leads → Quotations → Inbox → Projects →
  Website. One hour each, on real data, with the keyboard shortcuts shown at the end
  (N new lead, Q new quote, / search, G then D dashboard).
- Record the five hours as screen videos; keep them next to this file.
- The acceptance test: each staff member runs their own workflow unaided while the owner
  watches, once, before v2.1 is retired.

## 6 · Final v2.1 sync + retirement (OWNER, the last step)

1. Freeze v2.1 (read-only) on the conversion date recorded in Admin → Website → Conversion.
2. Run the v1→v2 delta import one last time (docs/08), then `tools/website/compare.php` —
   every page green or accepted-with-reason in `parity-decisions.json`.
3. Switch the last remaining group in the conversion screen; watch a day of real traffic.
4. Keep the v2.1 archive read-only for one quarter, then archive the files offline.
5. Change every external pointer (Google Business, Meta link-in-bio, printed material QRs)
   to the v2 URLs — the v1 redirects carry the rest.

## 7 · What P9 shipped in code (BUILT 18 Oct)

| Item | Where |
|---|---|
| SYS-013 export | Contact page → **Export data** (one person's whole record as JSON) |
| SYS-013 anonymise | Contact page → eye-slash button (needs leads.delete; wipes PII, keeps the row) |
| SYS-013 retention | `woodex:privacy-retention` daily 02:30 — stale lost leads (30d, unquoted) and old non-lead transcripts (12m) |
| GLB-016 shortcuts | `N` `/` `Q` `G→D` in the admin (JS in `resources/js/admin.js`; `/leads?new=1` opens the composer) |
| PRJ-009 costs | Project page → **Costs** card: expenses vs the quote's internal cost, margin |

Still open, owner-gated: SYS-009b off-site backup destination · SYS-011 API tokens (build when
an integration actually needs one) · QUO-018/WEB-014 Urdu content · the drills above.
