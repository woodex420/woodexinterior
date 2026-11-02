# Increment 101 — project costs screens and refreshed native preview

2 November 2026 · branch `arena/7093000c-woodexinterior` · builds on `abad230add5b701a272915693d65890de082fe2c` · draft PR #8

## Delivered source

- **Projects → Vendors & costs**, `/admin/projects/costs`, reuses the existing Hub pattern. Tabs: Project costs, Supplier bills, Expenses and Vendors. Existing Accounts URLs remain unchanged. The 47 existing sidebar destinations remain, with exactly one requested new entry (48 total).
- **Project-local costs** reuse the same Livewire component. Global project selection is query-linkable; the embedded project identity is server-locked. Finance users get a new-cost form choosing unpaid supplier Bill or already-paid Expense. Both call increment100's canonical `RecordProjectCost`, not a new ledger or ad-hoc model writes.
- Form inputs map domain validation back to visible fields. A successful save retains request identity/fields for a delayed retry; only explicit **New project cost** intent rotates the UUID. Kind toggles, close/open and project selection cannot turn a retry into a second posting. Duplicate supplier references have visible feedback.
- Tables paginate linked Bills and paid Expenses independently, with void/reversed/unposted state shown. **Manage supplier bills & payments** opens the existing bill screen scoped to this project. Advanced bills support a whole-bill optional project selector, multiple lines, asset purchases and recoverable supplier tax.
- Bill edit/payment prefills use `Money::toDecimalString()`, not binary floats, including `90071992547409.93`. Posted bill/stale-draft errors are visible. Project-filtered bill cards use the same project scope; Paid this month now sums actual bill-payment amounts within the calendar month, including partial payments, rather than fully paid bill totals or future-month payments.
- Archived project labels remain readable through Bill/Expense relations; new purchases still cannot attach to a deleted project.

## Summary boundaries

`ProjectCostSummary` is read-only and derives figures from existing sources:

- Linked purchase basis = posted, unreversed Bill subtotals + posted, unreversed direct paid expenses. Bill input/recoverable tax is separate. Asset purchases may be part of purchase basis: **this is not P&L/profit**.
- Supplier bills, bill payments, outstanding supplier balances and direct paid costs are separate. Payments do not increase cost recognition. Drafts, voids and reversed sources do not enter linked cost totals.
- Customer context is separately labeled issued invoices / receipts allocated against those invoices / invoice balances. Draft/void customer invoices are excluded. Credit notes are displayed separately, not silently applied to other balances. Unallocated customer receipts are not mislabeled as invoice receipts. Customer cards require `invoices.view` in addition to project/account visibility.
- Legacy `project_expenses` remain separate, showing recorded and currently posted amounts/counts with an explicit unknown-supplier/payment warning. They are not inferred paid, moved, matched, copied or reposted. The old project cost card is labeled **Legacy project cost history** and its difference is explicitly legacy-only, not overall margin.
- Finance managers no longer see a competing legacy entry form. Existing non-finance project-manager legacy entry behavior/actions remain available under the original Project update policy, with an explanatory warning; native permission behavior was not silently removed. Legacy reconciliation and broader ledger cross-model acceptance remain release gates.
- These are canonical-source summaries, not an installed-ledger reconciliation or a promise that every historic payable/receipt is linked. Generic journal reversal/recovery consistency still belongs to the remaining financial acceptance work.

## Verification

- **21 new screen/summary cases /107 assertions** pass: unpaid/paid source entry and replay, explicit new intent, field errors, permission boundaries, hub and project rendering, supplier/customer separation, tax/drafts/partial settlement, reversals, legacy warnings, filtered actual payments (future-month exclusion), and large exact prefills.
- Navigation/role regression group: **83 tests /288 assertions** passed before the final calendar-month bound regression. Full-suite initial failures were exactly the two old fixed 47-destination assertions; updated tests now retain the original 47 and positively verify the added route/group/permission and all four new hub tabs. No assertion-count workaround or skipped tests.
- Final serial exact-source full native run: **1,923 tests /10,830 assertions /217.35s, exit0**, strict warning/skip flags. A focused run overlapped a full run and saw another test's fake-storage quotation files; no application/vendor/TestCase patch was made. Final acceptance runs serially because these fixtures share a fake filesystem directory.
- All **1200** application/test/resource/build/migration/route files in scope match the isolated tested source byte-for-byte.
- Scoped Larastan **0 errors**; Pint passed; `npm run build` passed using the existing lock/dependencies. Existing compiled utilities already cover these views, so build artifacts did not change. No new schema or dependency migration in increment101.
- Real Chromium: **9 new workflow checks**, plus rerun **24 application +4 local cross-site iframe checks**, all passed. Tested project selection/AP-vs-AR/legacy warning, unpaid and paid entry, visible cross-model duplicate rejection, project-scoped canonical payment screen, exact settlement, project-local heading/form behavior, 390px viewport and no uncaught JavaScript errors. Synthetic ledger queries confirm one source journal per cost, full1250050 settlement, separate25075 paid expense, payment UUID presence and no copied legacy cost; foreign keys clean.
- The synthetic DB was restored to its pre-browser demo after browser writes. Browser traffic was restricted to the local preview; application outgoing Laravel HTTP is rejected, mail is array-only, no worker/scheduler is running.
- Prior hosted run41504047552 is confirmed **SUCCESS for abad230 only**. New exact-candidate hosted acceptance is separate.

## Updated preview

The native server was restarted on **0.0.0.0:8080** with the updated isolated application, existing104-migration synthetic SQLite and build. Ordinary preview `artisan migrate` returned **Nothing to migrate**. No production database or hosting operation.

Open `/admin/projects/costs?costProject=1` and choose **PREVIEW-COSTS-01** if needed. The persistent synthetic demo contains an unpaid/part-paid supplier bill, a paid expense and an unposted legacy warning example. Its quote is synthetic/draft; no quote was sent and no financial history was regenerated. Preview credentials are the existing synthetic owner fixture, not production credentials.

## Not claimed

No MySQL concurrent-writer acceptance, actual-install identity/financial audit, private backup/verified restore, staging rehearsal, full release completion, merge, deployment or live-production verification. Whole-document project linking only; mixed-project line allocations remain unimplemented. Chat/Website/Assistant/archive/recovery gates remain in the30-item register. Workbook/import, Sheets, Drive/GitHub backup integrations and provider AI remain deferred. **Deployment last; auto-deploy OFF.**
