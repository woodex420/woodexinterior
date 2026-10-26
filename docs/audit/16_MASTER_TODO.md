# 16. Master TODO — Woodex OS completion (2026-10-20)

_Method: full inventory of the code at `228dcc3` (routes, Livewire components, models, migrations, views), the
owner's screenshots and review list, and the earlier audits in this folder. Every line below is labelled
**Verified** (read in code / exercised), **Partial** (exists but shallow or unlinked), **Missing**, or
**Needs evidence** (cannot be reproduced from the sandbox — a URL or screenshot is needed)._

---

## 16.1 Fixed in this pass (Verified in the browser)

| # | Report | Fix | Proof |
|---|---|---|---|
| 1 | “This page is built from sections…” shown when opening the builder | `WebsiteBuilderController::show` now **redirects a block page to its section editor** (302) instead of opening a builder that can only refuse it | `/admin/website/builder/150` → `/admin/website/pages/150`, editor h1 “About Woodex Interior…” |
| 2 | Builder opens **with the Woodex OS sidebar** | The sidebar links to the builder with Livewire SPA navigation, which swaps only the body and loses the builder's CSS/JS. `AdminNavigation::FULL_DOCUMENT` marks full-document routes and the sidebar skips `wire:navigate` for them | Sidebar link has `wire:navigate: false`; after clicking, the page has **0** `.menu-item` elements and the builder stylesheet is loaded |
| 3 | Same class of bug from the pages list | `PageEditor` sent HTML pages to the builder with `navigate: true`; now a normal navigation | `PageEditor.php` line ~162 |
| 4 | Block pages had no obvious edit action | Pages list now shows **Edit sections** for block pages and **Page builder** for HTML pages | `pages-index.blade.php` |
| 5 | Website chat never reached the OS | The widget switch (`SiteSettings::chat_widget_enabled`) was **off**, so `/chat/widget.js` served a no-op. Switched on; a visitor message now creates a thread and the admin bell opens it by itself | browser run 2026-10-20 |
| 6 | The chat drawer was only 71 px tall (trapped in the header) | `backdrop-blur` on the header makes it the containing block for `position: fixed`. Header now paints solid (same look) | drawer measures 448×900 |
| 7 | The chat bell disappeared when nothing was unread | It rendered only for unread/active threads, so an empty inbox had no way in. Always visible now | `live-chat-bell.blade.php` |
| 8 | A visitor's text was dropped while the thread opened | The widget cleared the box before `send()`; it now keeps the words until the token is ready | `chat/widget.blade.php` |

Tests: `VisualBuilderTest` **13 passed / 44 assertions** (new test: a block page redirects to its editor).
Committed with this document.

---

## 16.2 Triage of the owner's list

### Website / page builder

| Report | State | Finding (Verified unless marked) |
|---|---|---|
| Builder must be full screen, no OS sidebar | **Fixed** | See 16.1 |
| “Built from sections” error | **Fixed** | The API still refuses block pages by design; the UI no longer routes them there |
| “Fix live website error” | **Needs evidence** | `/`, `/about/?preview=1`, `/3d-visualization/?preview=1`, `/1-kanal-house-design/?preview=1` all return 200 in the sandbox. The message quoted is the builder's, not the public site's. A URL/screenshot of the failing page is required. Defensive fix planned: an `html` page with empty `canvas_html` must fall back to the block renderer or 404, never a blank document |
| “Update missing frontend v1” | **Partial** | The Website menu already has all 15 items the owner listed (Verified in `AdminNavigation`). What is missing is the v2.1 **Content** group below |
| Content: Service pages ★, City pages ★ | **Missing** | 61 service and 12 city pages exist as pages, but there is no manager for them. v2.1 has **Service & site pages** (New page → Start from: Blank / Service / Sector / Landing / About & team / Packages / Case study, or copy an existing layout) and **City pages** (city picker, “Edit content”, `Bulk create with AI`, city-localised copy) |
| Content: Blog ★ | **Partial (first pass)** | A post **is** a page of type `insight` — one content model, kept. The list, create, duplicate, unpublish and the `/insights/` index card work (`6c22e99`, evidence `docs/audit/evidence/blog.jpg`). **What the owner is missing:** v2.1's article editor — template picker, kicker, standfirst, hero, details strip, category/tags, listing card, SEO panel with live preview, FAQ link, AI drafting. Rebuilt on the shared content editor in P1A–P1B |
| Content: Portfolio ★ | **Partial (first pass)** | The 6 project pages list with the project link (`projects.case_study_page_id`, consent), index card, duplicate and unpublish work (`ec7613e`, evidence `docs/audit/evidence/portfolio.jpg`). **Missing:** v2.1's project editor — template picker, hero, details strip (project type, setting, year, size), facts box (scope, key move, materials, note), listing card, AI drafting |
| Content: Page templates ★ | **Partial (first pass)** | 50 layouts with search, type filter, default per type, duplicate and retire are live (`5c164ae`, evidence `docs/audit/evidence/page-templates.jpg`). **Wrong shape:** it is a standalone screen that also creates pages, while v2.1 offers templates inside “New page → Start from”, inside “New article”, and in a Template library. Being folded into the create flows in P1B; the builder edits one page, so a template must never create a builder page |
| Content: Forms ★ | **Done (16.13)** | `/admin/website/forms` lists the seven site forms with counts from `leads`, the field names actually received, and the one real alert-routing rule. No per-form routing store — see 16.13 |
| Content: FAQs ★ | **Done (16.16)** | `/admin/website/faqs` edits the shared FAQ library every page's FAQ section picks from, shows which pages use each answer, and re-renders them on save. Rename/reorder/add/delete; no second store |
| Content: Testimonials ★ | **Done (16.17)** | `/admin/website/testimonials` edits the shared review library; reviews are quoted verbatim, “Used on” is the switch, and there is no per-review rating or photo because the site's section shows neither |
| Content: Estimator ★ | **Done (16.15)** | `/admin/website/estimator` edits the file the public page reads and mirrors the rates into `EstimatorRate`; template pack (14 ready-made services), saved rate books, “adjust all rates %”, finish names, quotation terms and the live preview are all in |
| Content: Client logos ★ | **Partial** | Client logos are ported separately (`admin-logos.js`, evidence `studio-logos.jpg`). **Missing from the v2.1 screen:** the display settings on the home Clients section (small title, heading, grid vs sliding row, speed, grey-on-hover) — those are section fields, not library rows |
| Content: Team ★, Media ★ | **Partial** | `TeamMembersIndex` and `MediaLibrary` (upload, alt text, filters) exist. **Missing:** team — starter roles, department, portrait ratio, Featured, “Pages & heading” tick-list, reorder, Live; media — Trash, Optimise large images, Make responsive (480/960 WebP), used-on list, replace picture, crop/resize, Save alt text with “add it to pages where this image has no alt text” |
| Redirects | **Exists** | `RedirectsIndex`; needs the v2.1 bulk/redirect-chain tools |
| Header & footer, Hero slides, Section library, SEO/AI, Speed, Site health, Theme, Settings | **Exists** | All routed; Theme now shares one token store |

### Sales / documents

| Report | State | Finding |
|---|---|---|
| “Estimations is empty” | **Partial** | “New estimate” exists and works; there is no master template, no duplicate, no starter lines. The emptiness is data + missing template |
| Quotation live preview / PDF viewer | **Partial** | Live preview exists (screenshot); PDF is a separate route. Needs an in-page viewer with zoom/download and a print stylesheet check |
| Description library | **Exists** | `DescriptionLibrary`, `DescriptionTemplate` |
| “No new invoice system” | **Partial** | Invoices are only raised from project milestones and quote advances (`RaiseMilestoneInvoice`, `RaiseAdvanceInvoice`). There is **no “New invoice” action** on the invoices list |
| “No Woodex Interior / Woodex Furniture invoice templates” | **Missing** | `DocumentDesign` exists for layout, but there is no brand template switch or branded preset |
| Invoice PDF export | **Exists** | `admin.invoices.pdf` (currently labelled “Download PDF” in the preview) |

### CRM / leads / pipeline

| Report | State | Finding |
|---|---|---|
| Lead click should open a side panel, not a new page | **Missing** | `admin.leads.show` is a full Livewire page |
| Update status without leaving the list | **Partial** | The list can move stage; a drawer with status/owner/stage is the requested behaviour |
| “No New pipeline option” | **Missing** | Data model is `PipelineStage` only — one pipeline. Multiple pipelines need a `pipelines` table and a stage FK |
| Lead assignment rules | **Exists** | `AssignmentRules` + `LeadRulesSettings`; needs reorder/edit polish |
| Alert rules edit/manage | **Exists** | `AlertRulesIndex`; verify edit/disable and recipients |
| Change requests empty; Telegram `/idea` `/issue` | **Partial** | Board exists; no Telegram intake command or bot webhook mapping |
| Canned replies | **Exists** | `CannedRepliesIndex`; needs categories/variables |
| Handovers | **Partial** | `ProjectHandoverItem` and project screens exist; no dedicated checklist flow |
| Client portal | **Exists** | `PortalAccessCode`, `/portal` routes |

### Chat / marketing / integrations

| Report | State | Finding |
|---|---|---|
| Live chat from the website into the OS | **Done (Verified 2026-10-20)** | Visitor bubble → conversation → bell auto-opens the drawer → staff reply → visitor sees it. Evidence `docs/audit/evidence/live-chat-{admin,visitor}.png` |
| Chatbot asks name → phone → project type → location, saves to DB | **Partial** | `BotKnowledge`/`BotUsage` exist; there is no scripted capture flow that writes those fields onto the lead |
| Auto-open the chat when the client sends a message | **Done** | `LiveChatBell::poll()` (10 s, visible tabs) watches the newest **unread inbound message** — an assistant reply can no longer hide the enquiry — and opens the drawer on that thread |
| Lead Ads | **Missing** | Menu item is a placeholder (`P7`) |
| Facebook / LinkedIn / GMB | **Missing** | Only `SocialPlanner` (scheduled posts). No connectors, no reviews import |
| MCP | **Missing** | No MCP server or client in this repo |
| Telegram working | **Partial** | Webhook connect + “check live connection” exist in Integrations and a notification toggle in Settings; there is **no token/chat-id form**, which is why it cannot be configured |
| WhatsApp templates | **Exists** | `WhatsAppTemplates` |

### Dashboard / settings

| Report | State | Finding |
|---|---|---|
| Dashboard must show the whole system | **Partial** | `DashboardHub` exists with tabs; needs the cross-module summary (leads, sales, chat, automations, marketing) |
| Settings: add Telegram | **Missing** | Token/chat-id/test form absent (see above) |
| Website settings: social + Google Search Console + Analytics | **Partial** | GTM id + Facebook/Instagram/LinkedIn links exist; GA4 and Search Console verification are not fields |
| Data import per master plan | **Exists** | `DataImport`; scope to be confirmed against the plan |

---

## 16.3 The plan

Order chosen so that each phase is shippable, testable and reversible on its own. Sizes are working sessions
(half-days), not promises.

### P0 — Trust the website (1–2 sessions)

1. Empty-`canvas_html` guard in `SiteRouter` (fallback → block renderer → 404), with a test.
2. End-to-end form check on the live site: `/api/forms.php` creates a lead, spam guard, thank-you state.
3. Close the evidence gap on “live website error” (needs the owner's URL).
4. Fix any dead menu items that are placeholders but look live.

**Done when:** a visitor can submit every form on `/` and `/contact/`, the lead appears in the OS, and no public
page can render blank.

### P1 — Port the v2.1 content admin (revised 2026-10-20, second revision)

_The owner's call, and it is right: do not rebuild the content system, **port it**. The screens exist, they
are used in production, and they are plain JavaScript. What follows is the smallest honest way to have one
content system inside Woodex OS. The reasoning and the measurements are in 16.7._

**P1-1 — The seam (1 session). — DONE 2026-10-20 (`ce…` this commit).** Two routes in the OS that speak the language the admin already uses:
`POST /admin/website/api` (`/api/admin.php` in v2.1) and `POST /admin/website/builder-api`
(`/api/builder.php`). Both take `{action, ...}` and return `{ok, ...}`. Each action resolves to an OS
handler; an unimplemented one returns `{ok:false, error:"…"}` — the admin already renders that honestly, so
a missing action looks like a message, never a fake success. Permissions are checked server-side
(`website.edit` / `website.publish`) and the v2.1 token header is accepted but never trusted alone.

**P1-2 — The Website section, screen by screen (7–9 sessions).** 18 JS files, 4,435 lines, **108 actions**
cover all 23 Website screens (the map is in 16.8). Order by dependency: `admin-pages.js` (page list) →
`admin-content.js` (blog, portfolio, FAQ groups, testimonials, team, AI) → `admin-fields.js` (services,
cities, bulk AI) → `admin-templates.js` → `admin-logos.js` → `admin-media.js` (24 actions, the biggest) →
`admin-library.js` + `admin-chrome.js` + `admin-hero.js` + `admin-redirects.js` (the Pages & builder group)
→ `admin-p18e.js` (forms, estimator) → `admin-seo.js` / `admin-seo38.js` / `admin-seoagent.js` /
`admin-speed.js` / `admin-media.js` (health) / `admin-theme.js`. The three builder actions (`load`, `save`,
`page_new`) map onto the builder we already have — one builder, one page.

**P1-3 — Look, shell and the sidebar (1 session).** The ported screens keep their own stylesheet, scoped,
inside the OS shell (sidebar + header). If a screen cannot live inside the shell (a full-width table or the
editor), it gets the same full-document exception the builder has. Their sign-in screen is never shown: the
OS session is the session. The Website sidebar regroups to the owner's own structure — **Pages & builder**
(6), **Content** (11), then Media, SEO, SEO agent, Speed, Site health, Theme — and
`WebsiteAdminParityTest`'s pinned label list is rewritten with it.

**P1-4 — Switch and delete (1 session).** As each ported screen passes its browser check, the OS-native
screen behind it is deleted the same day (the list is in 16.8). `ListingIndex` stays only if the ported
screens use it; otherwise it goes with its tests. The OS *services* stay — `PageCache`, `PageAudit`,
`PageSpeedRun`, `Menus`, the theme tokens, the media pipeline — because they are what the ported screens
read. The port deletes presentation, never data.

**P1-5 — Business family (later, only if wanted).** `admin-crm.js`, `admin-sales*.js`, `admin-chat*.js` and
friends stay out: the OS already has better versions (ledger, permissions, CRM links). Porting them would
replace a working system with a copy.

**Done when:** the owner's missing content screens (article editor with templates and AI, project editor,
service pages, city pages with AI, FAQ groups, testimonials, client logos, team, media library) open inside
Woodex OS, are the v2.1 screens, and write to the OS database through OS permissions — and my own versions
of Blog, Portfolio and Page templates no longer exist.

### P2 — Sales documents (4–6 sessions)

1. **New invoice** action + standalone invoice builder; brand switch **Woodex Interior / Woodex Furniture**;
   PDF export with the brand template and a print check.
2. **Estimations master template**: starter sections, duplicate, “from rates”, and a seeded example.
3. **Quotation preview**: side-by-side live preview + in-page PDF viewer (zoom, download, print).
4. **Description library**: create/edit, insert into quote lines.
5. **Document designer**: presets, header/footer, page numbers, terms per brand.

**Done when:** an invoice can be created from the list, branded, exported, and paid without touching a quote.

### P3 — Leads, pipeline, alerting (5–7 sessions)

1. **Lead drawer** — *done 2026-10-20*: clicking a client name opens the lead over the list; stage and owner
   save in place (the Won rule still applies). Evidence: `docs/audit/evidence/lead-drawer.jpg`.
   Known issue found while testing: the hub renders its own page header **and** the tab component renders
   one, so "Leads" appears twice above the tab bar. Pre-existing on every hub screen; fix next pass.
2. **Multiple pipelines**: `pipelines` table + stage FK (schema change — needs approval), board switcher,
   “New pipeline”, per-pipeline stages.
3. **Assignment rules**: drag-to-reorder, edit, workload caps, test panel.
4. **Alert rules**: edit/disable, recipients, test fire.
5. **Change requests**: Telegram `/idea` and `/issue` intake → board card; move card notifies the author.
6. **Canned replies**: categories + variables.

**Done when:** a lead can be worked end to end from the list, and a Telegram idea becomes a tracked card.

### P4 — Live chat + bot (4–5 sessions)

1. Widget auto-open rules, unread badge, sound.
2. Scripted capture: name → phone → project type → location, saved on the lead/conversation.
3. Bot flow editor (reuse `BotKnowledge`), fallback to a human, transcript on the lead.
4. Inbox: assignment, canned replies, “create lead from chat”.

**Done when:** a website visitor chats, the bot captures the four fields, and the lead appears in the OS with the
transcript and a bell notification.

### P5 — Marketing + integrations (5–7 sessions)

1. **Facebook / Instagram** lead ads + page token, **LinkedIn** lead gen, **Google Business Profile** reviews.
2. **Telegram**: settings form (token, chat id, test message), notification routing.
3. **WhatsApp templates**: submission state, variables, language.
4. **MCP**: define the tool surface (pages, leads, quotes) and ship a read-only server first.

**Done when:** a lead from a Facebook form lands in the OS with source attribution, and Telegram can be configured
and tested from the UI.

### P6 — Dashboard + settings (2–3 sessions)

1. Cross-module dashboard: leads today, pipeline value, quotes/invoices due, chats waiting, automations, website.
2. Settings: Telegram, GA4, Search Console verification, social links, brand assets.
3. Data import wizard aligned with the master plan.

