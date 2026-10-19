# 05 · UI / UX gap analysis

Scope: the **admin** interface (the public website's design is final and out of scope, per the
standing instruction). Evidence: the 53-item sidebar (`AdminNavigation.php`), the route table
(`02`), the component tree, and read-only greps of the views. Interaction claims that would need a
real browser session are marked **Not tested** — the browser checks (`tools/sandbox/*.mjs`) cover the
builder and public pages, not every admin screen.

---

## 1. Navigation

### 1.1 Structure

Eleven groups, 53 items, filtered per user by permission; items without a route are rendered as
"planned" with their phase label. The grouping is by job-to-be-done and matches the master plan
(`docs/01-MASTER-PLAN.md` §4).

| Group | # | Items |
|---|---|---|
| Overview | 3 | Dashboard, Approvals, Reports |
| CRM | 3 | Leads, Contacts, Tasks & Visits |
| Sales | 5 | Estimations, Quotations, Invoices, Payments, Documents |
| Accounts | 4 | Chart of Accounts, Journals, Periods, Payment Methods |
| Projects | 4 | Projects, Board, Client Portal *(planned)*, Handovers *(planned)* |
| Inbox | 2 | Conversations, Chat Bot *(planned)* |
| Automation | 4 | Automations, Sequences, WhatsApp Templates, Alert Rules *(planned)* |
| Marketing | 2 | Social Planner, Lead Ads *(planned)* |
| Website | 15 | All pages, Page builder, Section library, Header & footer, Hero slides, Redirects, Media, Content library, Team, SEO, SEO agent, Speed, Site health, Theme, Website Settings |
| System | 11 | Settings, Users & Roles, Lead Assignment, Canned replies, Integrations, Data Import, Recycle Bin, Health, Audit log, Change Requests, Alert Rules |
| **Total** | **53** | 48 live, 5 planned |

Grouping is by job-to-be-done (comment in the source cites `docs/01-MASTER-PLAN.md` §4) and the
Website group deliberately mirrors the v2.1 pro's starred section item for item.

### 1.2 Findings

- **N-01 — duplicate label.** "Alert Rules" appears twice in the sidebar: once under Automation
  (planned, `P5`, no route) and once under System (live, `admin.system.alert-rules`). Same visible
  name. Resolve by renaming one (e.g. "Lead alert rules" vs "System alerts") —
  **owner decision**, because nav labels are preserved unless a change is approved.
- **N-02 — P4b is undefined.** "Client Portal" and "Handovers" carry phase `P4b`; none of the 36 pre-audit documents
  mentions `P4b` (grep over the original `docs/` set returned nothing). P4, P5 and P7 are used by other items, so only the
  `b` variant is orphaned. Planned items render without a link and with the raw phase tag.
- **N-03 — stale plan signal.** The client portal **is built and tested** (signed portal pages, six-digit
  code, quote/invoice/project views). Showing it as future work understates the product. Handover
  *items* exist as an entity; whether the screen is complete is a Phase 1 question.
- **N-07 — Bot has a backend but no screen.** `app/Domain/Bot/` holds five providers, guardrails, a
  reply action and two models; the Chat Bot nav item is still marked planned `P4`. Either the screen
  was dropped or it is configured from Conversations — Phase 1 must establish which, because an
  unreachable feature is a "built but not delivered" risk.
- **N-08 — no search/favourites/pinning.** With 53 items and a long Website group, reaching a screen
  means knowing its group. The master prompt asks for a command palette; there is none. **Recommend**
  a `Ctrl/⌘-K` palette as a Phase 5 (polish) item.
- **N-09 — mobile.** The sidebar collapses to an off-canvas drawer (`components/admin/sidebar.blade.php`
  uses 3 responsive classes; layout uses 4). Groups are long enough that the drawer scrolls; this is
  acceptable but should be re-checked with the real 53 items on a 390 px viewport during Phase 6 QA.

### 1.3 What is good

Active state, permission filtering, section counts, planned items visually distinct, icon per item
(`<x-icon>` closed set — the earlier missing-icon defect is fixed), and no dead links: every item
either has a route or is explicitly labelled planned. That is the "no working link for a nonexistent
page" rule satisfied (verify again in Phase 6 after any nav edit).

---

## 2. Screen patterns

**Consistent primitives** — `x-admin.card`, `stat`, `badge`, `button`, `field`, `modal`,
`page-header`, `pagination`, `empty`, `toasts`, `lead-row`, `quote-list`, `attachments`,
`visit-modal`, `company-fields`, `google-notice`, `pdf-preview`. This is a real design system, not ad
hoc classes; it is the main reason the admin reads as one product.

**Empty states** — `x-admin.empty` used in 40 views, with a title, description and optional actions.
Copy is specific ("Nothing scheduled for today"), not "No data". Good.

