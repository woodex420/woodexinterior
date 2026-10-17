# Woodex OS — P10 Master Plan: Admin Shell, Live Chat & Frontend v1 Completion

_Owner decisions 17 Oct (via ask_user): admin first, then frontend · sidebar = global defaults + personal
overrides · live chat = icon + toast, never auto-open · frontend = full conversion, group by group.
Everything the previous phases left pending (P8.3 two-brand documents tail, P8.6 MySQL/portal/handover, P9
hardening) is **on hold by the owner's instruction** until P10's waves are shipped or re-ordered._

---

## Owner decisions (17 Oct, via ask_user)

| # | Decision | What it means here |
|---|---|---|
| **P10-A** | **Admin first, then frontend.** | Wave 1–2 (sidebar + live chat) ship before any builder work; the frontend program (Wave 3+) is planned now, built after. |
| **P10-B** | **Sidebar: consolidate, don't customize.** (Corrected by the owner, 17 Oct: "too many pages — merge workflows, don't develop something".) Related pages merge into single workflow pages with tabs inside (e.g. Quotations + Quote Tracking + Quote Templates; Description Library + Document Design). No per-user preferences, no setup screen — the list shrinks because the pages merged. |
| **P10-C** | **Live chat: header icon + toast + sound on every admin page; nothing ever pops open by itself.** | A runtime presence for web_chat: unread count in the header (icon), toast + optional sound when a new visitor message arrives, one click opens the chat drawer. The owner chose the least intrusive option — "auto open" is explicitly rejected. |
| **P10-D** | **Frontend v1: full conversion, group by group.** | The builder becomes the single source of truth. Money pages (home, services, cities) convert first; the long tail follows group by group behind the existing URL-parity gates (docs/13). Static files are retired only when their group passes parity. |

Reference studied: TailAdmin SaaS demo (grouped menu, tabbed cards, linked views) — inspiration for
interaction polish only. The studio's own design language (navy/paper, Preline + Tailwind v4 components,
`x-admin.*`) stays; **no template swap**.

---

## Wave 1 — Sidebar consolidation + lead tracking (P10.1) — CONFIRMED 17 Oct (ask_user, 2 rounds)

**Goal:** the sidebar shows too many pages. Each group becomes a small set of **workflow pages**,
each holding its related screens as **tabs inside the page** (TailAdmin pattern), in working
sequence. **No new features are built** — the existing Livewire screens are mounted as tabs inside
a container page; the old URLs redirect to the right tab so bookmarks, notifications and the
Telegram buttons keep working.

Owner's examples (17 Oct): *Quotations + Quote Tracking + Quote Templates → one page* ·
*Description Library + Document Design → one page*. The rest of the merge map follows the same
logic and is confirmed with the owner before building (§ below).

1. **Container pattern, once:** one `x-admin.tabs` component + one wrapper Livewire component per
   merged page. Tab state lives in the URL (`?tab=`), so tabs are linkable, deep-linkable and
   back-button safe. Old routes become 301s to the new page + tab. Nothing about the screens
   themselves changes.
2. **The confirmed merge map** (17 Oct, ask_user round 2 — Overview ✓ proposed, Sales ✓ proposed,
   CRM ✓ with a tracking system, rest ✓ keep unchanged). Old URLs always redirect to the right tab:

   | Group | Before | After | The workflow pages |
   |---|---|---|---|
   | **Overview** (+Reports) | 3 + 2 | 3 | **Dashboard** (Overview · My Day tabs) · **Approvals** (own entry — action queue + badge) · **Reports** (Sales Funnel · Revenue & Collections) — the old Reports group folds in |
   | **CRM** | 6 | 3 | **Leads** (List · Pipeline · **Tracking**) · **Contacts** (People · Companies) · **Tasks & Visits** (Tasks · Site Visits) |
   | **Sales** | 12 | 5 | **Estimations** (entry point) · **Quotations** (All · Tracking · Templates) · **Invoices** (All · Tracking) · **Payments** (All · Dues · Receivables) · **Documents** (Descriptions · Document Design · Rate Book) |
   | **Accounts** | 4 | 4 | unchanged (owner decision) |
   | **Projects** | 2 | 2 | unchanged (owner decision) |
   | **Inbox / Automation / Marketing** | 3 | 3 | unchanged |
   | **Website** | 6 | 6 | unchanged (owner decision) |
   | **System** | 11 | 11 | unchanged (owner decision) |

   Totals: **49 live entries → 37**, **11 groups → 10** (Reports folds into Overview). Sales is
   ordered by the studio's working sequence: estimate → quote → invoice → collect, with the
   documents cluster at the end.

2b. **Lead tracking board (new, inside the Leads page's Tracking tab).** The owner: "CRM is simple,
   need improve complete CRM tracking system — simple Leads need tracking system." Mirrors the P8.4
   quote tracking board so the team learns one pattern. Built on existing columns
   (`next_action_at`, `next_action_type`, `last_activity_at`, `first_response_at`, `sla_due_at`):

   - **Groups (owner-confirmed):** Uncontacted (no first reply) · Due today · Overdue · Stale
     (no activity 7+ days) · Snoozed (future date).
   - **Card actions (owner-confirmed):** Log follow-up (note + next action date) · open lead ·
     existing WhatsApp deep link. No new gateways.
   - **Alerts (owner-confirmed): passive only** — overdue leads highlighted on the board, counts on
     My Day/Dashboard. Nothing auto-sent to anyone (A0).

3. **Sidebar polish (no new systems):** collapsible groups (persisted per browser), live unread
   badge on Inbox, pending-approvals badge on Approvals, active-group highlighting. The sidebar
   code list shrinks because the pages merged — no per-user preferences, no setup screen.
4. **Sequence inside the page:** tabs are ordered by the studio's working sequence (e.g. track
   after send), and cross-links between tabs keep the workflow moving (a quote's row action
   "Track" opens the tracking tab filtered to it — only where such links already exist).