### P7 — Projects, handovers, portal (3–4 sessions)

1. Handover checklist per project, photos, client sign-off.
2. Portal: documents, invoices, approvals, support.

---

## 16.4 Rules for every phase

- No schema change without explicit approval (P3 pipelines and P1D FAQ groups are the two asked for).
- Each phase: tests (feature + browser), Pint, PHPStan, `Feature/Website` suite, smoke, docs section, one commit.
- Rollback is a revert; nothing deletes data.
- The public site stays byte-identical unless the phase is about changing it.

---

## 16.5 Questions (answers change the order)

1. **The live-site error**: send the URL (or a screenshot with the address bar). I cannot reproduce it from the
   sandbox — every public URL I try returns 200.
2. **Invoices**: what should the two templates look like? A PDF/photo of the current Woodex Interior and Woodex
   Furniture invoices is enough to match them.
3. **Live chat**: should the bot answer first and hand over to a human, or should every chat ring the inbox
   immediately?
4. **Marketing**: which connector first — Facebook Lead Ads, Instagram, LinkedIn, or Google reviews?
5. **MCP**: what should it do first — read pages/leads, or let an agent edit content?
6. **Priority**: my order is P0 → P1 → P2. If cash flow matters more than website content, P2 (invoices) can go
   before P1 — say the word.
7. **FAQ groups** need two tables (`faq_groups`, `faq_group_items`). Approve the schema change?
8. **AI for content**: which provider, and whose key? The chat bot already stores one in Settings; the content
   actions can reuse it with their own daily cap, or take a second key.

---

## 16.6 Content studio — what each screen is for (owner review, 20 Oct)

| Screen | What it is for | What it is not |
|---|---|---|
| Page builder | The HTML canvas for the one page the site still builds that way (home). Frontend v1 pages are HTML and stay HTML | Not a section editor; not how articles, services or cities are written |
| Page templates | A library of section layouts that fills “Start from” in New page and the article template picker | Not a page list; not a creator of builder pages |
| Blog & insights | Writing and publishing articles: template, AI draft, kicker/standfirst/hero/details, SEO, listing card, FAQ link, schedule. The 48 live articles sit in the same list, marked live | Not a page list with a filter — that was the first pass |
| Portfolio | Case studies made from real projects: project link, consent, facts box, gallery, listing card | Not a second project manager — the job itself lives in Projects |
| Service & site pages | Pages of type service / page / landing / about / legal: created from a layout, managed by type | Not a second “All pages” — same inventory, filtered |
| City pages | One page per city on the same layout, copy localised by AI, bulk create | Not a copy of the service pages |
| All pages | The whole page inventory, every type, for finding and housekeeping | Not the place to write |
| Content library | FAQ groups, testimonials, logos and team — written once, reused in sections, in the AI chat and in schema | Not per-page copies of the same question |

Three rules that come out of the review:

1. **Import, don't invent.** Where v2.1 has a screen or a field, the OS copy has the same screen and the same
   fields, in the same order. Where the OS data model differs (a post *is* a page), the workflow stays the
   same and only the storage differs.
2. **One content model.** Articles, studies, services and cities are pages. There is no second article table.
3. **One editor.** Blog, Portfolio, Service pages and City pages all open `ContentEditor`; the screen only
   decides the type, the defaults and the sidebar panels.

**Order inside P1, as agreed:** P1A editor → P1B templates + AI → P1C the four screens → P1D FAQ groups,
testimonials, logos, team → P1E media. Documents (P2) stay blocked on the owner's sheet.

---

## 16.7 Decision: port the v2.1 content admin, do not rebuild it (20 Oct)

The owner asked the right question: **why not copy the complete system?** I read the source before answering.

**What the v2.1 content system actually is** (Verified, `marketingwoodex-cloud/-marketingwoodex`, 2.9 MB):

| Part | Measurement |
|---|---|
| Screens | `frontend-v1/admin/` — 67 files: `index.html` (112 KB), `admin.css` (111 KB), `admin.js` (50 KB core) + 60 plain-JS modules. **No build step** |
| The seam | Every module calls `W.api(action, data)` or `W.bapi(action, data)`; both POST JSON to one endpoint (`/api/admin.php`, `/api/builder.php`) with an `action` field |
| Actions | 73 distinct actions across the 12 content modules |
| Backend | `api/v2/` — a separate PHP app: its own tables (`wx_content`, …), its own auth (`wx_require_user`), its own audit and approvals, its own dispatcher |

**Why the screens can be copied and the backend cannot.** The screens are the system; the backend is a second
truth. Copied whole, a website lead would be written to `wx_*` and the OS CRM would never see it, a quotation
made in the OS would be invisible to the copied admin, and the permission matrix would be decided twice. The
one thing that must stay ours is the data and the permissions. So: **their screens, our server.**

**Where each of the owner's missing screens already lives:**

| Owner's list | File |
|---|---|
| New article with template + AI | `admin-content.js` (“Start a new article”, `cms_ai_*`, `ai_run`) |
| New project | `admin-content.js` (study editor, facts box) |
| Services and city pages with AI | `admin-fields.js` (`cms_biz_*`, “Bulk create with AI”) |
| FAQ groups | `admin-content.js` + `admin-starters.js` (“Add starter FAQ groups”) |
| Add testimonial | `admin-content.js` (testimonials / team, 5 designs) |
| Client logos | `admin-logos.js` |
| Team | `admin-content.js` |
| Media library (trash, optimise, responsive, alt, used-on) | `admin-media.js` |
| Templates | `admin-templates.js` |
| New page / page list | `admin-pages.js` |

That is the whole list, already written. Rebuilding it — what I started with Blog and Portfolio — was the
wrong approach and is now abandoned.

**Rules for the port**

1. **One frontend.** The public site is v1, already imported; nothing in this port touches it.
2. **One builder.** `load` / `save` / `page_new` map onto the existing builder; the builder edits one page.
3. **One database.** `cms_list` / `cms_get` / `cms_save` read and write the OS `pages` table; the article
   fields go in `pages.seo` and block data, so the port needs **no migration**.
4. **One permission check.** Server-side, on every action; the v2.1 token is never the authority.
5. **Delete what the port replaces**, same day, so there is never a second way to do the same job.
6. **Nothing pretends.** An action that is not implemented says so.

**Two things to confirm before the code starts**

1. **Provenance.** That repository has no licence file. If it is the studio's own code, say so and it becomes
   our source, recorded in `platform/THIRD-PARTY.md` with the commit SHA. If not, I port the behaviour and
   rewrite the screens — same result, more time.
2. **The delete order.** I will not remove Blog, Portfolio and Page templates until their replacements open;
   the OS must not go a day without a content screen.

**Effort.** P1-1 and P1-3 one session each; P1-2 five to six; P1-4 one. Eight to nine sessions for the whole
content studio, against fifteen to twenty to rebuild it screen by screen — and the rebuild would not match.

---

## 16.8 The target: one Website section, 23 screens, the v2.1 front on the OS data (20 Oct)

The owner's goal, written down: **the same frontend running on Woodex OS — the complete v2.1 content system —
with the old different frontend removed and the usable data kept.**

### The 23 screens, and where each one stands

| v2.1 screen | v2.1 module | Woodex OS today | Decision |
|---|---|---|---|
| All pages | `admin-pages.js` | `PagesIndex` (OS style) | Port; OS list deleted |
| Page builder | `admin.js` + `/builder/` | full-screen builder (HTML canvas, one page) | **Keep the OS builder**, wire `load`/`save`/`page_new` to it |
| Section library | `admin-library.js` | `SectionLibraryIndex` | Port; OS screen deleted |
| Header & footer | `admin-chrome.js` | `MenusIndex` | Port; OS screen deleted |
| Hero slides | `admin-hero.js` | `HeroSlidesIndex` | Port; OS screen deleted |
| Redirects | `admin-redirects.js` | `RedirectsIndex` | Port; OS screen deleted |
| Blog | `admin-content.js` | `BlogIndex` (first pass) | Port; **my screen deleted** |
| Portfolio | `admin-content.js` | `PortfolioIndex` (first pass) | Port; **my screen deleted** |
| Service pages | `admin-fields.js` | **missing** | Port |
| City pages | `admin-fields.js` | **missing** | Port |
| Page templates | `admin-templates.js` | `PageTemplatesIndex` (+ creator) | Port; creator deleted, library kept as the screen |
| Forms | `admin-p18e.js` | **missing** (public renderer only) | Port |
| Estimator | `admin-estimator.js` | **ported** (16.15) | Rate book file + `EstimatorRate` mirrored on save |
| FAQs | `admin-content.js` | `ContentEntry` rows inside Content library | Port |
| Testimonials | `admin-content.js` | same | Port |
| Client logos | `admin-logos.js` | same | Port |
| Team | `admin-content.js` | `TeamMembersIndex` | Port; OS screen deleted |
| Media | `admin-media.js` | `MediaLibrary` | Port (24 actions: trash, optimise, responsive, alt, used-on) |
| SEO | `admin-seo.js`, `admin-seo38.js`, `admin-p36.js` | `SeoOverviewIndex` | Port; `PageAudit` behind it |
| SEO agent | `admin-seoagent.js` | `SeoAgentIndex` | Port |
| Speed | `admin-speed.js` | `SpeedIndex` | Port; `PageSpeedRun` behind it |
| Site health | `admin-media.js` | `SiteHealthIndex` | Port |
| Theme | `admin-theme.js` | `ThemeBuilder` | Port; theme tokens unchanged |

`Content library` disappears: its three kinds become the three v2.1 screens. `Website Settings` stays as it
is (OS-only, the v2.1 equivalent is `business` / `settings`).

