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
  `node ../tools/sandbox/browser-check.mjs` passes in a real headless browser (D-50).
