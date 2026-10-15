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