**The sidebar after the port** (the owner's list, verbatim, in its own groups):

```
Website
  Pages & builder   All pages · Page builder · Section library · Header & footer · Hero slides · Redirects
  Content           Blog · Portfolio · Service pages · City pages · Page templates · Forms · Estimator
                    FAQs · Testimonials · Client logos · Team
  (no group)        Media · SEO · SEO agent · Speed · Site health · Theme
```

Seven of the 23 do not exist in the OS at all today (Service pages, City pages, Forms, Estimator, FAQs,
Testimonials, Client logos) — the port is also what makes the sidebar match the owner's list for the first
time.

### How the same frontend runs inside the OS

1. **Screens, not a second app.** The 18 files are copied into `platform/public/assets/admin-v21/`. Their
   chrome — sidebar, topbar, login, hash router, session storage — is switched off by one adapter
   (`wx-embed.js`) that: hides `#side`/header/login, calls their own `signedIn()` with a token the OS mints,
   reads the screen from the OS URL, and tells the OS shell which nav item is active.
2. **One seam.** `api()` and `bapi()` POST `{action}` to `/admin/website/api` and `/admin/website/builder-api`.
   108 actions, one resolver table, each mapped to an OS class. Unimplemented → `{ok:false, error}` and the
   screen shows the message; nothing pretends.
3. **One session, one permission check.** The OS mints the token; the server checks `website.edit` /
   `website.publish` on every action and never trusts the header alone. Their login screen is unreachable.
4. **One database.** `cms_*` read and write `pages` + `page_blocks` + `pages.seo`; `media_*` the media table;
   `heroes_*` the hero table; `chrome_*` the menus; `redirects*` the redirects; `est_*` the rates;
   `forms_*` the forms bridge. **No migration is needed for the port itself.**
5. **One look.** Their CSS is scoped to the mount point, inside the OS shell — same table, same buttons, same
   OS header. A screen that needs the whole viewport (builder) keeps the existing full-document exception.
6. **Delete on green.** My OS screen is removed the same day its ported twin passes its browser check.

### What is deleted, and what must survive

**Deleted (presentation only):** the 17 OS Website screens replaced above; the `Content library` nav item;
the Page templates creator; my Blog and Portfolio lists; the v2.1 admin's own chrome, login, router and
session code; the copy of the v2.1 static admin once the OS port is signed off.

**Kept (all of it is data, and all of it is read by the ported screens):** 48 insight pages, 61 service pages,
12 city pages, 8 area pages, 6 project pages, the home page, every `page_blocks` row, every `pages.seo`
record, the 50 page templates, the media library, hero slides, menus, redirects, saved sections, FAQ /
testimonial / logo entries, team members, theme tokens, PageSpeed runs and audit results.

**Never deleted:** the public v1 site (it is final), and the CRM / sales / accounts data — the port does not
touch them.

### Questions that change this plan

1. **Provenance.** Is `frontend-v1/admin` the studio's own code? If yes, it becomes our source, recorded in
   `platform/THIRD-PARTY.md` with the SHA. If not, I port the behaviour and rewrite the screens.
2. **The old admin.** `woodex.com.pk/admin` and the sandbox copy — retire both when the OS port is signed off,
   or keep one read-only for comparison?
3. **Estimator and Forms.** The v2.1 screens expect their own tables. Confirm the OS keeps `EstimatorRate`
   as the rate source and `leads` as the form destination — the screens stay, the storage is ours.
4. **AI keys.** The ported screens call `cms_ai_*` / `ai_run`. Reuse the chat bot's key (Settings → Bot) for
   content AI with its own daily cap, or a second key?

Answered “yes” to 1 and 3 and named a choice on 2 and 4, the port starts at P1-1.

### Status of the port (2026-10-20, end of session)

| Step | State |
|---|---|
| P1-1 The seam | **Done.** `POST /admin/website/studio/api` (+ `builder-api`), `{action}` in, `{ok}` out, OS session and `website.edit` on every call; an unported action answers honestly. 10 tests |
| Screen 1 — Client logos | **Live** at `/admin/website/logos`: the v2.1 module, inside the OS, writing the home page's clients section — verified end to end in a browser (upload → save → the home page renders it) |
| Screens 2–3 — Blog, Portfolio | **Live** (`175b7b4`). The real v2.1 editor inside the OS: 48 imported articles open with their words, a new article creates a `Page` draft, takes a hero from the media picker, publishes through `cms_published` and renders at `/insights/<slug>/` with kicker, h1, dek, meta and hero. `StudioContent` keeps the editor JSON in one `text` block and a `PageVersion` before every save; publish requires `website.publish` and adds the `/insights/` index card. The OS-native `BlogIndex`/`PortfolioIndex` (Livewire, views, tests) are **deleted**. 19 tests |
| The rest | Screens are switched on one at a time in `WebsiteStudioController::SCREENS`, each after its actions are ported |

Evidence: `docs/audit/evidence/studio-logos.jpg` (the ported screen inside the OS), and the browser run that
uploaded a logo, saved, and then found the new heading on `/`.

---

## 16.9 Wave 0 — freeze, inventory and safety (started 20 Oct 2026)

The owner supplied a full upgrade plan (`docs/audit/17_OWNER_MASTER_PLAN_2026-10-20.md`, 1,182 lines,
Waves 0–10) and said **start**. Wave 0 is the gate: no real row moves until the inventory is written,
the credentials are rotated and the import has been rehearsed. The working paper is
**`docs/audit/18_WAVE0_BASELINE.md`**; this table is the tracking view.

| # | Item | State on 20 Oct | Next action |
|---|---|---|---|
| 1 | Staging database | `legacy` connection now takes `LEGACY_DB_DRIVER=mysql\|sqlite` + `LEGACY_DB_SQLITE_PATH`; default behaviour unchanged | Owner: a v2.1 dump **with rows**, or read-only `LEGACY_DB_*` |
| 2 | Inventory — tables, stores, screens, routes, APIs | **Done** in §1–§2 of doc 18: 44 `wx_*` tables in three destinations, 4 JSON stores, 60 admin modules, 35 API route files, 65 OS migrations, 90 OS screens | Keep it current as modules port |
| 3 | Canonical business settings | **Closed for the public site** (§4 of doc 18): the site already reads NAP from `BusinessSettings`; the one real number in application code (the flood refusal) and the three literal “Monday to Saturday” labels now follow the settings, both covered by tests. Validation examples stay literal on purpose | Settings → Business edits the values |
| 4 | Rotate old credentials | **List issued** (§5, 10 items with where each lives) | Owner: hPanel, Meta, BotFather, Cloudflare, AI provider |
| 5 | `composer.lock` | Absent (Q-01). CI already falls back to `composer update` and prints a gzip+base64 lock to commit | One CI run, or `composer update --lock` locally |
| 6 | Activate CI | Workflows written and validated against local runs (276 tests, Pint PASS, PHPStan 0) but **cannot be pushed**: the GitHub App lacks `workflows` permission | Owner: grant the permission or commit `.github/workflows/` |
| 7 | First dry-run import report | **Pipeline rehearsed end to end** on a synthetic v2.1 database: `users 3, clients 3, leads 12, notes 19/20, bookings 6`, 0 failed, 5 expected warnings, transaction rolled back. Evidence `docs/audit/evidence/wave0-import-rehearsal.txt`, fixture `platform/database/rehearsal/build-v21-rehearsal.php` | Re-run against the real dump and attach the report |

**Not blocked by the dump:** Wave 1 (canonical settings, sidebar regroup) and the Website screen port
continue in parallel. Waves 2–10 stay as written in the owner's plan; §16.8 is the Website slice of it.

---

## 16.10 Website sidebar regrouped (20 Oct 2026)

The owner's three-band list is live (`847c2bd` → `AdminNavigation`). The single “Website” group of 19
entries is now:

| Band | Items |
|---|---|
| **Pages & builder** | All pages · Page builder · Section library · Header & footer · Hero slides · Redirects |
| **Content** | Blog · Portfolio · **Service pages** · **City pages** · Page templates · Forms* · Estimator* · FAQs* · Testimonials* · Client logos · Team |
| **No heading** (site-wide tools) | Media · SEO · SEO agent · Speed · Site health · Theme · Website Settings |

`*` = not ported yet: listed disabled with a “soon” pill and the tooltip “arrives with the Website port”,
so the section matches the target without pretending a screen works. **Content library left the nav** —
its route still answers and its records are the pages and blocks the screens above edit, so no data moved
or was deleted (asserted in `WebsiteAdminParityTest`).

Two bugs surfaced while checking it in the browser:

1. `VisualBuilderTest` and `QuoteBuilderTest` both declared a global `builder()` helper. Any **full**
   `vendor/bin/pest` run — which is exactly what CI runs — died at bootstrap with “Cannot redeclare
   function builder()”. Renamed to `builderApi()`. The complete suite now runs: **1,299 passed, 1 skipped
   (8,847 assertions)**. CI item 6 would have failed on this even with the `workflows` permission.
2. The Website settings card printed “Header &amp; social” (an escaped entity inside an escaped title);
   now “Header & social”.

Evidence: `docs/audit/evidence/studio-sidebar.jpg` (Pages & builder / Content, with the disabled pills)
and `studio-sidebar-tools.jpg` (the heading-less tools band).

Pint PASS 850, PHPStan 0 errors.

---

## 16.11 Screen 4 — Service pages, ported (20 Oct 2026)

`/admin/website/services` is live in the studio shell (`docs/audit/evidence/studio-services.jpg`). It is the
v2.1 screen's job, carried over honestly: v2.1 listed **built HTML files** and edited a page by rewriting the
file; the OS renders every page from blocks, so this screen lists the OS page table (77 pages: services, areas,
about, home, booking, contact…) with the same columns and sends **Edit content** to the OS section editor.

Kept from v2.1: the how-to box, the search, the **Duplicate** row action, and **New service page** started from
an existing layout (61 service pages offered) or blank. Added because the OS has them: the address is
normalised and checked before anything is created, and both new-page paths land as **drafts**.

New seam actions: `pages`, `cms_page_kinds`, `cms_page_new`, `cms_page_duplicate`. The copy itself lives in
`App\Domain\Website\Services\PageCopier`, shared with Pages → Duplicate so the two cannot drift; it numbers
the address (`-copy`, `-copy-2` …) instead of failing on a collision.

Verified in the browser: 77 rows listed, search “kitchen” narrowed to 4, Duplicate produced `/about-copy/`
with all 8 blocks as a draft, and “New service page” created a draft from a source layout and opened the OS
editor on it. Smoke rows deleted afterwards (pages back to 143). **0 console errors.**

Tests: `StudioServicePagesTest` **9 passed (36 assertions)** — the screen, the permission, the list, the kind
map, copy-from-layout, blank page, taken address, duplicate numbering, and the nav contract.
Full Website suite + sidebar test: **328 passed**; Pint PASS 852; PHPStan 0.

Next screens in the band: City pages, Forms, Estimator, FAQs, Testimonials.

---

## 16.12 Screen 5 — City pages, ported (20 Oct 2026)

`/admin/website/cities` is live (`docs/audit/evidence/studio-cities.jpg`). v2.1 kept city pages as **content
drafts** and published them by writing HTML; the OS has one kind of page, so a city draft is a page of type
`city` with draft status — it appears in this list, opens in the section editor, and goes live from Pages.

Carried over: the banner (“new cities are copied from a template city, saved as drafts”; footer link now goes
to the OS Header & footer), the **Draft first, Live after** ordering with the status badge, the search, **New
city** with a template picker, and **Bulk create** (one per line, up to 25, each result reported per row).

Carried over honestly: **“Bulk create with AI” is not**. The AI rewrite needs `ai_run`, which is not ported;
the screen says the text is the template's with the name swapped and that no AI key is connected here,
instead of showing a checkbox that does nothing.

The copy swaps the **template city's name in the words only** — headings, body copy, titles — and never in an
address, a `/media/` reference or an image id. A page copied from Faisalabad therefore says “Interior design
in Gujrat” while the studio's own Lahore address stays correct.

Two bugs caught while doing it:

1. `catch (Throwable)` in a namespaced class without `use Throwable;` silently matches nothing — the bulk
   path aborted on the first bad name instead of reporting it. Imported, and the test now proves a bulk run
   creates the good cities and reports the skipped one.
2. The new screen's JS had double-escaped regexes (`/^\\/+…/`), so the file failed to parse and the v2.1
   view stayed active — the browser showed v2.1's own “no city pages loaded” text. `node --check` on every
   ported module is now part of the check.

Verified in the browser: 12 cities listed, 12 template options, New city created a draft and opened the
editor, Bulk created 2 drafts with 2 green rows, 15 rows afterwards, **0 console errors**. Smoke pages
deleted (back to 143 pages / 12 cities). Tests: `StudioCityPagesTest` **6 passed (32 assertions)**.
Sidebar count assertion moved to 53.

## 16.13 Screen 6 — Forms, ported (20 Oct 2026)

`/admin/website/forms` is live (`docs/audit/evidence/studio-forms.jpg`). The screen answers two questions the
v2.1 screen asked: **what came in through each form**, and **where a new enquiry goes**.

What came in is read from the OS, not from a new table: the public pages already post to the site bridge,
which writes every submission into `leads` with `source_detail` = the form key (`brief`, `contact`,
`website`, `estimator`, `booking`, `book-a-visit`, `whatsapp`). The screen lists those seven forms with the
count in the last 30 days, the total, the last submission, and **the names of the fields actually received**
(from `meta.fields` on the latest 200 leads of that form). A form with no submission says so instead of
showing an empty box. Every row links to Enquiries.

**Deliberately not carried over: the per-form alert editor.** v2.1 let each form set its own alert email,
WhatsApp number, WhatsApp switch, auto-reply switch and auto-reply text, stored in its own settings blob.
The OS routes every new enquiry through one rule — the roles in Settings → Notifications, in-app and
Telegram, email for staff without Telegram — switched on or off by `woodex:new-lead-alert` in Automations.
Storing a second, per-form routing table would be a schema change and a product decision (two competing
routing rules, and no way to tell which one won) — so the screen shows the one real routing rule and links to
where it is set. If the owner wants per-form routing later, it is a table and a form on this screen; nothing
here pretends it exists.

The v2.1 view for Forms was **removed** from `admin-p18e.js` rather than left to shadow the new one: it called
`forms_get`, which is not ported, and painted a red “not ported yet” box over the ported screen. `admin-p18e.js`
still owns the Estimator view, which is the next screen in the queue.

Two bugs caught in the browser, both invisible to the PHP test:

1. `$("#fm-alerts .card-b").className = ""` followed by another query for the same selector returned `null` —
   the class had just been stripped, so the selector no longer matched and the whole read fell into the error
   branch. It now queries the box once and writes into it.
2. The literal `→` in “Automations → New lead alert” renders as a tofu box in the admin font. Replaced with
   `·` (the em dash the rest of the screen uses renders correctly).

Verified in the browser: 7 forms, `3D brief` 2 in 30 days / 2 total / today / fields `area`, `style`,
`budget`; alert switch **on**, roles `sales_manager`, `owner`, quiet hours 21:00–09:00; **0 console errors**.
The two submissions used for the check were posted through the real public endpoint and deleted afterwards
(back to 26 leads / 26 contacts). Tests: `StudioFormsTest` **6 passed (26 assertions)**; Website + sidebar
**340 passed (4,231 assertions)**; Pint PASS 854; PHPStan 0.

## 16.14 Next up — Estimator: what the screen has to write (analysis, 20 Oct 2026)

Read before building, because this screen is not like the others: **the public estimator does not read the
database.** `/estimator/` is a static v1 page that loads `/assets/js/estimator-rates.js` and builds the
calculator from `window.WX_RATES` — 6 services with three rates each, 3 finish levels with a label and a hint,
22 catalogue add-ons (`cat`, `desc`, `unit`, `rate`), and 3 quotation terms. That file is the live price book.

The OS already holds the same six services in `estimator_rates` (minor units: 150.00/sq ft …), and it is what
the bot and the staff estimate builder price from. So the two copies exist today and can already disagree.

**Design for the port (no schema change):**

1. The screen edits the rate book; saving writes **both** — regenerates `estimator-rates.js` (so the public
   page changes) and upserts `estimator_rates` (so the bot and estimates change with it). One save, one truth.
2. The 22 calculator add-ons stay in the file, written back verbatim. The OS `catalog_items` table (78 rows)
   is the *quotation* catalogue at a different granularity — not these add-ons, and not merged.
3. Finish labels/hints and the 3 terms have no OS table. They go into a small settings group
   (`App\Settings\EstimatorSettings`) — the OS's own settings subsystem, not a new table — and the file is
   rendered from it. The first save seeds it from the current file, so nothing is retyped.
4. “Saved rate books” (v2.1's snapshot templates) are the same settings group: name → the four sections. If
   they are dropped instead, it is stated on the screen — a snapshot with nowhere to live is not shown as a
   button that does nothing.
5. The live preview stays: range = essential × qty … premium × qty, selection = chosen level × qty, with the
   Essential ≤ Standard ≤ Premium check the v2.1 screen had.

Open question for the owner (not blocking; default is to keep): **saved rate books** — keep them in settings
(recommended) or drop them?

## 16.15 Screen 7 — Estimator, ported (20 Oct 2026)

`/admin/website/estimator` is live (`docs/audit/evidence/studio-estimator.jpg`). This screen is not like the
others: **the public estimator does not read the database.** `/estimator/` is the v1 page and it builds the
calculator from `assets/js/estimator-rates.js` — six services with three rates each, three finish levels with
a name and a description, 22 catalogue add-ons, and three quotation terms. The file is the live price list.

So one save writes **both**:

1. it regenerates `estimator-rates.js`, which is what the public page loads, and
2. it mirrors the same rates into `estimator_rates`, which is what the bot and the staff estimate builder price
   from (150.00/sq ft is stored as 15000 minor units).

The file is written inside the database transaction. If `estimator_rates` fails to save, the previous file is
written back, so the page can never show prices the bot does not use.

Carried over from v2.1: the 14 ready-made services, saved rate books (named snapshots), “adjust every rate by
%”, the three finish levels with their names and descriptions, the quotation terms box, the Essential ≤
Standard ≤ Premium check (the server refuses, it does not just colour the box), add/remove/reorder, and the
live preview (From = essential × qty, To = premium × qty, selection = chosen level × qty).

Two honest limits, stated on the screen rather than hidden:

- **The 22 catalogue add-ons are not editable here.** The OS `catalog_items` table is the quotation catalogue
  (78 rows at a different granularity), not these calculator add-ons, and the v1 page needs them in the file.
  They are written back exactly as they are — the screen counts them and says so.
- **Removing a service never deletes its `estimator_rates` row.** An old estimate may still reference it; the
  row is switched off (`is_active = false`) and the position is renumbered.

How the file is read matters: it is hand-written JavaScript (unquoted keys, trailing commas, a header
comment), so `json_decode()` cannot read it. `RateBook` scans it with a string-aware reader, and when it writes
it back uses the file's own style — two-space indent, unquoted keys, the original header comment. A round trip
of the live file is byte-for-byte identical in content, proven in the test and again in the browser: after a
save, `/estimator/` still returns 200 with 6 services, 22 catalogue lines and 3 terms.

Saved rate books live in a new settings group (`App\Settings\EstimatorSettings`, one JSON property). That is
the OS settings table, not a new table; spatie/laravel-settings needs the row to exist before the first save,
so `EstimatorSettings::shelf()` creates it.

The v2.1 module `admin-p18e.js` held the Estimator and Forms views and injected their CSS. With both screens
ported it had nothing left, so it is **deleted**; the two chip styles it injected moved into `admin.css`.

Verified in the browser: the screen renders 6 services, 3 finishes, 3 terms and 22 catalogue lines; Save &
publish answers “The estimator page and the bot now use these rates.”; a rate book is saved and deleted; the
public page still loads with 6 services / 22 catalogue / 3 terms; **0 console errors**. Tests:
`StudioEstimatorTest` **8 passed (43 assertions)**; Website + sidebar **348 passed (4,274 assertions)**;
Pint PASS 857; PHPStan 0.

## 16.16 Screen 8 — FAQs, ported (20 Oct 2026)

`/admin/website/faqs` is live (`docs/audit/evidence/studio-faqs.jpg`, editor `studio-faqs-editor.jpg`).

v2.1 called these **FAQ groups**: a named set of questions, reused on pages, by the chat agent, and in Google's
FAQ results. The OS already has the same idea with one store — `content_entries` of kind `faq`, the shared
library a page's FAQ section picks from (`library_ids`). So the screen edits that library rather than growing a
second one:

- every answer in library order, with **the pages that already show it** (read from the page blocks, never
  from a stored list, so a section edited by hand is counted);
- add / edit / delete, reorder with arrows that save immediately;
- delete says **how many page sections were showing it** and leaves those pages' blocks alone — the renderer
  already skips an id that no longer exists.

Two things are stated on the screen instead of implied:

1. **No named groups.** Nested groups would be a new table; one library keeps the promise (“write once, reuse
   anywhere”) and the page still picks which answers it shows.
2. **The chat bot does not read this list.** It answers from the studio knowledge in Settings → Bot, which
   mirrors page text and hand-written answers. v2.1 fed published FAQ groups to its agent; saying that here is
   true, and a link goes to the real control.

A cache bug was caught and fixed while testing: a published page is rendered once and cached until the page's
own `updated_at` changes, and a library edit does not touch any page — so editing an answer left every page
showing the old text until somebody happened to edit the page. Every action now re-renders the pages whose FAQ
section draws from the library (and only those: a page that types its own questions keeps its timestamp). The
test proves both halves, and the browser run proves it end to end — the edited answer appeared on the live home
page with its FAQPage JSON-LD, without the page being touched.

Verified in the browser: add, edit, reorder and delete all work with toasts; “Used on” lists the page; the
public page shows the new wording; **0 console errors**. Tests: `StudioFaqsTest` **11 passed (41 assertions)**.

## 16.17 Screen 9 — Testimonials, ported (20 Oct 2026)

`/admin/website/testimonials` is live (`docs/audit/evidence/studio-testimonials.jpg`, editor
`studio-testimonials-editor.jpg`). **This closes the Content band: nothing in the Website sidebar is on “soon”.**

v2.1 kept testimonials in its content store with a per-card **Live** switch, a star rating, a photo and a
“Pages & heading” dialog that rewrote pages' HTML. The OS keeps one shared review library
(`content_entries`, kind `testimonial`) and a page's Reviews section picks from it — so:

- the screen lists every review **as the client said it**, with client, when, source, and **which pages quote
  it**. “Used on” is the switch that matters: add the review to a page's Reviews section in Pages, or remove it
  there to stop showing it. The page rewritten is the page's own data, not HTML.
- **no per-review Live switch, star rating or photo.** The OS Reviews section renders five stars for every
  review and no photo, so offering those fields would promise something the site never shows. The screen says
  so. (A per-review rating is a template change — a decision, not a form field.)
- the words are stored exactly as typed; the screen warns that it will not tidy a quote.

Same library semantics as the answers: delete reports how many sections were showing it and never edits the
pages, and every change re-renders the pages whose Reviews section draws from the library.

Verified in the browser: two reviews added, an empty one refused with the server's own sentence, reorder saved,
“Used on” filled in once a Reviews section picked them, **0 console errors**; the Reviews section renders the
library rows (text, name, when, source) with a temporary draft page made and deleted for the check. Tests:
`StudioTestimonialsTest` **8 passed (35 assertions)**.

Evidence pages used for the checks were deleted afterwards — 143 pages, 0 reviews sections, 0 content entries,
exactly as before the session started. Sidebar count assertion 57 live destinations.

## 16.18 Where the Content band stands after 16.13–16.17

The six screens the owner asked to run inside the OS are all live and tested (two more, Service pages
and City pages, were later retired in §16.19 — the table keeps their build record):

| Screen | Route | Evidence | Test |
|---|---|---|---|
| Service pages | `/admin/website/services` | `studio-services.jpg` | `StudioServicePagesTest` 9 |
| City pages | `/admin/website/cities` | `studio-cities.jpg` | `StudioCityPagesTest` 6 |
| Forms | `/admin/website/forms` | `studio-forms.jpg` | `StudioFormsTest` 6 |
| Estimator | `/admin/website/estimator` | `studio-estimator.jpg` | `StudioEstimatorTest` 8 |
| FAQs | `/admin/website/faqs` | `studio-faqs.jpg` | `StudioFaqsTest` 11 |
| Testimonials | `/admin/website/testimonials` | `studio-testimonials.jpg` | `StudioTestimonialsTest` 8 |

**Nothing in the Website sidebar is disabled any more.** `WebsiteAdminParityTest` now asserts that every
Content item has a route, so a screen cannot go back to being listed before it works.

What was deliberately **not** built, each for a stated reason:

- per-form alert routing (16.13) — a second routing table competing with the one real rule;
- named FAQ groups (16.16) — the shared library + per-page picking already delivers “write once, reuse”;
- per-review star rating, photo and Live switch (16.17) — the site's Reviews section renders none of them;
- the 22 calculator add-ons editor (16.15) — they belong to the estimator file and are preserved verbatim.

The two shared libraries share one renderer path now: `StudioApi::libraryUsage()` and
`revalidateLibraryPages()` serve both screens, so an edit on either re-renders exactly the pages that quote it.

**Remaining in the 23-screen target:** the older OS-native screens (Blog/Portfolio editors, Page templates,
Media, SEO, SEO agent, Speed, Site health, Theme, Team, Client logos) still have v2.1 depth gaps recorded in the
table in §16.7. Those are next-screen work, not missing destinations.

## 16.19 Woodex OS 1 — the files are the site, and the builder edits them (20 Oct 2026)

Owner decision, 20 Oct: **model = files** and **retire** the four database-only screens. This section records
step 1 (delete the copies), step 2 (point the builder at the real files) and step 3 (retire the four screens).

### Step 1 — the 86 database copies are gone

The v1 HTML files are the website. Every database page I created in the Wave 2 build was a second copy of a
page that already existed as a file, so it could never be the page a visitor saw (the web server answers the
file first). They are deleted, with their blocks and versions:

| | Before | After |
|---|---|---|
| Pages | 143 (demo DB; 144 in the census) | **57** |
| Blocks | 1,450 | 710 |
| Versions | 143 | 57 |

Deleted: 60 service, 12 city, 8 area, 6 project pages — **86 pages, 740 blocks, 86 versions**. What is left:
48 insight, 3 listing, and one each of about, booking, contact, estimator, home, legal.

Rollback is the backup taken immediately before the delete and kept out of Git:
`platform/storage/app/private/os1-backup/database-20261020-143458.sqlite` (4.1 MB) and
`pages-20261020-143458.json` (86 pages, 740 blocks, with per-page version counts).

The delete itself now ships as a command, so the owner's installation gets the same result rather than only
this sandbox's copy:

```
php artisan website:retire-shadow-pages            # report: pages, blocks, versions, what stays
php artisan website:retire-shadow-pages --force    # writes a JSON backup, then deletes in one transaction
```

`RetireShadowPagesTest` proves the report deletes nothing, `--force` removes only the four types with their
blocks and versions, the backup holds the pages it deleted, and a second run is a no-op.

### Step 2 — the builder opens `platform/public/<path>/index.html`

Before this change the builder never touched `public/`: `BuilderApi::load/save` went through
`BuilderPages::document()` and `SaveHtmlPage`, both database-only. A save wrote a row the web server shadowed
with a file — the screen could report “Saved & live ✓” and the page never changed. That is why the first thing
proven here is the file on disk, not a green toast.

Now:

- **`BuilderFile`** (`app/Domain/Website/Builder/BuilderFile.php`) is the only code that touches the site's
  files. It allows a clean `*.html` path inside `public/` (depth ≤ 4), refuses the system folders
  (`admin`, `assets`, `build`, `builder`, `vendor`, `storage`, `livewire`, `api`, `webhooks`, `media`, `img`)
  and anything with `..`. It lists the pages the site actually has (the home page first, then A–Z, each with
  its `<title>`), reads them, hashes them, writes them atomically (temp file + rename) and keeps versions.
- **`SaveHtmlFile`** (`app/Domain/Website/Actions/SaveHtmlFile.php`) is the only code that writes a page. It
  keeps every rule the database-backed save had: a complete document under 3 MB, no `contenteditable`/
  `data-wx-ed` left behind, the script guard for everyone below the owner, and a snapshot before every write.
  The conflict check is now a **hash** of the file as the builder opened it, with mtime as the fallback, so a
  save made in the same second as someone else's edit is still caught. A save the owner confirms with
  `force` overwrites and says so.
- **Versions live in storage, not the database**: `storage/app/private/builder/<sha1(path)>/<stamp>-u<id>.html`,
  40 kept per page. `backups`, `backup_get` and `restore` answer from those files; a restore is itself a save
  (so the current state is snapshotted first).
- **The route takes a path**, `/admin/website/builder?path=about/index.html`; without one it opens the first
  page of the site. `PageEditor` sends a database HTML page to `?path=…` and the pages index links to the
  builder only when a file answers that slug.
- **The builder has no password screen.** `BuilderPages`, `SaveHtmlPage`, the login form and the v2.1
  `/api/admin.php` token flow are deleted. A 401 now shows one panel: “your Woodex OS session ended”, with the
  link to sign in again. (The API already answered `login`/`setup` with “Sign in through Woodex OS”.)
- **Home is not a file yet.** `platform/public/index.html` does not exist; `/` is a Blade/CMS page. The builder
  therefore lists and edits the 141 files that do exist, and home stays CMS until the owner decides whether to
  export the rendered home into `index.html` (that decision changes which page answers `/`, so it is not taken
  silently).

### Proof (real browser, 20 Oct)

`node /tmp/prove-builder.mjs http://127.0.0.1:8080` — owner signs in, opens
`/admin/website/builder?path=about/index.html`, the canvas shows the file's own `<h1>`
(“Design and build, held to one standard”), types into it, presses Escape (finishing the edit, which enables
Save) and clicks Save:

```
toast: Saved & live ✓ (backup kept)
file on disk contains the edit: true
a version was kept before the write: true (1 -> 2)
that version is byte-identical to the old file: true
/about/ now serves the edit: true
```

Screenshots: `docs/audit/evidence/os1-builder-edit.jpg` (the heading editable in the canvas) and
`os1-builder-saved.jpg` (after Save, live). The edit was reverted afterwards by re-syncing the demo, so the
shipped v1 pages are untouched (`git status` clean for `platform/public/**/index.html`).

### Tests and gates

- `tests/Feature/Website/VisualBuilderTest.php` — rewritten for files, 17 tests: list/load/save/versions/
  restore, hash conflict (and `force`), editor markup, script guard (editor vs owner), path allow-list
  (`../.env`, `admin/`, `assets/`, `builder/`), `page_new`, and no password field on the screen. It swaps the
  application's public and storage paths for a scratch site, so no shipped page is written by the suite.
- `pest tests/Feature/Website tests/Feature/P10SidebarHubsTest.php` → **371 passed (4,355 assertions)** before
  the retire; **302 passed (4,125 assertions)** in `tests/Feature/Website` after it, plus 57 in
  `WebsiteAdminParityTest` + `P10SidebarHubsTest`; **347 passed (4,257 assertions)** with `HomeFileTest` and
  `RetireShadowPagesTest` added; **346 passed (4,247 assertions)** after step 5.
- `pint --test` → **PASS 855 files**; `phpstan analyse` → **[OK] No errors** (after the retire).

### Step 3 — the four screens are retired

Service pages, City pages, FAQs and Testimonials only ever wrote database rows. With the rows gone and the
site served from files, they could not put a single word on a page: a sidebar destination that cannot reach
the website is a lie. So they are gone, not hidden:

- `WebsiteStudioController::SCREENS`, the `routes/admin.php` loop and `AdminNavigation` (items and
  `FULL_DOCUMENT`) list only Client logos, Blog, Portfolio, Forms and Estimator of the ported screens.
- The four v2.1 modules left `studio.blade.php` and the repository: `admin-services.js`, `admin-cities.js`,
  `admin-faqs.js`, `admin-testimonials.js`.
- Their tests are deleted (`StudioServicePagesTest` 9, `StudioCityPagesTest` 6, `StudioFaqsTest` 11,
  `StudioTestimonialsTest` 8).
- `WebsiteAdminParityTest` asserts the four URLs **404**, that no Content item is listed without a route, and
  that Forms, Estimator and Client logos still answer 200. `P10SidebarHubsTest` counts **53** live
  destinations (was 57).

Editing a service page, a city page, an FAQ or a testimonial now means opening the page's file in the page
builder (§16.19 step 2) — the one editor the owner kept.

### Step 4 — the home page is a file (owner answer: yes)

`/` was the last address the builder could not open. It is now `platform/public/index.html`, exported from
the page the CMS was serving, so a visitor sees exactly the design that was already live — nothing was
restyled, and the export is what the owner approved.

- The export was taken from the running site, not from `woodex/index.html` (that is the older v1 source):
  same title, same hero, same H1 ("Spaces shaped by purpose and identity"), same sections.
- Two things were removed that belong to the server, not the page: the sandbox's `__preview-beacon` script
  and its `<noscript>` pixel. `HomeFileTest` asserts neither can come back, along with the absence of Blade
  source (`<?php`, `__contextArgs`), `localhost` and `127.0.0.1`.
- **A real bug surfaced while exporting**: the JSON-LD block on every Blade-rendered page contained compiled
  Blade garbage — `"<?php $__contextArgs = []…"` — because `@context` inside the `json_encode([...])` was
  parsed as a Blade directive. It is fixed at the source (`site/partials/head.blade.php` now writes
  `'@'.'context'`), and the exported home carries valid `"@context": "https://schema.org"`.
- `platform/public/.htaccess` already had `DirectoryIndex index.html index.php`, so the file answers `/`
  before Laravel on the Hostinger Apache deploy; the comment that said the root was CMS-served is corrected.
- The builder lists it first as `/` (`index.html`) and edits it like any other page.

**Consequence to decide next:** the CMS home row and the **Hero slides** screen still exist, but they no
longer drive `/` — the file does. That is the same "a screen that cannot reach the site" problem the four
retired screens had. The recommendation is to retire Hero slides and the home row (backup first) so there is
one home, not two; the alternative is to keep the row as a draft staging area, which nobody has asked for.

### Step 5 — Hero slides and the studio Client logos screen are retired; the home row is deleted

Owner answer: retire. With `/` served by `public/index.html`, the Hero slides screen and the home row it wrote
could not put a word on the live page — and **Client logos** turned out to be the same: `logos_save` writes a
`clients` block on the home row (`StudioApi::clientsBlock`), so once the row is gone it would create records
that no page renders. It is retired with it. **The logo records themselves stay**: the shared content library
(`/admin/website/content`, `ContentLibraryIndex`) still lists and edits the client-logo entries, which is where
the earlier decision put them.

What went:

- `Hero slides` and `Client logos` out of `AdminNavigation` and `FULL_DOCUMENT`; their routes, the
  `HeroSlidesIndex` component and view, and `admin-logos.js` / `admin-hero.js` are no longer loaded by the
  studio document. `SCREENS` is now Blog, Portfolio, Forms, Estimator.
- Their tests are gone (`PageBuilderLiveTest` hero cases, three `WebsiteAdminParityTest` cases, five
  `StudioSeamTest` logos cases). `StudioSeamTest` now opens the Forms screen as its ported fixture.
- `WebsiteAdminParityTest` asserts both URLs **404** and both labels are absent; `P10SidebarHubsTest` counts
  **51** live destinations (was 53).
- The home row is deleted with its blocks: `website:retire-shadow-pages --force --home` deleted **87 pages,
  752 blocks, 87 versions** on the demo database (86 shadows + home), backing up first to
  `storage/app/private/os1-backup/shadow-pages-*.json`. Pages now: **56**.

### The home file's assets no longer change name on a rebuild

`public/index.html` links `/build/assets/site.css` and `/build/assets/site.js`. A content hash there would
have broken the home page the first time anybody ran `npm run build`, because no Blade template can rewrite a
static file's `<link>`. The site entry now keeps stable names in `vite.config.js`; the admin entry and every
other asset keep their hashes, and Blade still reads the manifest for those. Verified by rebuilding: the
manifest maps `resources/css/site.css → assets/site.css` and `resources/js/site.js → assets/site.js`, and the
home renders identically from the new names.

### Step 6 — the URL the retire run broke, and which screens can actually reach the site

**What broke.** `website:retire-shadow-pages --force --home` deleted `/furniture/`. The row was a published
service page whose file had never been written, so the row *was* the page — the command assumed every retired
row was a copy of a file. The URL is linked from the v1 pages and answered 404 for a day.

The command now has two guards before it deletes anything:

- **A published page with no file is exported first** (`site.page` render, media made root-relative like the
  rest of the site), then its row is deleted. `/furniture/` was restored this way and is now
  `platform/public/furniture/index.html` — header, hero, services, process, gallery, FAQ and form, the page the
  row rendered.
- **A draft with no file is kept**, not exported: exporting it would publish it.
- Rows an earlier run only soft-deleted are force-deleted, so a second run finishes the job; a soft-deleted
  row still held the unique slug and blocked a restore.

`RetireShadowPagesTest` covers all three (8 tests), plus the `--home` opt-in and the backup JSON.

**The sitemap was reading the wrong truth.** It was built from the `pages` table, so after the 86 rows were
retired it listed **57 of the 143 pages the site serves** — every service, city, area and project page was
missing from the sitemap while still answering 200. It now reads the files (`BuilderFile::pages()`), skips one
that marks itself `noindex`, still lists a published CMS page that has no file (SiteRouter answers those), and
is cached on the newest file mtime, the page count and the newest row. Verified: **143 URLs, 143 files, no
difference either way.**

### Step 6b — the SEO screens read the site, and the row-only screens are gone

**The audit was told about a fifth of the website.** SEO, SEO agent and Site health all read `SeoAudit`, which
walked the 56 `pages` rows. The site had 143 pages. So the screen could say "everything checks out" about a
site whose 86 service, city, area and project pages it had never opened — the same class of lie as the page
editor's Publish.

`FileAudit` replaces it: one pass over `public/` through `BuilderFile::pages()`, reading each file's `<title>`,
meta description, H1 count, body words, `noindex`, and every `href`. It finds:

- **per page** — no/long/short/repeated title, no/long/repeated description, no or several H1s, images without
  alt text (inside `<main>`: the logo's `alt=""` is decorative on purpose), thin content, `noindex`.
- **site-wide** — internal links that lead nowhere, pages nothing links to, redirects pointing at no page.
- **143 pages checked, 0 broken links, 0 orphans, 0 stray redirects** on the demo site. The one real finding is
  **83 content images with an empty `alt` across 78 pages** — a content fix in the files, not a bug.

The three screens now show it: Site health is seven checks over the files, SEO lists every page's listing with
its file's real lengths, SEO agent is a worklist with a rule filter. `SeoAudit` and `ProposeSeoCopy` (the row
audit and its AI proposal) are deleted with them — nothing read the result.

**Retired with it** — the screens whose records no file page renders: **Menus** ("Header & footer") and
**Team**. Their models and records stay (the importer and the Blade layout still read them, and the Blade
layout still answers a CMS page that has no file), but the destinations are gone: the nav is in the files, and
a team section is written in the page. `/admin/website/menus` and `/admin/website/team` answer 404.
**Sidebar: 47 destinations** (was 49).

**Still open — one decision.** **Website Settings** holds the tracking IDs (GA4, GTM, Clarity, Search Console
verification) and the header/footer words. Both reach only a CMS-rendered page, and every page is a file, so
**analytics is loaded by nothing on the live site today** — no IDs are set, which is why nobody has noticed.
Two honest ways forward, and it needs the owner's call:

1. **Make tracking work on the files**: a `/site/tags.js` route that renders the IDs when they are set, plus one
   `<script>` line in each of the 143 files (a scripted, additive edit — no restyling). The screen then tells
   the truth, and a future GA/GTM setup just works.
2. **Retire the screen**: the file site has no tracking, and the OS stops pretending it can add one.

Recommendation: (1). It is small, additive, reversible, and analytics is the one thing on that screen that a
website genuinely needs.

**Which screens reach the site** (audited one by one):

| Screen | Reaches the live site? |
|---|---|
| Page builder (`/admin/website/builder`) | **Yes** — reads and writes `public/<path>/index.html` |
| Media | **Yes** — files serve `/media/{id}/…` |
| Redirects | **Yes** — `SiteRouter` 301s; table currently empty |
| Theme builder | **Yes** — `--wx-*` tokens become `/theme/<hash>.css`, linked by every page |
| Forms | **Yes** — posts to `/api/forms.php` and lands in `leads` |
| Estimator | **Yes** — writes `public/assets/js/estimator-rates.js` |
| Pages + Page editor | **Only for a row with no file.** All 56 rows have files, so today every save is shadowed — yet Publish still says “is live at /…”. A page with no file is rendered by `SiteRouter`. |
| Page templates, Section library | **No** — they create rows and blocks for the editor above, which the files shadow |
| SEO overview, SEO agent, Site health | **No** — they read `Page` rows and `SeoAudit` over rows: 56 pages, not the 143 that exist |
| Speed | Partly — it runs PageSpeed against `Page::canonical()`, a URL that does exist |
| Menus, Team, Header & footer + tracking | **No** — they feed `layouts/site.blade.php`, which no file page uses |
| Content library | **No** — records for the retired FAQ/testimonial/logo screens; kept as the logo store |

**Decided and shipped (owner, 20 Oct): the Pages screen is an index of the files, and the builder is the only
page editor.** The screen was the last one that claimed a live result it could not deliver — every row it showed
had a file, so its Publish changed nothing a visitor saw while the toast said “is live at /…”.

- `PagesIndex` now lists `BuilderFile::pages()`: 143 files, the section each sits under (home, top-level,
  insights, projects, lahore), size, and when it changed. A row opens the builder on that file, the live page, or
  takes the page **offline** — which moves the file to `storage/app/private/site-retired/`, after a version
  snapshot, so it comes back by moving it home.
- The block page editor, **Page templates** and **Section library** are gone with their routes, nav items, views
  and 13 test files that only exercised them. `/admin/website/pages/{page}` and `/pages/new` redirect to the
  builder on that file, so old bookmarks still land somewhere that can change the page.
- The sidebar is **49** destinations (was 51).

### What is still open

- **The screens that still read rows**: SEO overview, SEO agent and Site health audit the 56 `pages` rows, not
  the 143 files — so they describe a fifth of the site. Menus, Team, Header & footer and tracking feed
  `layouts/site.blade.php`, which no file page uses. Making the first group read the files and retiring the
  second is the next piece of step 6; neither can mislead the owner the way the page editor did, so neither is
  urgent.
- `StudioApi` still answers the retired screens' actions (`cms_faq*`, `cms_testimonial*`, `cms_city*`,
  `logos_*`, `pages`, `cms_page_new/duplicate`) with no screen calling them. Removing them is its own change,
  because `admin-templates.js` still uses `cms_page_kinds` and `admin-fields.js` uses `bapi_load`.
- **Dead endpoints**: `StudioApi` still answers the retired screens' actions (`cms_faq*`, `cms_testimonial*`,
  `cms_city*`, `pages`, `cms_page_new/duplicate`) even though no screen calls them. Removing them needs care —
  `admin-templates.js` still uses `cms_page_kinds` and `admin-fields.js` uses `bapi_load` — so it is its own
  change, not a deletion sweep.
