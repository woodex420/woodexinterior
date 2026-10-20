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
| Content: Blog ★ | **Partial (first pass)** | A post **is** a page of type `insight` — one content model, kept. The list, create, duplicate, unpublish and the `/insights/` index card work (`6c22e99`, evidence `docs/audit/evidence/blog.png`). **What the owner is missing:** v2.1's article editor — template picker, kicker, standfirst, hero, details strip, category/tags, listing card, SEO panel with live preview, FAQ link, AI drafting. Rebuilt on the shared content editor in P1A–P1B |
| Content: Portfolio ★ | **Partial (first pass)** | The 6 project pages list with the project link (`projects.case_study_page_id`, consent), index card, duplicate and unpublish work (`ec7613e`, evidence `docs/audit/evidence/portfolio.png`). **Missing:** v2.1's project editor — template picker, hero, details strip (project type, setting, year, size), facts box (scope, key move, materials, note), listing card, AI drafting |
| Content: Page templates ★ | **Partial (first pass)** | 50 layouts with search, type filter, default per type, duplicate and retire are live (`5c164ae`, evidence `docs/audit/evidence/page-templates.png`). **Wrong shape:** it is a standalone screen that also creates pages, while v2.1 offers templates inside “New page → Start from”, inside “New article”, and in a Template library. Being folded into the create flows in P1B; the builder edits one page, so a template must never create a builder page |
| Content: Forms ★ | **Done (16.13)** | `/admin/website/forms` lists the seven site forms with counts from `leads`, the field names actually received, and the one real alert-routing rule. No per-form routing store — see 16.13 |
| Content: FAQs ★ | **Done (16.16)** | `/admin/website/faqs` edits the shared FAQ library every page's FAQ section picks from, shows which pages use each answer, and re-renders them on save. Rename/reorder/add/delete; no second store |
| Content: Testimonials ★ | **Done (16.17)** | `/admin/website/testimonials` edits the shared review library; reviews are quoted verbatim, “Used on” is the switch, and there is no per-review rating or photo because the site's section shows neither |
| Content: Estimator ★ | **Done (16.15)** | `/admin/website/estimator` edits the file the public page reads and mirrors the rates into `EstimatorRate`; template pack (14 ready-made services), saved rate books, “adjust all rates %”, finish names, quotation terms and the live preview are all in |
| Content: Client logos ★ | **Partial** | Client logos are ported separately (`admin-logos.js`, evidence `studio-logos.png`). **Missing from the v2.1 screen:** the display settings on the home Clients section (small title, heading, grid vs sliding row, speed, grey-on-hover) — those are section fields, not library rows |
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
   save in place (the Won rule still applies). Evidence: `docs/audit/evidence/lead-drawer.png`.
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

Evidence: `docs/audit/evidence/studio-logos.png` (the ported screen inside the OS), and the browser run that
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

Evidence: `docs/audit/evidence/studio-sidebar.png` (Pages & builder / Content, with the disabled pills)
and `studio-sidebar-tools.png` (the heading-less tools band).

Pint PASS 850, PHPStan 0 errors.

---

## 16.11 Screen 4 — Service pages, ported (20 Oct 2026)

`/admin/website/services` is live in the studio shell (`docs/audit/evidence/studio-services.png`). It is the
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

`/admin/website/cities` is live (`docs/audit/evidence/studio-cities.png`). v2.1 kept city pages as **content
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

`/admin/website/forms` is live (`docs/audit/evidence/studio-forms.png`). The screen answers two questions the
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

`/admin/website/estimator` is live (`docs/audit/evidence/studio-estimator.png`). This screen is not like the
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

`/admin/website/faqs` is live (`docs/audit/evidence/studio-faqs.png`, editor `studio-faqs-editor.png`).

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

`/admin/website/testimonials` is live (`docs/audit/evidence/studio-testimonials.png`, editor
`studio-testimonials-editor.png`). **This closes the Content band: nothing in the Website sidebar is on “soon”.**

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
| Service pages | `/admin/website/services` | `studio-services.png` | `StudioServicePagesTest` 9 |
| City pages | `/admin/website/cities` | `studio-cities.png` | `StudioCityPagesTest` 6 |
| Forms | `/admin/website/forms` | `studio-forms.png` | `StudioFormsTest` 6 |
| Estimator | `/admin/website/estimator` | `studio-estimator.png` | `StudioEstimatorTest` 8 |
| FAQs | `/admin/website/faqs` | `studio-faqs.png` | `StudioFaqsTest` 11 |
| Testimonials | `/admin/website/testimonials` | `studio-testimonials.png` | `StudioTestimonialsTest` 8 |

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

Screenshots: `docs/audit/evidence/os1-builder-edit.png` (the heading editable in the canvas) and
`os1-builder-saved.png` (after Save, live). The edit was reverted afterwards by re-syncing the demo, so the
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