**Exit:** the sidebar drops from 49 to 37 live entries without losing a single screen; every old URL
redirects to its tab; a lead's follow-up state is visible at a glance on the Tracking board; full gate
green.**

## Wave 2 — Runtime live chat in the admin (P10.2)

**Goal:** a visitor texting the website bubble is visible to the team within seconds, on any admin page,
without opening the inbox.

1. **`LiveChatIcon` Livewire component in the admin header** (every page, `inbox.view`): unread web_chat
   conversation count, green dot when a visitor is "typing"/active in the last 2 minutes (last message
   < 2 min), one click opens the drawer.
2. **Chat drawer (slide-over):** the live conversation list filtered to web_chat + the selected thread,
   replying inline via `SendConversationMessage` (the existing web_chat branch — no gateway, no window).
   Full inbox stays one click deeper.
3. **New-message toast + optional sound** (`NotificationSettings.chat_alert_sound`, default off): toast
   "New website chat — {name}: {preview}" with Open action. Polling every ~10s on visible tabs
   (Livewire `wire:poll.visible`), rate-limited query per user. No websockets on shared hosting —
   polling is honest and cheap at this scale.
4. **Assignment guard:** the toast/drawer only claims a thread (marks read) when a user opens it; two
   staff seeing the same toast is fine — the inbox already dedupes via unread_count.

**Exit:** a test visitor message produces a badge, a toast and a reply from the drawer, on any admin
page, without a page reload — full gate green.

## Wave 3 — Frontend v1: content systems (P10.3)

**Goal:** everything the "v2.1 pro" reference has that this builder lacks, built as native sections and
screens on the existing core (docs/12 §5). Order follows the money.

1. **Content models:** Blog (posts + categories, Sveltia decision D-106 stays for long-form — this is the
   dashboard-side blog for the studio's own writers), Portfolio (projects ← CRM projects, published flag,
   photos), Testimonials (from lead reviews/quotes' client feedback + manual), Client logos (exists as
   JSON — becomes managed), Team (people, roles, photos, sort), FAQs (exists as a block — becomes a
   library shared across pages), Hero slides (rotating hero for home).
2. **City pages & service pages as templates:** page templates ("city page", "service page") with
   placeholder sections — new city/service page = fill 6 fields, done. Links to the CRM area data
   (LinkCityPagesToAreas already hints at this).
3. **Forms builder:** the enquiry form becomes definable (fields, required, labels, Estimator link) on
   top of the existing `public/leads` pipeline + Turnstile. The Estimator (already public) becomes a
   page-embeddable block.
4. **Page templates screen** + "duplicate as template" on any page.

## Wave 4 — Frontend v1: SEO agent, speed, health (P10.4)

1. **SEO screen upgrade:** per-page audit (title/description length, canonical, schema presence, image
   alts), sitemap freshness, the dormant `woodex:seo-agent` (P8.5) wired to this data — it already
   reports missing descriptions and broken redirects; it gains image-alt and schema checks.
2. **Speed:** image conversion enforcement (WebP sizes already exist), lazy-loading audit, theme.css
   budget, PageCache hit-rate readout.
3. **Site health:** one screen — broken internal links across pages, redirects 404ing, media without
   alt, draft/published drift, last-import state.

## Wave 5 — The conversion itself (P10.5)

Money pages → long tail, group by group, behind the docs/13 gates (URL parity, visual parity, owner
sign-off per group). Static file retired per group only after parity passes. Home page last (highest
risk). Each conversion group ships green; the owner signs off each group in the preview.

---

## Route hierarchy (adds)

```
/admin/quotes?tab=…          All · Tracking · Templates (old /admin/quote-templates etc. redirect)
/admin/sales/documents?tab=… Descriptions · Document Design · Rate Book
…same pattern per the confirmed merge map (CRM, Accounts, Projects, System, Website)
header (all admin pages)     LiveChatIcon · drawer (inbox.view)
/admin/content/…             blog · portfolio · testimonials · team · faqs · logos · hero slides
/admin/website/templates     page templates (city, service, blank)
/admin/website/forms         forms builder + estimator embedding
/admin/website/seo           SEO audit · sitemap · agent
/admin/website/health        site health + speed
```

## Safety (unchanged, A0 — docs/23/24)

Nothing in P10 messages a client without consent, posts money, or enables an agent implicitly. The live
chat drawer is staff-to-visitor on a thread the visitor opened. The SEO agent stays dormant behind its
switch. Frontend conversion never changes a public URL without its redirect in place first.

## Definition of done (per wave)

Full test gate green (95+ files) · Pint + Larastan clean · preview verified live in a real browser ·
STATE.md + PROJECT_STATUS.md updated · commit pushed · owner sign-off before the next wave starts.