- The editor still cannot create a *folder* that the site's navigation links to; `page_new` writes
  `<folder>/<slug>/index.html` and that is enough for now.

### Step 7 — the home file is the v1 page again (21 Oct 2026)

**Step 4 was wrong, and this corrects the record.** It said the home export was "the page the CMS was serving",
"exported from the running site", so "nothing was restyled". Only the first half was true: the running *sandbox*
served the CMS render. The owner's live site served the **v1** page, and the file written back to
`public/index.html` was the Tailwind render — a different design with a one-shot hero and a different nav.

The owner saw it as two frontends in one site: `/` (new design, "Cost guides", "Start your project") beside
`/3d-visualization/` (v1, "Woodex Studio", "Get a quote"). The site itself was the evidence:

| | v1 | new export |
|---|---|---|
| Pages in `public/` | **141** (`site-p21.css` + `v1-p21.css`) | **2** — `/` and `/furniture/` (`build/assets/site.css`) |
| Hero | six slides (INTERIORS … CRAFT) | one static hero |
| Nav | Home · Woodex Studio · Services · Projects · About · Insights · Contact · Get a quote | Services · Cost guides · Projects · About · Contact · Start your project |

**Fixed:** `resources/legacy/home-v1.html` (the live page, kept since `228dcc3`) is moved to
`platform/public/index.html` — `git mv`, so there is one home and its history follows it. The file needs no
new asset: every path it references (`site-p21.css`, `v1-p21.css`, `site.js`, `theme.css`, `assets/img/*`)
already exists, and it contains **no `/media/` and no `/build/`** reference, so it is identical on every
install. `HomeFileTest` still passes: no Blade source, no `localhost`, no beacon.

