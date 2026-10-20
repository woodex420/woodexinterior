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
| Content: Forms ★ | **Partial** | Forms render through the website bridge (`/api/forms.php` → lead), but there is no form manager or submissions screen |
| Content: Estimator ★ | **Partial** | Public estimator + `EstimatorRate` + `EstimationsIndex` exist. **Missing, per v2.1:** template pack (Kitchen, Wardrobes, Bedroom, Living, Complete home, Restaurant, Salon/clinic, Retail, Elevation, Grey structure, House construction, False ceiling, Flooring, 3D), saved rate books, “adjust all rates %”, and the live preview (From/To range in lac) |
| Content: FAQs ★, Testimonials ★, Client logos ★ | **Partial** | `ContentEntry` has only `faq` / `testimonial` / `logo` as untitled records. **Missing:** FAQ *groups* (reused in the AI chat, linked to articles, emitted as Google FAQ schema, “Add starter FAQ groups”), testimonial fields (role, project, city, rating, photo, Live), client-logo display settings (show on home, small title, heading, grid vs sliding row, speed, grey-on-hover, reorder) |
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
| Estimator | `admin-p18e.js` | **missing** (rates + public estimator exist) | Port, rates stay `EstimatorRate` |
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
| **Content** | Blog · Portfolio · Service pages* · City pages* · Page templates · Forms* · Estimator* · FAQs* · Testimonials* · Client logos · Team |
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
