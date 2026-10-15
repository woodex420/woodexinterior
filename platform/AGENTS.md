# Instructions for AI coding agents (Woodex OS)

You are working on **Woodex OS**. Before changing anything:

1. Read **`../docs/03-MASTER-SYSTEM-PROMPT.md` (Part A)**. It is your system prompt and its rules are binding.
2. Read `../docs/07-DECISIONS.md`, then the phase card you were given (Part B of the system prompt).
3. Check `STATE.md` for what is done, what is next, assumptions and known issues.

Non-negotiables (short version):
- PHP 8.4 / Laravel 13 / Livewire 4 **class** components / Tailwind 4 / Preline 5 / TailAdmin layout.
  ASCII file names only. Never import Alpine on admin pages (Livewire bundles it).
- Hostinger shared hosting: database queue + cron, no daemons, no Node.js on the server.
- Money = integer paisa (`*_minor`), percentages = basis points (`*_bp`), quantities DECIMAL(14,3).
  Totals only via `App\Domain\Sales\Services\QuoteCalculator`.
- Every enquiry goes through `App\Domain\Crm\Actions\CaptureLead`.
- The repository is public: never commit secrets, tokens, staff phone numbers or bank account numbers.
- Tests (Pest) for every business rule. CI must be green. Update `STATE.md` at the end of each task.

Patterns used in the code (follow them):
- Livewire pages: `#[Layout('layouts::admin')]`, `#[Locked] public int $xId` + `#[Computed]` model, `unset($this->x)`
  after writes, `$this->toast()` from `App\Livewire\Concerns\InteractsWithAdmin`, modals via `<x-admin.modal>`,
  form rows via `<x-admin.field>`. Business rules live in `app/Domain/*/Actions`, never in components.
- Visibility: `Lead::visibleTo($user)` / `Contact::visibleTo($user)` for every list or search.
- Settings classes: never `@var` on properties (spatie parses it); use `@phpstan-var` for array shapes.
- Preline JS: import per plugin in `resources/js/admin.js` and add it to `PRELINE_PLUGINS`.
- v2.1 data: extend `App\Domain\System\Import\V21Importer` (idempotent via `legacy_refs`), document in docs/04 §8.
- Reusable Livewire concerns: `BooksSiteVisits` (+ `<x-admin.visit-modal>`) for visit booking with conflict warning,
  `ManagesAttachments` (+ `<x-admin.attachments>`) for files on a record, `HasSavedViews` for list screens.
  Form objects live in `app/Livewire/Forms` (e.g. `CompanyForm` + `<x-admin.company-fields>`).
- Staff notifications: use `App\Notifications\Concerns\SendsToDatabaseAndTelegram` (bell + Telegram when linked),
  return `title`, `body` and `url` from `toArray()`; scheduled work goes in `routes/console.php` (cron-driven).
- Inline SVGs need `width`/`height` attributes as a no-CSS fallback (D-36). New Tailwind classes require
  `npm run build` before checking a page.
- Layout geometry (sidebar width, content offset) comes from the CSS variable `--wx-sidebar-w` and
  `<html data-sidebar>`, never from Alpine `:class` bindings; charts go through `initCharts()` (D-48).
- Public forms must render and read correctly without JavaScript: no fields inside `<template x-if>`, `x-cloak` on
  post-submit panels, static button labels (D-49).
- AI sandbox without native PHP: use `../tools/sandbox/` (README there). UI work is done only when
  `node ../tools/sandbox/browser-check.mjs` and `node ../tools/sandbox/iframe-check.mjs` pass in a real headless
  browser (D-50, D-52). The second one runs the app the way Arena shows it: inside a cross-site iframe, with the
  cookies blocked or dropped.
- Quotations: every change goes through `App\Domain\Sales\Services\QuoteEditor` (or an Action such as `CreateQuote`,
  `DuplicateQuote`), which ends with `RecalculateQuote`. Never write `*_minor` totals anywhere else (D-14, D-54).
  Check the quote's state (`isEditable()`) as well as the permission: the owner passes every permission check.
- Sending quotations (P2 part 2, D-60…D-64): only `SendQuote` sends. A sent version (`quote_versions`) is frozen:
  its snapshot, PDF and SHA-256 are never changed; later edits become the next revision through `ReviseQuote`.
  The client always sees the latest sent version: query versions with `reorder('revision', 'desc')` (the relation
  orders ascending, so `orderByDesc` alone returns the oldest). Approvals go through `RequestQuoteApproval` /
  `DecideQuoteApproval` (the fingerprint in `meta.approval` must match the figures being sent). Client actions only
  through `RecordQuoteView`, `AcceptQuote` and `RequestQuoteChanges`; links only from `QuoteLinks` (relative signed
  URLs). When a collection of users is notified, eager-load `telegramLink` (lazy loading is off).
- Money for people: payable amounts (milestones, later invoices) are whole rupees (`MilestonePlan::split`); words via
  `App\Support\AmountInWords::rupees(Money)`. PDFs come from `QuotePdf` (dompdf, font subsetting on, DejaVu Sans).
- Livewire 4: `wire:model.blur` never sends a request. Fields that save on blur use `wire:model.live.blur`, and
  saving is verified in a real browser, not only with `Livewire::test()->set()` (D-55).
- Never depend on the Referer header (`url()->previous()`) or on the route named `home` (the public website) for an
  admin redirect. Name the admin route instead: failed sign-in → its form, signed-in user on a guest page →
  `admin.dashboard` (D-51).
- Hostinger Git deploy serves the repository as-is (D-56…D-58):
  - `platform/public/build` is committed: run `npm run build` and commit the result with every change to CSS, JS or
    the Tailwind classes in Blade. `tests/Unit/DeployManifestTest.php` checks the build is complete.
  - A package added to `platform/composer.json` `require` goes into the root `composer.json` too (same test).
  - New permissions, number sequences or reference data ship as a **migration** (it may call a seeder): the owner's
    one-click "Database update" (System → Database update, `woodex:update`) runs migrations. Seeders use
    `firstOrCreate` and never overwrite the owner's edits.
  - Never read `.env` or `storage/` paths directly: use `base_path()`/`storage_path()`; on Hostinger they live in
    `~/woodex-os/<site>/` (`App\Support\Install\SiteState`).
  - The installer routes (`routes/install.php`) run without the `web` group: no session, CSRF or cookies there.
  - `node ../tools/sandbox/installer-check.mjs` walks a fresh simulated Hostinger site through install and update.