Verified in the preview: `#` slider has 6 slides and works (next → WORKPLACE, dot 6 → CRAFT), no console or
network errors, title/description/canonical unchanged. Gates: `tests/Feature/Website` **210 passed (2138)**,
`P10SidebarHubsTest` **42 passed** — 252, unchanged.

**`/furniture/` is deleted — owner's answer, 21 Oct 2026: delete it outright.** It had no v1 original (it was
created as a CMS page while the site was ported), no v1 page linked to it, and its body was largely the same
wardrobe copy as `/wardrobe-design/`. It went in three places, because one is not enough:

- **the file** — `platform/public/furniture/index.html`, deleted with `git rm`;
- **the row** — migration `2026_10_21_090000_remove_the_furniture_page` force-deletes slug `/furniture/` (blocks
  and versions cascade). Without it the SiteRouter would render the row — in the CMS design — the moment the
  file was gone, which is exactly the page the owner asked to remove;
- **the deploy** — `tools/deploy/after-composer.php` now unlinks the stale file on the server, because Hostinger
  keeps files the repository deleted (D-56). The same hook used to **delete `public/index.html` on every
  deploy** (the old Phase 3 cutover): that block is removed, or the next deploy would have taken the restored
  home away again.

The words are not lost: `database/data/website-bundle/pages.json` is the 17 Oct export and still holds the page
(it is only loaded on a fresh install or by an explicit `woodex:import-website`). Reviving it takes a deliberate
act, not a deploy.

Verified: `/furniture/` **404**, `/furniture` 301 to it, `/wardrobe-design/` 200, sitemap **142 URLs**, Pages and
Site health **142**, no console errors on the pages that remain. Gates: **252 passed (2247)**.

### Step 8 — the CMS renderer is retired; a page with no file is a 404 (21 Oct 2026)

Owner's answer: retire it. The renderer was what made a second frontend possible. A page was a file **or** a row,
the layout drew the row when the file was missing, and that is how `/` and `/furniture/` came to look different
from the other 141 pages. With every page now a file, the row renderer had no page left to draw — but it would
still have drawn any row that appeared later.

**Deleted:** `resources/views/site/page.blade.php`, its 20 section views, its 5 partials, the
`layouts/site.blade.php` layout and `PageRenderer`. `StudioApi` loses `bapi_load` (nothing called it; the builder
reads files), and `SiteRouter` keeps only what it still owns: the one canonical 301 for a missing trailing slash
and the old website's 301s. A URL with no file is a 404; no database row can answer one.

**The 404 is a page of its own now** — `resources/views/errors/404.blade.php`, built from the v1 chrome (the same
header, mobile panel, footer and `wx-crit` stylesheet every page carries, wrapped in `@verbatim`) plus
`public/assets/404.css` for the band. It uses no new design: the header, buttons and footer are the site's.

**`/furniture/` left the URL map too.** It was the map's one page the old site did not have, and the parity test
counts the map: with the page deleted, the contract was asserting a URL that answers nothing. The map is 142 URLs,
every one with a file, and `LegacyUrlParityTest` now reads `public/` — the same thing the web server reads — instead
of asking `SiteRouter` to render a row.

**`RetireShadowPages` no longer exports.** It cannot: the renderer that wrote the file is gone. A published page
with no file is now **kept** and reported (it is the only copy), and a draft is kept as before.

**Tests that went with the code:** `SiteRenderingTest` (5 — the section library), `WebsiteHomeTest` (7 — the CMS
home), and 21 render tests out of `CmsPageTest` (8), `ContentLibraryTest` (2), `HeaderFooterEditorTest` (4),
`HomeCutoverTest` (1), `MenusTreeTest` (1), `SliderSectionTest` (2) and `WebsiteAdminTest` (3). Adapted rather than
deleted: `LegacyUrlParityTest` (file-based), `LegacyUrlMapTest` (142), `RetireShadowPagesTest` (keeps, not exports),
`ThemeTokensTest` (the home file links `/assets/theme.css`), `WebChatWidgetTest` (the loader lives in `site.js`,
which the files share).

Gates: `tests/Feature` + `tests/Unit` **1195 passed, 1 skipped (6385 assertions)**; Pint clean; PHPStan no errors.
Preview: `/` 200 (v1), `/furniture/` 404 with the new page, mobile menu opens, no console errors.

**What this leaves open, in the same class:** Website Settings still holds the tracking IDs and the header/footer
words, and they were read by the layout that is now deleted — so they reach nothing. The chat widget is fine: the
files' one shared script (`/assets/site.js`) already loads `/chat/widget.js`, and the switch changes what that
script serves. Tracking has no such loader; the earlier recommendation stands (a `/site/tags.js` route plus one
script line per file), or retire the screen.

### Step 9 — tracking reaches the files (21 Oct 2026)

Owner's answer: continue with the recommendation. **Website Settings' tracking IDs now reach the site.**

The renderer that used to print the tags is gone (step 8), and the pages are files the web server answers before
Laravel runs — so no switch in the admin could change them. The files needed one thing they could share:

- **`GET /site/tags.js`** (`SiteTagsController` + `resources/views/site/tags.blade.php`) answers with GA4,
  Google Tag Manager or Clarity, using the IDs already on the screen. **Nothing is emitted until an ID is set**,
  nothing is emitted while the switch is off, and **nothing for signed-in staff** — the same three rules the
  Blade partial had. `Cache-Control: no-store`, so a saved ID is live on the next page load, not in five minutes.
- **All 142 page files load it** — one line after their shared `site.js`:
  `<script src="/site/tags.js" defer></script>`. The edit is scripted and additive: no markup, text or styling
  changed, and `HomeFileTest` now knows `/site/tags.js` is answered by the app like `/media/` and `/theme/`.
- **Tag Manager wins over GA4** when both are set (one container, no double counting), and Clarity loads
  alongside either.
- **Search Console verification cannot travel this way.** Google's verifier reads the meta tag without running
  JavaScript, so the field is kept for the record and the screen tells the owner to verify with a DNS record.

**The header/footer card is now labelled honestly**: every page is a file and carries its own header and footer,
so those words and switches no longer reach the site. The fields are kept for the record; retiring them is its
own small change.

Verified: `/site/tags.js` answers `No tracking is configured` with no IDs; with `tracking_enabled` and a GA4 ID it
answers the gtag loader with that ID; reset returns to the no-op. `SiteTagsTest` (8 tests) covers the file link,
the empty answer, GTM-over-GA4, GA4 alone, Clarity, the off switch, staff exclusion and the cache header.

Gates: `tests/Feature` + `tests/Unit` **1203 passed, 1 skipped (6553)**; Pint clean; PHPStan no errors.

### Step 10 — the settings screen loses what the files cannot show (21 Oct 2026)

Step 9 left one card labelled honestly rather than retired, because retiring it was its own change. This is that
change. **The eight header/footer fields are gone.**

- **Why they could not work:** the keys (`site.header_cta_label`, `site.header_cta_url`, `site.social_links`,
  `site.footer_tagline`, three `site.footer_show_*` switches and `site.footer_copyright`) were read only by
  `layouts/site.blade.php`, which went out with the page renderer in step 8. Every page is a file with its own
  header and footer, so the form was inviting edits no page could show.
- **What went:** the properties, the mount defaults, the validation rules and the fill block in `SiteSettings` and
  `WebsiteSettings`; the "Header & social" and "Footer" cards in the settings view; and `HeaderFooterEditorTest`.
- **Stored keys are deleted, not orphaned** — `2026_10_21_100000_remove_the_header_footer_settings.php` calls
  `deleteIfExists` on each key. `down()` is empty and says why: the layout that would read them is gone, so
  restoring the rows would restore nothing. They were never read by a page, so no live output depends on them.
- **`save()` no longer flushes the page cache.** Nothing on the remaining screen changes a file, so the flush only
  threw away rendered pages for nothing.

The screen now has two cards: **Tracking** (which reaches the site through `/site/tags.js`, and the DNS note for
Search Console) and **What is live**. Both describe something the site can actually produce.

**The chat switch was checked, not assumed.** It reaches the files too: `site.js` loads `/chat/widget.js` on every
page and the controller answers `/* Woodex web chat is switched off. */` while the switch is off — 38 bytes, then
nothing. With the switch on it serves the bubble, and the three `/api/chat/*` endpoints refuse requests while it is
off. The stale comment claiming the deleted layout injected the same script is corrected, and the page description
now says no analytics tag is emitted rather than nothing is loaded (the no-op request is real).

Verified: the migration ran; `/admin/website/settings` answers 200 with "Tracking" present and "Header & social" /
"Footer tagline" absent, no console errors.

### Step 11 — the content images carry their own alt text (21 Oct 2026)

The bundle already held alt wording for all 105 media rows; the page files carried empty `alt=""` on 83 `<img>`
inside `<main>`, across 78 of the 142 pages. Each one now takes the wording stored on its own media row, matched
by filename.

- **83 images on 78 files filled; 0 filenames unmatched.** The edit is attribute-only — no tag, text, class or
  styling changed, and no other `<img>` was touched.
- **Not every one is a screen-reader fix:** 63 of the 83 sit on images marked `aria-hidden`, i.e. deliberately
  decorative. The wording is correct and harmless there; the count matters because an audit tool counts an empty
  alt either way.
- The filenames are the same `img-*.webp` set the bundle lists, so a re-import keeps the match.

Census against the owner's screenshots (taken from a different instance): pages **142 vs 145**, media **105 vs 113**,
missing alt in `<main>` **0 vs 112**, redirects **0 vs 36**; `404.html` / `500.html` / `503.html` do not exist in
this workspace, and nothing here was changed to match those numbers.

Gates: `tests/Feature` + `tests/Unit` **1202 passed, 1 skipped (6543)** — exactly the one test and ten assertions
that `HeaderFooterEditorTest` took with it; Pint clean (839 files); PHPStan no errors.

### Step 12 — the live sitemap checked against the files (21 Oct 2026)

The pre-deploy check, run before any further build work: every URL Google is told about today, against the files
that will replace it. Full evidence in **`docs/audit/19_URL_PARITY_2026-10-21.md`**; the sitemap itself is kept
at `docs/audit/evidence/live-sitemap-2026-10-21.txt`.

- **143 live URLs, 142 files, one loss: `/furniture/`** — deleted by the owner's decision, linked from nowhere in
  the site, and the app's own sitemap (142 `loc`s) leaves it out.
- **Titles, descriptions and canonicals: 142 / 142 identical** to the CMS export; 142 distinct titles; one `<h1>`
  per page; no `noindex`; no duplicate canonical.
- **11,588 internal links, 153 distinct paths, 0 unresolved.**
- **The 36 redirects the other instance reports do not exist here** and are not in the repository's data; no
  sitemap URL depends on them. Keeping them would need an export from that instance before a database deploy.
- **One defect found and fixed the same day:** the sitemap cache key omitted the host, so a document built for one
  domain was served to the other for up to a day. The host is now part of the key, and a test builds as
  `woodex.com.pk`, asks as `admin.woodex.com.pk` and expects each `<loc>` to carry the asking host.
- **The 36 redirects are to be exported from that instance before deploy** (owner's answer): `from_path`,
  `to_path`, `code` per row, from `/admin/website/redirects` there or
  `SELECT from_path, to_path, code, note FROM redirects ORDER BY from_path;`. They are imported and asserted to
  answer 301 here; none of the 143 sitemap URLs depends on them.

### Step 13 — the Media screen's alt text reaches the pages (21 Oct 2026)

Step 11 put the CMS's own alt wording into the files; the screen that edits that wording still wrote only to a
database row and answered "Saved." for something the site never showed. The owner chose to make the screen reach
the files rather than retire it.

- **`App\Domain\Website\Builder\PageImageAlt`** — finds every `<img>` that shows an image by its **stem**
  (`img-abc` also matches the `-480`/`-960` variants used in `srcset`), replaces the `alt` value or adds one when
  the tag has none, and leaves the rest of the tag untouched. Matching is by filename, because the pages embed
  `/assets/img/img-*.webp` while the library serves `/media/{id}/{name}`.
- **`MediaLibrary::updateMeta()`** saves the row, gives the sibling rows of the same photograph the same wording
  (one image, one alt), then writes the files and reports what happened:
  *"Saved. The alt text now appears on N pages."* — or, when nothing shows it,
  *"Saved. No page shows this image yet, so the site itself has not changed."*
- **`MediaLibrary::delete()`** now refuses when a **page file** shows the image ("used on N pages") instead of
  reading `pages`/`page_blocks` rows the site no longer renders; the page cache flush is gone with them.
- The field hint says what the button does: edit, then press **Save** to write it into the pages.

Verified in the browser: on `/admin/website/media`, editing one image and pressing Save answered *"Saved. The alt
text now appears on 7 pages."*, and exactly 7 page files carried the new wording afterwards (restored from the
repository copy). A site-wide image reported 142 pages / 261 tags. Tests: `WebsiteAdminTest` gains a file-level
test (replaces the alt on two pages, including a tag that had none, leaves a different image alone, syncs the
variant row) and a delete test (refuses the used image, deletes the unused one).

Gates: `tests/Feature` + `tests/Unit` **1204 passed, 1 skipped (6555)**; Pint clean (840 files); PHPStan no errors.

### Step 14 — every image the pages use is now in the Media library (21 Oct 2026)

After step 13 the screen could edit alts, but it only knew **62 of the 95 images the pages show**: the theme's own
files (`/assets/img/clients/…`, `/assets/img/svc-…`) had no row, so the page's "every photograph on the public
website" was not true and those 33 could not be found or edited at all. The owner chose to bring them in.

- **`woodex:import-site-media`** scans the page files for `<img>` tags that load `/assets/img/…`, groups the
  variants by stem, takes the alt the page already carries, and creates one row per image:
  `disk = site`, `path = assets/img/…`, dimensions from the file, folder `site/img` or `site/clients`.
  It adds only what is missing — a second run reports `0 added, 95 already there`.
- **`Media::url()`** answers a `site` row with its public path (`/assets/img/clients/attock.webp`), so the grid
  shows the real file; nothing is copied into storage and the pages keep loading the file they already load.
- **The rows are managed, not deleted**: a theme image shows a "theme file" tag and "Shipped with the design"
  instead of Delete, because the file belongs to the design and the pages would break without it.
- **It runs with the deploy**: `woodex:update` (the owner's one-click path and the SSH command) now calls it, and
  the GitHub Actions deploy runs it after `migrate`. Idempotent, so repeated deploys cost one scan of 142 files.

Verified in the sandbox: the import added **33 rows (62 + 33 = 95 images, 0 missing alt)**, and the Media screen
answered 200 with 138 rows, the new ones first and tagged "theme file". Editing a theme image in the browser
answered *"Saved. The alt text now appears on 1 page."* and that page's file carried the new wording (restored
afterwards). Tests: `SiteMediaImportTest` (adds 33-style rows, matches variants by stem, files the folder, does
not duplicate a known image, second run adds nothing).

Gates: `tests/Feature/DatabaseUpdatesTest.php` + `tests/Feature/Website` **205 passed (1848)**; full suite
**1206 passed, 1 skipped (6568)**; Pint clean (842 files); PHPStan no errors.