**Toasts** — one mechanism (`InteractsWithAdmin::toast()` → `dispatch('toast')` → Alpine store →
`<x-admin.toasts>`), `aria-live="polite"`, dismiss button labelled. 232 call sites; a sample was read,
**not all audited**. Risk: a success toast raised before the write commits. The pattern is
`action()` → `toast('Saved')` inside a DB transaction? — **Phase 1 task**: audit the write path of
every mutating screen for "toast before commit".

**Destructive actions** — the modal component exists; whether every delete/void asks for confirmation
is **Not tested** screen-by-screen. Phase 1 checklist item.

**Loading/disabled** — `wire:loading` usage exists; buttons are not all disabled during submission.
Low priority; double-submit is largely prevented by transactions/unique constraints (verify per
screen).

**Accessibility baseline** — 194 `aria-label`, 30 `aria-hidden`, `aria-selected`/`expanded`/`modal`/
`live` on the interactive primitives. Missing: focus trap in modal (Alpine handles `x-trap`?
**unverified**), skip link, and focus-visible rings on custom buttons. The website builder has an
SEO/a11y checker for *published pages*; the admin itself has no automated a11y check. **Recommend**
axe-core in CI against 8 representative admin screens (Phase 6).

---

## 3. Page Builder — the UX verdict

The builder already has the interaction model the master prompt asks for at the section level:
click-to-edit, drag reorder, layers, undo/redo (25 steps), live preview, section style panel, HTML
block with sanitisation, 50 templates, saved sections, theme presets, draft/publish with an explicit
gate, version history and restore.

What is missing is **granularity and safety**, not basics:

| Gap | User impact | Phase 8 proposal |
|---|---|---|
| No per-breakpoint styles | Mobile/tablet cannot be tuned per section | Add breakpoint tabs to the style panel (data model already stores JSON per block) |
| No nested containers | Cannot build multi-column layouts beyond the preset sections | Add a `columns` section type with 2–4 children, not a free canvas |
| No copy/paste/multi-select | Reworking a page is slow | Clipboard in the layer tree + shift-click range |
| No per-element inspector | Text/button tweaks require re-editing the whole section | Field-level editing inside the section form |
| No conflict detection | Two editors on one page silently overwrite (autosave persists a draft) | Revision check (`updated_at`/version) before save; warn and offer merge/discard |
| Preview persists drafts | "Preview" creates/updates a page before an explicit Save | Save a preview snapshot instead, or label the button "Save & preview" (**Phase 1 decision**) |
| Free canvas / plugin registry | Not needed for this studio's workflow | **Recommend not building**; document the decision |

The last row matters: the brief lists free canvas and a plugin registry, but the studio's content is
section-based marketing pages maintained by one editor. Building a free canvas would duplicate
GrapesJS at maintenance cost with no business gain. Recommendation: **decline free canvas**, keep the
section model, and record the decision in `docs/decisions`.

---

## 4. Public website (read-only assessment)

- 80 exported v1 pages are served as static files and are byte-identical to the approved design
  (`/about/` checked, sha `ee0ac387…`). **Do not change them** (standing instruction).
- 63 further URLs are CMS-served, including all six Lahore locality pages — these are live proof that
  the CMS renderer works in production shape.
- Two sources of truth (W-01) is the single biggest UX risk *for editors*, not visitors: an editor can
  publish a CMS change to `/about/` and see nothing change on the site. Mitigation options are in
  `03` W-01; this needs the owner's conversion order.

---

## 5. Cross-cutting UX defects to verify in Phase 1

| # | Item | Why |
|---|---|---|
| U-01 | Mobile 390 px pass on 12 representative screens (dashboard, leads, quote, project, page editor, media, settings, portal) | Responsiveness is asserted, not evidenced |
| U-02 | Keyboard-only pass: tab order, modal focus trap, Escape to close, drawer focus return | Not tested |
| U-03 | Error states: 404/403/419/500 screens are branded and actionable | Only `/up` and smoke statuses checked |
| U-04 | Long-content behaviour: 200-char company names, 500-row tables, 3-line nav labels | Not tested |
| U-05 | Dark mode contrast on the custom `navy`/`wood` tokens | Theme exists; contrast not measured |
| U-06 | Toast timing vs commit, and no success on failure | 232 call sites, sampled only |
| U-07 | Every "planned" badge maps to a roadmap item with the same ID | N-02 shows one (`P4b`) that does not |

None of these are release blockers on their own; together they are what "professional finish" means,
and they are cheap to close once the functional work is done.

---

## 6. Summary judgement

The admin's **visual and interaction system is coherent and above the usual bar for a custom Laravel
admin**. The gaps are: navigation polish (N-01…N-09), builder granularity/conflict safety, and the
absence of *evidence* for responsive/keyboard/error-state behaviour. Nothing found requires a redesign;
the Phase 5/6 plan should be "prove and polish", not "rebuild".