### Step 15 — the Content screens checked against the files (21 Oct 2026, audit only)

Same question as steps 10 and 13, asked of the Content band. Full evidence in
**`docs/audit/20_CONTENT_SCREENS_VS_FILES.md`**.

- **Blog and Portfolio change nothing a visitor can see.** Their `cms_*` actions and `page_new` write `pages` and
  `page_blocks` rows; `StudioContent` has no file write at all. A published row created exactly as the screen
  creates one appeared in `/sitemap.xml` and answered **404** (removed after the check).
- **Content library is empty and unread**: `content_entries` holds 0 rows, the only reader is `StudioApi`, and the
  route still answers though the nav dropped it on 20 Oct. The logos now live in Media (step 14).
- **Forms is honest** — it reads `leads`, writes nothing. **Estimator is wired** — it writes
  `assets/js/estimator-rates.js`, which `/estimator/` loads.
- **The sitemap can advertise a 404**: `SitemapController` still lists published rows without files, under a
  comment that says `SiteRouter` answers them. Since step 8 it does not.

Waiting on the owner: retire Blog, Portfolio and Content library, and make the sitemap list files only — or rewire
the two editors to write page files.

### Step 16 — Blog and Portfolio write the page files (21 Oct 2026)

The audit in step 15 found the two editors writing rows only: a "published" article appeared in the sitemap and
answered 404. The owner's decision was **not** to retire them — they had to publish.

- **`ArticleFile`** renders a post/study into `public/<folder>/<slug>/index.html` using an existing page of the
  same kind as the shell, replacing only the head metadata and `<main>`. Same design, same classes, no restyle.
- **`cms_save`** (when published), **`cms_published`** and **`cms_status`** write the file; draft moves it to
  `storage/app/studio-retired/` instead of deleting it; a rename retires the old address; `cms_delete` retires
  then deletes the row.
- **The sitemap lists files only** (rows can no longer hand Google a 404) and its `<loc>`s are canonical on every
  host.
- Verified in the sandbox: publish → file + 200 + listed once; draft → 404 and the file kept; delete → row gone;
  sitemap back to 142. Browser render of a published article checked against the v1 design.
- Tests: `tests/Feature/Website/ArticleFileTest.php` (7), `CmsPageTest` updated (files-only sitemap, canonical
  URLs). Gates: `tests/Feature/Website` **202 passed (1853)**; full suite **1216 passed, 1 skipped (6626)**;
  Pint clean (844 files); PHPStan no errors.

Waiting on the owner: the same question for **Content library** (0 rows, no reader) — retire it, or give it a
reader. And the **36 redirects** still need the owner's export before a DB deploy.

---

### Step 17 — the Redirects screen tells the truth (21 Oct 2026)

The last launch-critical screen that could claim a result it cannot produce was **Redirects**. The site is its
files and the web server answers a file **before** the app runs, so a redirect whose old URL is still a page
never fires — and the screen used to store it and say "Redirect added." anyway. Its third stat was also stale:
"Old URLs to map = 143 − total", a number from the row-rendering days.

- **A redirect whose old URL is still a page is refused**, with the reason and the way forward: *"…is still a
  page: its file answers first, so a redirect from it would never fire. Delete the page in All pages, then add
  the redirect."* The row is not written.
- **Rows that cannot fire are marked.** A row that reached the table another way (an SQL import, a page file
  added later) is counted in a new **Not firing** stat, shown in a warning banner and tagged *not firing* in the
  list — the screen never counts a dead row as a working 301.
- **The import says what it skipped.** Old URLs that are still pages are skipped and named (up to three);
  unreadable lines are counted; rows whose new URL has no page file are imported but flagged as worth checking.
  A **spreadsheet paste (tabs)** is read as well as a CSV — that is how the 36 old rows will most likely arrive.
- **A missing target is called out** after adding: *"Redirect added, but there is no page file at /… — check the
  new URL."* The redirect is still stored, because the target may be an app page and a typo is the owner's call.

Verified in the sandbox: `/zz-old-page/` (no file) → **301 → /about/**, hits counted; `/about/` still answers
**200** from its file, and the screen showed *Not firing: 1* with the row tagged (both test rows removed
afterwards; 0 rows, `/zz-old-page/` back to 404).

Tests: `WebsiteAdminTest` — refuses a redirect from a live page and names the reason; an address with no file is
stored and the screen reports the target state; an import skips the still-a-page line, reads a tab-separated
line and keeps its note; a row that cannot fire is counted and tagged. **12 passed (59 assertions)** in that
file; `tests/Feature/Website` **205 passed (1867)**; full suite **1219 passed, 1 skipped (6640)**; Pint clean;
PHPStan no errors.

Waiting on the owner: the **36 rows** — paste the export into the import box (`old, new, code, note`, or the SQL
result as TSV) and the screen will tell you exactly which old URLs are still pages.

### Step 18 — the ported admin's other screens are behind a door that says no (21 Oct 2026)

Found while checking the Content band against the files (step 15) and closed here: the copied v2.1 app in
`assets/admin-v21/` still carries **its own screens** — Service pages, City pages, Page templates, FAQs,
Testimonials, Team, Client logos, Header & footer, Section library, Hero slides — and its router reads the URL
hash. The OS sidebar hides them, but a typed hash or a saved bookmark reached them, and several rendered live
editors (`FAQ groups`, `Testimonials`, `Service & site pages`, `City pages`, `Page templates`, `Team`,
`Header & footer`, `Section library`, `Hero slides`). Every one of them edits **rows**, and rows are not the
website (docs/audit/20) — so each was a screen that could appear to save something and change nothing a visitor
sees.

**What the seam does now** (`wx-embed.js`, which loads before their router):

- Only the four ported screens are reachable — **Blog, Portfolio, Forms, Estimator**. A hash naming any other
  screen is put back *before* their router sees it, and the person is told, in the OS strip, exactly why:
  *"“FAQs” is the old admin’s screen: its edits would not reach the website, which is its page files. What it
  did lives in All pages and Page builder in Woodex OS."*
- The message is a **line of its own** under the strip: the screen is pushed down by its height, so nothing is
  covered (a first version floated over the page and hid the h1; fixed and re-checked).
- The article editor inside Blog (`#/post/<id>`) and the study editor inside Portfolio (`#/study/<id>`) still
  open — they are the ported screens' own detail views, not separate screens.
- A bookmark that names a refused screen boots on the screen whose OS URL it is, not on Blog.

Verified in the preview: `#/faqs` typed on the Blog page → hash back to `#/blog`, banner naming FAQs, heading
still visible; `#/global` likewise; `#/post/229` opens the article; Portfolio, Forms and Estimator answer as
themselves.

Also fixed on the way: the Blog and Portfolio lists drew **an empty grey square for all 48 imported articles**
because they have a hero block (`image_id`) but no `featured_image_id`. The card now falls back to the hero, so
the list shows the article's own artwork (`/media/150/…`), and the editor's hero carries the **alt from the
Media row** instead of an empty string.

Tests: `StudioSeamTest` — only the four screens are named as reachable, the refusal message exists, and each of
the four renders; `StudioContentTest` — the list card is the hero's media URL and the editor's hero alt is the
library's. Gates: `tests/Feature/Website` **207 passed (1879)**; full suite **1221 passed, 1 skipped (6652)**;
Pint clean (844 files); PHPStan no errors.

### Step 19 — Publish now publishes, and a page never says Live over a 404 (21 Oct 2026)

The open question from step 18's session: clicking **Publish now** in the article editor sent `cms_save`
and nothing else, left the button disabled and the article a draft. Three separate faults were stacked
behind that one silent button; all three are closed here.

**1. The wait was real, the silence was the bug.** In the preview runtime a publish is *two* server calls,
and the first one queues behind whatever else is running. Measured: with the image picker open, ~105 tiles
load at once and the save queued for **~26 s** before it even reached PHP. The write itself answers in
~300 ms. The editor only dimmed the button, so it read as dead. The button now says **"Publishing…"** from
the click and always comes back, and a failed publish says why instead of vanishing.

**2. `cms_published` was classified as a read.** `admin.js` drops a read-only reply when the screen changed
while it was in flight (P16 3.9) — correct for a list, wrong for a call that writes the page file and flips
the row. Publish clicked just before leaving the screen kept its promise forever, so the button never came
back. `cms_published` (and the two `*_order` calls, which write a position) are now writes.

**3. The row could turn Live when the file was not written.** `publish()` flipped the status, *then* wrote
`public/<folder>/<slug>/index.html`. When the write failed, the badge said **Live** over a **404** — the one
thing this admin must never show. The file is now written first; a page whose file cannot be written stays a
draft and the editor is told why. `mkdir` in the page writer is also warning-free: two publishes of the same
page (a double click, a retry) can reach it together, and "File exists" must not become an exception.

**4. The editor was not told the file it published.** `cms_get`/`cms_list` returned `rel: null` for every
item, and the editor reads a non-empty `rel` as "this is live". A just-published article therefore still
offered **Publish now**, **Delete draft** and an editable address, and never showed **View live**. The
server now returns the page file when it is on disk.

Verified live on a real draft (row 288, deleted afterwards): Edit → "Publish now" / Delete draft →
click → **"Publishing…"** → toast **"Published: /insights/zz-publish-check/"**, `/insights/zz-publish-check/`
**200**, 69,958-byte file, button now **"Publish changes"**, **View live** shown, Delete draft gone.
`/home/user/publish-fixed-2026-10-21.png`. The failure path was exercised too, by putting a file where the
folder should be: the API answered *"The folder for that page could not be created."* and the row stayed a
**draft** with no `published_at`.

A guard was also made honest in passing: a `ValidationException` from a screen-level check used to be
reported as *"The server could not complete that action"*. Those messages were written for the person
("That address belongs to the system", "The folder for that page could not be created"), so the API now
returns them.

Tests: `StudioContentTest` (13) — a page whose file cannot be written stays a draft with no `published_at`;
the same page publishes twice without failing on the folder it already made; `cms_get` reports
`insights/<slug>/index.html` after publishing and null before. Gates: `tests/Feature/Website` **210 passed
(1893)**; full suite **1224 passed, 1 skipped (6666)**; Pint clean; PHPStan no errors.

Note for the preview only: a page folder deleted from the shell while the preview server is running stays as
a stale entry in the PHP worker's filesystem view (`file_exists` true, `is_dir` false, `mkdir` → "File
exists"). Restart `tools/sandbox/server.mjs` after deleting a page folder by hand; production has no such
layer.

**Step 19b — the page names itself in its sharing tags.** The last item left over from the Blog/Portfolio
rewire: `ArticleFile` replaced the shell's canonical through a string swap, so `og:url` came right only
because every v1 shell happens to write the same URL in both tags — and a shell without `og:url` (or
`og:type`) produced a page with neither, leaving a shared link nothing to name it with. The head now sets
both explicitly: `og:url` is the page's own address, and `og:type` follows the design's rule — **article**
for insights, **website** for projects (checked across all six live project pages; the project shell is the
one that writes `website`). A tag that exists is replaced, never duplicated; a tag that is missing is added
before `</head>`.

Live: published `/insights/zz-head-check/` → canonical and `og:url` both
`https://woodex.com.pk/insights/zz-head-check/`, `og:type=article`, `og:title` the article's, and **0**
references to the shell page it was built from (row and file removed after the check).

Tests: `ArticleFileTest` (9) — a shell that names itself gets the new page's address in `og:url` and keeps its
`og:type`, with the tag appearing once; a shell with neither gets both, `website` for a study. Gates:
`tests/Feature/Website` **212 passed (1904)**; full suite **1226 passed, 1 skipped (6677)**; Pint clean;
PHPStan no errors.

### Step 20 — nothing can make a page row that is not a page (21 Oct 2026)

The last item of the Blog/Portfolio rewire list — *"decide the `page_new` type"* — turned out to have a
better answer than a type. `cms_page_new` ("New service page") created a **`PageType::Service` row and no
file**, and `cms_page_duplicate` copied one; both belonged to the old admin's **Service pages** screen, which
step 18 already put behind the door. A row is not a page since the renderer was retired, so each was a
doorway behind a locked door: an old bookmark's script, or any raw POST, could still add a page that answers
404 and appears in no list.

Both actions are gone from the seam and now answer by name: *"“Service pages” is the old admin’s screen: it
made a page row, and a row is not a page any more. Pages are the site’s files — make and edit them in All
pages and Page builder."* No type had to be chosen, because the OS never creates a page row for a page: the
builder's own **New page** / **Duplicate page** (`BuilderApi::pageNew`, from the builder menu) writes
`public/<folder>/<slug>/index.html` directly, and All pages lists files, which is what the screen already
says in its own note.

Verified live: both actions refuse with that message and the pages table stayed at **142 rows** with
`/a-service-page/` absent. The read-only pair (`pages`, `cms_page_kinds`) is left alone — it reads rows for
a screen that no longer opens, and claims nothing.

Tests: `StudioSeamTest` (8) — `cms_page_new` and `cms_page_duplicate` answer `ok:false` naming Service pages
and the files, and the row count does not move. Gates: `tests/Feature/Website` **213 passed (1911)**; full
suite **1227 passed, 1 skipped (6684)**; Pint clean; PHPStan no errors.

### Step 21 — the pages the owner writes have a copy the deploy cannot touch (21 Oct 2026)

Now that Publish really publishes (step 19), the site grows on the server: an article, a study or a builder
page is a **file under `platform/public/` that is not in git**. The v1 pages ship in the repository, so a
deploy puts those back — but the deploy only knows about them. Anything the owner published after go-live
exists on that one machine, and the deployment document still described the site folder as "replaced on
every deploy", which invites exactly the operation that would erase it.

The safety net that existed: `spatie/laravel-backup` already runs `backup:run --only-db` daily and a full
`backup:run` (which includes `base_path()`, so `public/` too) **weekly**, at 02:30. One week of publishing is
a lot to lose, and restoring it means restoring the whole application folder.

Added:

- **`woodex:site-backup`** (`app/Console/Commands/SiteBackup.php`) — zips every `index.html` under `public/`
  into `storage/app/private/site-backups/site-<date>.zip`, which lives in the data folder outside every
  `public_html` (deploys never touch it). `--keep=7` by default; `assets/`, `build/`, `media/`, `admin/`,
  `api/`, `builder/` and the rest of the application's folders are skipped, so a copy is the size of the
  pages, not the theme. Measured on the real site: **142 page files → 2.5 MB**.
- **Scheduled daily at 02:45** (`routes/console.php`), after the database backup.
- **`docs/06-DEPLOYMENT-HOSTINGER.md` §2.1a** — "`platform/public/` is the live site": the three things a
  deploy must never do (`git clean -fd`, a fresh clone/upload over the folder, restoring an old `public/`),
  what protects the pages (this archive, the builder's 40 versions per page, retired pages in storage), and
  the one-line restore.

Verified in the preview: `php artisan woodex:site-backup` → *"Backed up 142 page file(s) … 2.5 MB"*; the
archive is not in the repository and not under any web root.

Tests: `SiteBackupTest` (2) — the archive holds `index.html`, `about/index.html` and
`insights/a-post/index.html` and **not** `assets/index.html` or `media/index.html`; `--keep=1` prunes the
older dated copy. Gates: `tests/Feature/Website` **215 passed (1921)**; full suite **1229 passed, 1 skipped
(6694)**; Pint clean; PHPStan no errors.

### Step 22 — the Content library is retired, not hidden (21 Oct 2026)

The last open decision in the Content band (docs/audit/20 §5.2). On 20 Oct the Content library
(`/admin/website/content`) came out of the sidebar because its records are the same pages and blocks the
screens below edit — but the **route still answered**, the Livewire screen still rendered, and its two forms
still wrote `content_entries` rows. Those rows are read by `ContentLibrary`, which only `StudioApi` called;
nothing that writes a page file ever read them. It was a screen that could accept an edit and change nothing
a visitor sees — the exact trap steps 15–21 have been closing. It held **0 rows**.

Retired outright, per the recommendation:

- **Gone:** the route (`admin.website.content`), `ContentLibraryIndex`, its Blade view, `ContentLibrary`
  (the only reader), its test, and the twelve `StudioApi` actions behind the old FAQs / Testimonials / Client
  logos screens (`cms_faqs`, `cms_faq_save`, `cms_faq_delete`, `cms_faq_order`, `cms_testimonials`,
  `cms_testimonial_save`, `cms_testimonial_delete`, `cms_testimonial_order`, `logos_get`, `logos_save`).
  `StudioApi` is **641 lines shorter**.
- **Refused by name, not silently:** every one of those actions now answers from one `RETIRED` map with the
  screen and where that work is done — *"“FAQs” is the old admin’s screen: it changed database rows, and the
  website is its page files. That work is done in the FAQ section of the page, in Page builder now."* Same
  mechanism step 20 added for Service pages, widened to carry the destination per screen.
- **Kept:** the `content_entries` table, the `ContentEntry` model and its migration. Deleting them is a
  schema change and was not asked for; with no screen, no reader and no action left, the table is inert, and
  the data (if any ever arrives) is still there. Also kept: the FAQ, Reviews and Clients **sections** in the
  page builder, which are what a page actually renders.

Verified live: `/admin/website/content` → **404**; each retired action → `ok:false` with its own sentence
(the six checked: FAQs, Testimonials, Client logos, plus Service pages); `cms_list` still answers normally
with its items; `/` and `/sitemap.xml` unchanged at **142** URLs.
`/home/user/content-library-retired-2026-10-21.png`.

Tests: `WebsiteAdminParityTest` — the nav has no Content library, `Route::has('admin.website.content')` is
false, and the URL is 404 (it used to assert the screen answered); `StudioSeamTest` — six of the retired
actions answer `ok:false` naming their screen, and the screen itself is 404. Gates: `tests/Feature/Website`
**215 passed (1931)**; full suite **1229 passed, 1 skipped (6704)**; Pint applied (1 style fix); PHPStan no
errors.

### Step 23 — one word, every page: Find & replace (21 Oct 2026)

The first slice of docs/audit/21 §7, and the one the measurement made unavoidable: the phone number
`+92 322 4000768` is written into **all 142 page files** as plain text (399 occurrences), and so are the
email and the address. The builder edits one file at a time, so a new number meant 142 opens. Nothing in the
OS could change a word across the site — the v2.1 `global_replace` action was one of the 30 missing ones.

Added — `SiteWideReplace` plus the **Find & replace** screen (Website → Pages & builder → Find & replace):

- **Plain text, not a pattern.** `str_replace`/`str_ireplace` semantics, so nobody escapes anything. It
  changes every occurrence in the document — visible text, `tel:`/`mailto:` links, meta tags and the
  JSON-LD — because a phone number that changes in one of those and not the others is worse than one that
  did not change at all.
- **Preview writes nothing.** Every page and its match count, plus a readable snippet of the first match
  taken from the page's *text* (the first cut showed a window of raw HTML and landed inside the structured
  data — useless), falling back to the raw document only when the text is in an attribute.
- **A stale preview cannot authorise a write.** The preview is stored with a signature of the exact search,
  replacement, case rule and areas; changing any of them disables Replace. On top of that the counts are
  recounted at write time, so a builder save in another tab makes the run refuse rather than write.
- **The whole run, or nothing.** Every page is planned in memory first; `HtmlGuard::assertNoNewCode()` runs
  the same rule as a builder save (an editor changes words, not code — owners with `website.raw_html` are
  exempt, exactly as there).
- **Undo is one press.** Each touched page keeps a before-image in `storage/app/private/find-replace/<run>/`
  and a normal builder version (`…-find-replace.html`). The last **10 runs** are kept; Undo restores every
  page of a run, and a second Undo is refused by name.

Navigation: the entry sits under **Pages & builder**, beside the builder, because it changes the same files.
`P10SidebarHubsTest` now asserts **48** live destinations (was 47) and `WebsiteAdminParityTest` names the new
item — a screen cannot join the sidebar without a test saying so.

Measured live (preview 33143), a deliberately-marked change limited to **Lahore areas**:

```
/lahore/              9 matches
/lahore/model-town/   8
/lahore/bahria-town/  5   … 9 pages, 52 matches in total
→ Replaced 52 match(es) in 9 page(s). Every page was kept as a version.
→ Undo → Restored 9 page(s).
```

After the undo the 142-page tree is **byte-identical** to before the run (`sha1` over every `index.html`),
and `/sitemap.xml` still answers **142** URLs. `/home/user/find-replace-2026-10-21.png`.

Tests: `FindReplaceTest` (8) — preview counts without writing, area scoping, case rule, no-match writes
nothing, before-image + builder version kept, undo restores every page and refuses a second undo, and the
screen previews/replaces/undoes while refusing a stale preview; plus a permission test. Gates:
`tests/Feature/Website` **265 passed (2083)** including `P10SidebarHubsTest`; Pint clean; PHPStan no errors.

### Step 24 — the section library answers (21 Oct 2026)

The second slice of `docs/audit/21` §7, and the most visible broken promise left in the admin: the builder's
**My sections** panel was fully built — the save dialog with category, tags and a *Global section* switch, the
usage warning, drag-to-insert — against a server that answered `blocks_list` with `{"ok":true,"blocks":[]}` and
refused all five writes with *"… arrives with the section-library slice."* A screen that offers to save and
cannot.

**The library is a file.** `storage/app/private/section-library.json` holds the saved sections, for the same
reason the pages are files: a library of sections belongs beside the site it feeds, and there is no second
content model to reconcile. `BlockLibrary` owns it; a damaged file is **refused**, never replaced with an empty
one — the message says the library was not changed and to move the file aside.

**Global sections actually stay linked.** That is the one thing a copy inside a page file cannot do alone. The
builder stamps the section's root element with `data-wx-global="<id>"`, and `blocks_sync` puts the library's
current version onto every page that carries that marker, through `SaveHtmlFile` — so each page is snapshotted
first and the script guard runs, exactly like any other page write. `blocks_usage` reads the 142 page files to
say *which* pages, before anything is touched.

**Two defects in the shipped builder code, found by running it:**

1. **The marker was written into the middle of the opening tag** — `'<section class="x">'` became
   `<section class="x" data-wx-global="1">` correctly only when the tag had no other attributes; the old
   `replace(/^<([a-z]+)/, '<$1 data-wx-global="id">')` inserted `>` after the tag *name*, leaving
   `class="x"` outside the tag. The attribute now goes at the end of the opening tag and any old marker is
   removed first.
2. **Clicking a saved section did nothing.** `renderMine()` found the block with `x.id === btn.dataset.id` —
   a number against a string. It worked while ids were strings; the server sends ints. Now `String(x.id)`.
   The same mismatch would have made ⟳ and ✕ dead, so the panel would have looked alive and ignored every
   click.

**Added to the panel:** the **⟳ sync** control a global section needs (it was implied by the dialog's promise
and did not exist), and `.lib button .sync` styling.

**Import is cleaned before it is stored.** A library file comes from outside this site: scripts, inline
handlers, `javascript:` links and frames are removed in PHP (`sanitise()`), and a section that is not one
element is refused rather than stored broken. The starter-pack path in v2.1 imported the built-in sections the
same way.

Navigation is unchanged — the library lives in the builder, which is where it is used, not as a separate
screen. `blocks_*` are no longer in the "arrives later" list.

Verified live on the preview (33143), as the owner:

```
panel (empty)   "Save any section or element with 💾 in the toolbar."
blocks_save     ok · id 1 · "LIVE CHECK band" · global · tags [live, check]
panel           "Hero · 🔗 LIVE CHECK band · global · ⟳ sync · ✕ delete"
insert          <section class="live-check" … data-wx-global="1">  (one tag, marker inside)
save page       file carries the marker; the broken `data-wx-global="1"> class=` form: absent
blocks_usage    {"1": ["index.html"]}
⟳ sync          "Updated on 1 page(s) ✓" · file now says "Live check v2", v1 gone, one marker
blocks_delete   ok:false, used:true — "“LIVE CHECK band” is on 1 page(s): /. Deleting it leaves those
                copies on the pages, unsynced."
```

The home page was restored byte-for-byte afterwards (`sha1`), the test library file removed, and
`/sitemap.xml` still answers **142**. `/home/user/section-library-2026-10-21.png`.

Tests: `SectionLibraryTest` (18) — save/list/update ordering, code refusal (script, handler, `javascript:`,
iframe) and multi-root refusal, delete-with-usage and forced delete that never reuses an id, sync onto two
pages with the rest of the page untouched and a version kept, a second sync changing nothing, non-global sync
refused, import cleaning and skip counting, a damaged file refused through the endpoint in the panel's own
`{ok:false}` shape, the marker placement, and the nesting/quote-aware range scan. Gates:
`tests/Feature/Website` **283 passed (2162)**; full suite **1255 passed, 1 skipped (6830)**; Pint clean;
PHPStan no errors.

### Step 25 — integrations are connectors now; MCP answers over HTTPS (21 Oct 2026)

The owner's correction of the same day: *"integration just open to document… there is no solution of real
integration system and MCP is just dummy… change document to real login system with any connector."*
Two things were true. The hub's planned cards opened a modal that ended at **"Provider documentation"** — a
dead end, not a connection. And the MCP bridge, though real, needed a Node adapter on the owner's laptop and
could not read a single page of the 142-file website.

**The document modal is gone.** In its place is a connector engine: `integration_connections` (one encrypted
row per authorised provider), `Connector` + `ConnectorManager` + three drivers, and a signed OAuth callback.
The rule is the same one the rest of this codebase runs on: *the provider is asked before anything is stored,
and asked again every time the screen says "Connected".*

* **GitHub** — a personal access token; `GET /user` proves it, records the account and rate limit, and refuses
  a rejected token without writing a row.
* **Google Workspace** — Drive metadata, Sheets, Gmail and Calendar read scopes, on a consent separate from the
  identity sign-in (`openid email profile` stays identity-only). The stored scopes are the ones `tokeninfo`
  reports, not the ones we asked for.
* **Custom API** — any public https endpoint and bearer token, with an SSRF guard (https only, no localhost,
  link-local or private ranges). A provider that answers `200 {"ok":false}` is a failure, not a connection.
* **Connected apps** — the v2.1 strip (Gmail · Drive · GitHub · WhatsApp · Telegram) is back at the top of
  `/admin/integrations`, with four states. "Connected" requires a successful real call in the last 15 minutes;
  a configured-but-unproven channel says **Needs check**, not Connected.

**MCP stopped needing a laptop.** `POST /api/mcp` is a JSON-RPC 2.0 MCP server on the site itself: paste the URL
and a `wx_mcp_` bearer key into Claude, Cursor or any client. Seven read tools — `woodex_status`,
`woodex_site_status`, `woodex_list_pages`, `woodex_get_page`, `woodex_seo_report`, `woodex_list_leads`,
`woodex_list_quotes` — with new scopes `site.read` / `pages.read`. The tools read the same `FileAudit` and
`BuilderFile` the admin screens read, so "142 pages, 0 broken links" is the website, not a copy of it. Every
call is audited with the key id; a key never exceeds its scopes or its issuing user's live permissions. The
stdio adapter and `/api/bridge/v1/*` still work for older clients.

**Verified live on 33143**

```
GET  /admin/integrations         200 · Connected apps · "Connectors you can authorise now" · /api/mcp
Custom API  https://registry.npmjs.org/-/whoami  401 → "The service answered 401. The token was refused."
                                                  no row stored, modal stays open
Custom API  https://registry.npmjs.org/          200 → Connected · checked 0 seconds ago · Disconnect → gone
GitHub      gh token → woodex420 · 13 repos · "checked 0 seconds ago" from the screen; disconnected after
MCP         initialize → serverInfo woodex/2.0.0, protocol 2025-06-18
            tools/list → status, site_status, list_pages, get_page, seo_report (key with pages.read)
            woodex_site_status → 142 pages · 142 sitemap URLs · 0 broken links · 0 without alt
            woodex_get_page /about/ → about/index.html · 814 words · one H1
            woodex_list_pages?query=wardrobe → 3 real URLs
```

The sandbox's egress proxy replaces GitHub credentials, so a rejected GitHub token cannot be shown here; the
401 path is covered by `ConnectorTest` with `Http::fake`. Every other state above is a real request.

**Owner-side, unchanged:** the Google OAuth client and the second redirect URI
(`/admin/integrations/oauth/google/callback`), a GitHub token, and a production **System → Database update**
for `integration_connections`. Google Workspace, Notion, Slack, Zoom, Meta and LinkedIn still cannot be
connected without their apps — their cards now say exactly what is missing and collect nothing.

Tests: `ConnectorTest` (14) and `McpHttpTest` (9); `McpBridgeTest` **18**; integrations-adjacent suites
**163** green; Pint clean; PHPStan no errors. Screenshot `/home/user/integrations-rebuilt-2026-10-21.png`.

### Step 26 — deep QA: an enquiry can become an estimate, and the sidebar stops lying (21 Oct 2026)

**The finding.** 45 admin routes answered 200, the modules were real, and the database was almost empty:
`estimates`, `invoices`, `payments`, `projects`, `conversations`, `journal_entries` all had zero rows while
26 enquiries sat in CRM. The reason was structural, not cosmetic — the only link from a lead jumped straight
to a quotation (`CreateQuoteFromEstimate`), which never writes an `estimates` row. The Estimations screen was
therefore *guaranteed* to stay empty no matter how much real work the studio did.

**What changed (Slice A).**

* The estimate builder opens from an enquiry (`/admin/estimations/new?lead=…`), fills the service, size and
  client from real lead fields, keeps `lead_id` on the saved estimate and shows the enquiry on both sides.
  A service the rate book does not price (e.g. an old `kitchen-design` enquiry) leaves the service **unset** —
  never a guessed rate.
* The Estimations list shows the enquiry reference and its empty state now has two real actions instead of
  one line of prose.
* Eight workflow hubs (`Leads`, `Contacts`, `Tasks & Visits`, `Quotations`, `Invoices`, `Payments`,
  `Documents`, `Reports`) finally set the browser title through `Hub::render()->title()`.
* The admin header shows a **Demo data** pill when `APP_ENV=local`, because the preview is seeded with demo
  contacts, leads and quotations and nothing said so. Production installs never render it.
* Six regression tests in `tests/Feature/Sales/EstimateFromLeadTest.php`.

**Verified live on 33143.** Lead `L-2026-6KJYZ8` (Architecture, 1,350 sq ft) → *Start estimate* → prefilled
`architecture / 1350 / contact 26` with the enquiry banner → *Save* → `EST-2026-0002 saved with today's rates.`
→ present in Estimations with its enquiry reference → visible on the lead page. QA rows were then
force-deleted; the database is left as found.

**Tests.** Full suite **1,284 passed · 1 skipped · 6,976 assertions**; Pint clean; PHPStan 0 errors.

**Audit + plan:** `docs/audit/31_DEEP_QA_2026-10-21.md`. Next slices: B navigation truth (enable Client Portal,
Handovers, Chat Bot; remove the duplicate Alert Rules; System's 11 entries → 4 pages), C one pricing path,
D Overview control view, E invoice automation runtime, F marketing live proof, G hygiene.

### Step 27 — the sidebar stops lying; shipped screens come out of "P4" (21 Oct 2026)

**The finding.** The nav advertised four features as future work while they were live: Client Portal and
Handovers carried a `P4b` badge, Chat Bot `P4`, Alert Rules `P5`. "Alert Rules" was also listed twice — a live
screen under System and a disabled placeholder under Automation. System listed eleven screens; the owner
asked for three or four pages with their buttons inside.

**What changed (Slice B).**

* **Client portal** is a real page: every job's signed link (120 days), with copy / open / WhatsApp, and an
  honest empty state that points at quotations and projects. **Handovers** is a real page: jobs at finishing
  or handover with checklist progress and the client-signature state. Links open the project for the detail.
* **Chat Bot** opens `/admin/settings?tab=bot`, where the assistant's switches already lived.
* The **duplicate Alert Rules** placeholder is gone; the live screen stays under Platform.
* **System: 11 → 4** — Settings, **Access & team** (users & roles · lead assignment · canned replies),
  **Connections** (integrations · data import), **Platform** (health · audit log · change requests · alert
  rules · recycle bin). Every original route still answers, so bookmarks, Telegram buttons and notifications
  keep working.
* **Estimator rates** moved from Content to Sales, beside the estimates it prices. The Content group is back
  to Blog / Portfolio / Forms, and the Estimator/Estimations confusion is gone from the sidebar.

**Tests.** New `SystemNavigationTest` (12 tests) asserts the four System pages open for the owner and 403 for
support, the portal/handover screens render, `/admin/chat-bot` redirects to the Bot tab, and only **Lead Ads**
remains a planned entry. Nav parity tests updated deliberately: the live-destination count is now **44**
(11 System entries → 4 is −7; Client portal, Handovers, Chat Bot are +3). Full suite
**1,296 passed · 1 skipped · 7,002 assertions**; Pint clean; PHPStan 0 errors.

**Live on 33143.** Sidebar shows no `P4b`/`P5`; all four Projects entries are live; the three hubs open with
their tabs (Users & roles · Lead assignment · Canned replies / Integrations · Data import / Health · Audit log ·
Change requests · Alert rules · Recycle bin); `/admin/chat-bot` lands on Settings → Bot. Screenshots:
`/tmp/woodex-run/shots/slice-b-sidebar.png`, `slice-b-access.png`, `slice-b-platform.png`, `slice-b-portal.png`.

**Found on the way:** `System\LeadRulesSettings` has no route and is only used by a test — the live screen is
`Crm\AssignmentRules`. Remove it with its test in the hygiene slice.

### Step 28 — one pricing path: the estimate comes first (Slice C, 21 Oct 2026)

**Why.** `CreateQuoteFromEstimate` priced a quotation straight from the enquiry's meta and never wrote an
`Estimate` row. Every quotation therefore had `estimate_id = null`, and Sales → Estimations stayed empty while
estimator enquiries were arriving — the estimate was optional. That was finding **B6** in
`docs/audit/31_DEEP_QA_2026-10-21.md`.

**What changed.**

* `Sales\Services\EstimatorLeadDetails` — the single reader for what an estimator enquiry said: service, area,
  finish and the range the site showed. It reads the bridge's `meta.fields` and the importer's `meta.estimate`,
  and falls back to the lead's own `service`/`area_sqft` columns **only** for estimator-sourced rows with no
  meta. A form enquiry, or an estimator form with a blank area, stays unpriced — never a guessed rate.
* `Sales\Actions\DraftEstimateFromEstimatorLead` — writes the rate-book draft for an enquiry, attributed to the
  lead owner (or the first active person with `estimates.manage`), idempotent per lead.
* `Sales\Listeners\RecordEstimatorEstimate` — wired to `LeadCaptured` in `AppServiceProvider`, so the draft is
  written when the enquiry arrives, not when somebody remembers to click. The v1 site's own
  `POST /api/forms.php` goes through `CaptureLead`, so it is covered without touching the page.
* `CreateQuoteFromEstimate` — converts the enquiry's existing draft when it still matches, otherwise writes one,
  links `quote.estimate_id` and `estimate.quote_id` in the same transaction. A quotation can no longer exist
  without its estimate.
* `estimates:backfill {--dry-run}` — catch-up for estimator enquiries captured before this slice; safe to re-run.
* The hub Blade no longer renders its own page header above the active screen — `/admin/leads` used to show
  "Leads" twice, with two different descriptions. One heading per page now.
* Estimations shows the rate book's label ("Interior Design") instead of the stored key.

**Tests.** `tests/Feature/Sales/EstimatorEnquiryEstimateTest.php` (5): the draft is written on capture with the
rate-book snapshot; a repeat submission keeps one draft; form enquiries and unknown services get none; the
quotation converts the existing draft rather than writing a second one.
`tests/Feature/Sales/BackfillEstimatesTest.php` (4): backfill writes from lead columns, dry-run writes nothing,
running twice is safe, an unlisted service is left for a person. `EstimateToQuoteTest` gained the two-way link
assertion, and `P10SidebarHubsTest` the single-heading assertion. Full suite **1,307 passed · 1 skipped · 7,044 assertions**; Pint clean;
PHPStan 0.

**Proof on 33143.** `POST /api/forms.php` with `form=estimator`, `area=1200`, `finish=premium` → `L-2026-000002`
and `EST-2026-0007` (draft, Rs 480,000) in one request. `estimates:backfill` → `EST-2026-0003…0006` for the four
estimator enquiries already present. QA rows deleted after the proof; screenshots
`/tmp/woodex-run/shots/slice-c-estimations.png`, `slice-c-leads-hub.png`.

**Still open.** D (Overview control view), E (invoice automation), F (marketing live proof), G (hygiene).

### Step 29 — the Overview is a control view, not a report (Slice D, 26 Oct 2026)

**Why.** `Dashboard` recomputed leads-per-day, pipeline and "needs attention" with its own queries while
`SalesFunnelReport`, `RevenueCollectionsReport` and `ReceivablesReport` computed the same business twice; the
attention rows offered a WhatsApp link but no way to open the work; the Leads list had no filter that could
reproduce a dashboard count. A tile was a dead end and a number could not be checked. Finding **B10** in
`docs/audit/31_DEEP_QA_2026-10-21.md`.

**What changed.**

* `Domain\System\Control\ControlPanel` — one query per number, each returned with the href of the screen that
  filters to the same rows: enquiries today, quotations awaiting approval, overdue follow-ups, money due
  (outstanding + late), jobs running, automations failing. A card is only returned when the person can open its
  screen, so sales sees four, an accountant sees three, and an editor sees none (with an honest explanation
  instead of an empty grid).
* `Lead::followUpDue()` — the single definition of a follow-up that should already have happened: `next_action_at`
  in the past, or a first reply past `sla_due_at`; closed leads excluded. The dashboard tile, the needs-attention
  line and `LeadsIndex?due=overdue` all run it.
* Dashboard (Overview tab) — six link tiles, a "needs attention" list whose button is the action itself (Open the
  list / Decide now / Chase it / See what failed / Check the schedule), the five most overdue enquiries with
  WhatsApp and call, then the 14-day trend, pipeline, go-live checklist and activity. `wire:poll.60s.visible` kept.
* `LeadsIndex` — `?created=today|week` and `?due=overdue` are URL filters with a "Filtered:" chip and Clear, so a
  dashboard number and the list under it are literally one query.
* `LeadTrackingBoard::$group` is `#[Url]` (same five names, still defaulting to `overdue`), so a count can link to
  its column.
* Reports hub — the same six numbers above the tabs from `ControlPanel`; `ReportRange` gained a `today` preset and
  both reports put `preset` in the URL, so "Today's reports" opens on the period the dashboard measures.
* `automationsFailing()` — a task counts only while its **latest** run failed (a recovered task is not failing);
  the failed-jobs queue is added; tasks with no run in 25 hours are named separately as "stopped reporting".

**Tests.** New `tests/Feature/Overview/ControlViewTest.php` (15): tile set and hrefs, the overdue definition, tile
count equals list rows, the `created=today` filter and its clear, money-due split, late-money and approval
attention lines, failing vs recovered vs stale automations, per-role gating (sales/accountant/editor), own-book
scoping, the reports strip and `preset=today`, tracking-board URL state, and an empty studio with no invented
numbers. Full suite **1,322 passed · 1 skipped · 7,116 assertions**; Pint clean; PHPStan 0.

**Live on 33143 (owner session).** `/admin` renders all six tiles; "Follow-ups overdue" reads **9** and its own
href `/admin/leads?due=overdue` lists exactly **9** rows with the "Filtered: Follow-ups overdue" chip;
`/admin/reports?tab=funnel&preset=today` shows the same six numbers and the Today period. The other five tiles are
honest zeros on the fresh demo database ("nothing outstanding", "no active project yet", "last run of every task
was clean"). This turn's workspace reset removed the browser kit's NSS libraries, so these checks were made over
HTTP against the live preview with a real owner session — no screenshot is claimed.

**Still open.** E (invoice automation that runs), F (marketing live proof, owner-blocked), G (hygiene).

### Step 30 — invoice automation that actually runs (Slice E, 26 Oct 2026)

**Why.** The advance invoice was the only one the OS raised by itself; every later milestone waited for a person to
remember, and overdue money had no ladder at all — each reminder was a manual click on the invoice. Finding **B11**
in `docs/audit/31_DEEP_QA_2026-10-21.md`.

**What changed.**

* `Sales\Actions\RaiseMilestoneInvoice` — one raiser for any project milestone: number series, tax slice from the
  quotation, frozen PDF/snapshot, ledger posting and sequence trigger. A milestone is billed once; raising again
  returns the existing invoice, and an archived one refuses instead of duplicating.
* `Sales\Actions\RaiseAdvanceInvoice` — keeps only the acceptance rules (accepted quotation → the `on_acceptance`
  milestone) and delegates the raising, so acceptance and the scheduler cannot drift apart.
* `woodex:invoice-milestones` — hourly at :15, `--dry-run`. Bills every reached milestone on an accepted quotation,
  which also catches up projects accepted before this slice or whose acceptance job failed.
* `Sales\Actions\InvoiceReminderLadder` + `woodex:invoice-reminders` — daily 10:00. Rungs 7/14/30 days past due from
  `sales.invoice_reminder_rungs`; the rung already fired is read back from the audit trail (`event = invoice_reminder`
  carrying `rung`), so no new table was needed and the same record is what Audit shows.
* `Sales\Notifications\OverdueInvoiceNotification` — bell + Telegram to whoever may verify payments, carrying the
  rung, the days late, and the reason when the client was not messaged.
* Client messages still go through `SendPaymentReminder` (consent, opt-out, WhatsApp connection). The ladder sends as
  the invoice's creator, else the first active person who may verify payments, because conversation sends authorise a
  real staff member; `SendPaymentReminder` now accepts a nullable sender.
* Automation catalog + scheduler — **Milestone invoices** (hourly :15) and **Overdue payment ladder** (daily 10:00),
  both toggleable; pausing the ladder stops every client message at once.
* Settings migration `2026_10_26_100000_add_invoice_automation_settings` — `invoice_reminder_rungs = [7,14,30]`,
  `invoice_reminder_send_client = true`.

**Tests.** `tests/Feature/Sales/InvoiceAutomationTest.php` (10): a reached milestone is billed once and the second
run adds nothing; an unreached milestone is left alone; `--dry-run` writes nothing; one rung per day-period with the
consenting client messaged; rungs advance 7 → 14 → 30 with age; no consent records the rung with the reason; paid,
void, draft and not-yet-due invoices are skipped; the Automation screen lists both jobs; the switch is honoured.
Full suite **1,332 passed · 1 skipped · 7,165 assertions**; Pint clean; PHPStan 0.

**Proof on 33143.** `schedule:list` → `woodex:invoice-milestones` 15 * * * *, `woodex:invoice-reminders` 0 10 * * *.
`/admin/automation` lists both with cadence. QA invoice `INV-QA-0001` (Rs 50,000, 8 days late) → exactly one rung,
audit text *"7-day reminder for INV-QA-0001 (8 days overdue): client not messaged — WhatsApp is not connected in
Settings yet."*; a second run fired 0. QA rows deleted afterwards.

**Deliberately not built.** Recurring invoices (a schedule per client) need a new table — a schema change waiting for
the owner's yes. Milestone invoices cover the project billing that produces most of the money.

**Still open.** F (marketing live proof, blocked on Meta/LinkedIn), G (hygiene).

### Step 31 — recurring invoices: a retainer bills itself (Slice E, part 2, 26 Oct 2026)

Slice E gave the money side two automatic jobs — milestone invoices and the overdue ladder. The one billing shape it
could not express was the simplest one a studio actually sells: the retainer. A client pays Rs 25,000 a month for
maintenance, or a quarterly inspection, and somebody had to remember to raise the invoice. Approved by the owner on
26 Oct as **one new table**; nothing else in the schema changed.

**Schema (owner-approved, two migrations).**
* `2026_10_26_120000_create_recurring_invoices_table` — `contact_id`, `company_id`, `title`, `business_line`,
  `frequency` (`monthly|quarterly|yearly`), `lines` JSON (the same shape the quotation and invoice lines use),
  `tax_bp`, `due_days` (null = `sales.invoice_due_days`), `notes`, `starts_on`, `next_issue_on` (indexed),
  `ends_on`, `is_active`, `invoice_count`, `last_issued_at`, `created_by`, plus `public_id` (ULID).
* `2026_10_26_120100_add_recurring_invoice_sequence` — the `recurring.invoice` row in `number_sequences`
  (prefix `RI`, `{prefix}-{year}-{number}`, padding 4, yearly reset). Invoices raised by a schedule are numbered
  `RI-2026-0001`, so they never collide with `INV-…` and a client can tell a retainer from a job invoice.

**What shipped.**
* `Sales\Enums\RecurringFrequency` — `advance()` uses `addMonthNoOverflow` / `addMonthsNoOverflow(3)` /
  `addYearNoOverflow`, so a schedule that starts on the 31st never silently jumps to March.
* `Sales\Models\RecurringInvoice` — casts the business line and frequency, `scopeDue()` (active, `next_issue_on`
  on or before today, not past `ends_on`), and money accessors computed from the standing lines.
* `Sales\Services\InvoiceFreezer` — the freeze/snapshot step extracted from `RaiseMilestoneInvoice` so both raisers
  write the same immutable document: idempotent, verifies the sha256, refuses a tampered document.
  `RaiseMilestoneInvoice` now delegates to it (constructor `SalesSettings, InvoiceFreezer, PostInvoiceToLedger`).
* `Sales\Actions\IssueRecurringInvoice` — locks the schedule row, skips a paused/not-yet-due/ended schedule (an ended
  one deactivates itself), creates the invoice (`kind = recurring`, `status = issued`, PKR, `RI-…` number, due date
  from `due_days`), writes the items with their per-line tax, freezes the PDF, posts to the ledger as the person who
  owns the schedule, then advances `next_issue_on`, bumps `invoice_count` and records `last_issued_at`.
  A schedule that missed dates produces **one** invoice now, not a backlog: the next date is walked forward past
  today before it is saved.
* `woodex:invoice-recurring {--dry-run}` + `routes/console.php` — **daily 09:20**, gated by the existing
  `Automation::enabled` switch, so it appears on `/admin/automation` as **Recurring invoices** and can be paused.
* `Livewire\Admin\Sales\RecurringInvoices` + its blade, behind the **Recurring** tab of
  `/admin/invoices` (`invoices.manage` for every mutator): list with the standing total and next run, a create form
  (client, title, line, frequency, tax, due days, start/end), pause/resume, **Bill now**, delete.
* `AppServiceProvider` — `'recurring_invoice' => RecurringInvoice::class` in the morph map, because the audit trail
  and the invoice snapshot both store polymorphic subjects.

**Tests.** `tests/Feature/Sales/RecurringInvoicesTest.php` (9): one invoice per period with the `RI-` series, 15% tax
on 25,000.00 → 28,750.00, due date +7 days and the frozen PDF written; a second run raises nothing; a schedule 40
days late catches up with exactly **one** invoice and moves its next run into the future; a paused and an ended
schedule bill nothing; `ends_on = today` bills once and then deactivates; the screen saves a quarterly Rs 18,000
schedule (20,700.00 with tax) with its creator; **Bill now** on a future-dated schedule bills immediately; pause and
resume; the `sales` role is forbidden; `/admin/invoices?tab=recurring` and `/admin/automation` show the job.
Full suite **1,341 passed · 1 skipped · 7,210 assertions**; Pint clean; PHPStan 0 (598 files).

**Proof on 33143.** A QA schedule (*QA monthly retainer · Hamza Khan*, Rs 25,000 + 15%, monthly, due in 7 days) was
created against the live database. `woodex:invoice-recurring --dry-run` printed
*"would bill QA monthly retainer · Hamza Khan (every month)"* and *"Would raise 0 recurring invoice(s) from 1 due
schedule(s)."*; the real run printed *"QA monthly retainer · Hamza Khan → RI-2026-0001 (Issued)"* and
*"Raised 1 recurring invoice(s) from 1 due schedule(s)."*; the second run printed **0**. The invoice reads
`RI-2026-0001 · issued · 25,000.00 + 3,750.00 = 28,750.00`, one item, PDF
`invoices/…/invoice-RI-2026-0001.pdf`. `/admin/invoices?tab=recurring` showed the schedule at Rs 28,750 and the
invoice list showed `RI-2026-0001`. The QA schedule, invoice, items, ledger entry and number were then deleted.

**Still open.** F (marketing live proof, blocked on Meta/LinkedIn), G (hygiene).

### Step 32 — hygiene: no third-party font host, System hubs gated, evidence trimmed (Slice G, 26 Oct 2026)

Slice G was the small, safe cleanup the audit listed last: two font families off a CDN, 13 MB of historical
screenshots, an authority index, and hub routes that opened for anybody signed in. What the audit *assumed* about
the fonts was wrong, and finding that out changed the work.

**Fonts — more than two families.** The v1 site was already self-hosted (`/assets/fonts/*.woff2` linked from
`v1-p21.css`); the leak was the **page builder**. Its typography picker offers 26 families, and both the picker and
the theme panel built a `fonts.googleapis.com` stylesheet at runtime — and the builder saves the whole document, so a
page edited that way shipped with an external stylesheet on every visit. Three facts decided the fix:
* The families are not two, they are 26, so hand-copying files was not the job — `@fontsource` packages were fetched
  with `npm pack` (the sandbox reaches the npm registry) and the latin `wght` files were extracted.
* **87 woff2 faces** now live in `platform/public/assets/fonts/` (91 files for 27 families in total, counting the
  site's own), and `platform/public/builder/fonts.css` declares them all with relative
  `url("/assets/fonts/<family>-<weight>.woff2")` — no host, no protocol-relative URL
  (`/tmp/getfonts.py` regenerates it; the file is committed).
* `builder.js` line 551 (picker `href`) and line 1618 (exported theme `@import`) both point at `/builder/fonts.css`.

**System hubs — the page, not just the tabs.** Each tab of `/admin/system/{access,connections,platform}` filtered by
permission, but the route itself only required *a* login. A support colleague could open a shell of tabs their role
could never use. New `Http\Middleware\EnsureAnyPermission` (alias `can-any`) opens a hub when the person may use
**any** of its tabs: access → `users.manage,settings.manage`; connections → `integrations.manage,system.import`;
platform → `system.health,audit.view,system.change_requests,settings.manage,leads.delete`.

**Evidence.** All 33 PNG screenshots in `docs/audit/evidence/` were re-encoded to JPEG quality 88 — 13 MB → 5.6 MB,
same pixels at 100% — and every reference was repointed (`11_PHASE2_PAGE_BUILDER.md`, this file). `docs/` went from
15 MB to 6.8 MB. `docs/README.md` was corrected at the same time: its "historical" list started at `18` and
`audit/00`, which swept up the three documents it tells the reader to read first (`audit/16` and `audit/31`); the list
now starts at `15` and `audit/17`, and says plainly that 16 and 31 are the current set.

**Proof on 33143.** `/builder/fonts.css` → **200 `text/css`, 13,281 bytes, 87 `@font-face`**; `/assets/fonts/poppins-400.woff2`
→ **200 `font/woff2`, 7,884 bytes**; the served `builder.js` contains **0** occurrences of the font CDN and **2** of
`"/builder/fonts.css"`; `/admin/website/builder?path=/about/` → 200. A QA support user (`support-qa@woodex.test`,
deleted afterwards) logged in at `/admin/login` and got **403** on all three System hubs while `/admin` and
`/admin/leads` stayed **200**; the owner got **200** on all three.

**Tests.** `tests/Feature/System/HubAccessTest.php` (4) — support and accountant refused on every hub, owner opens
them, and one extra permission opens exactly its own hub and no other. `tests/Feature/Website/BuilderFontsTest.php`
(3) — no font-CDN string and no absolute URL in `builder.js` or `fonts.css`, the local stylesheet is linked, and
every family parsed out of the picker's own `GFONTS` table has a face and an existing woff2 file.
Full suite **1,348 passed · 1 skipped · 7,354 assertions**; Pint clean; PHPStan 0 (598 files).

**Not done in G.** Nothing from the audit's list. F (marketing live proof) stays blocked on Meta/LinkedIn approval.
