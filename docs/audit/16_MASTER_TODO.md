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

### P1 — Content studio parity with v2.1 (revised 2026-10-20, after the owner's review)

_The v2.1 admin is the complete system (the owner's screenshots, 20 Oct). The work is to **import its content
workflows** into Woodex OS — same fields, same order of steps — inside the OS theme and on the page model we
already have. Not to invent screens of my own. What each screen is for is settled in 16.6._

**P1A — One content editor (3–4 sessions).**
`ContentEditor`, one Livewire component, used by every content type. Fields, as in v2.1: title, kicker, page
address, standfirst, template, hero image, details strip (label + value rows, add/remove), body sections
(heading, paragraph with bold/italic/link, image, table — the block editor underneath). Sidebar: publish now /
date / schedule / save draft; status & visibility (draft, published, public, unlisted, words, reading time);
category and tags; search & sharing (SEO title and meta description with counters, share image, focus
keyphrase, SEO check, Google preview); listing card (image, text, label). Type panels: project → facts box
(box title, facts, note under the facts); city → city and region; service → service type.
No new columns without approval: the SEO block goes in `pages.seo`, the rest in block data.

**P1B — Templates and AI (2–3 sessions).**
Article templates (Cost guide, How-to guide, Project story, Ideas & trends list, Comparison, Blank) and page
layouts (Service, Sector, Landing, About & team, Packages, Case study, plus “copy layout from this page”).
Templates live in **New page → Start from**, **New article** and a manage-only Template library — the current
standalone Page templates screen stops creating pages. AI: Settings → AI (provider, model, key, tone, daily
cap — `BotSettings` already stores the key) feeding draft-from-template, the SEO writer (`ProposeSeoCopy`),
city localisation and bulk city drafts, plus starter packs (FAQ groups, team roles). No key → the action is
disabled and says where to add one; never fake output. The chat assistant may create a draft from a template;
it never publishes without `website.publish`.

**P1C — The four content screens on that editor (3–4 sessions).**
**Blog & insights** (articles; the 48 live articles appear in the same list, marked live, editable in the
builder as v2.1 does), **Portfolio** (studies with project facts and consent), **Service & site pages**
(type filter + Start from + copy layout), **City pages** (city picker, edit content, bulk create with AI).

**P1D — Reusable content: FAQ groups, testimonials, logos, team (3–4 sessions).**
FAQ groups that feed the AI chat, link to articles and emit Google FAQ schema (needs a new table — approval
requested); testimonials with role, project, city, rating, photo and Live; client-logo display settings
(show on home, small title, heading, grid vs sliding, speed, grey, reorder); team with starter roles,
department, Featured, pages tick-list, reorder and Live.

**P1E — Media library (2 sessions).**
Trash and restore, optimise large images, make responsive (480/960 WebP), used-on list, replace picture,
crop/resize, bulk alt text, and the counters (files, MB, unused, used, broken). Upload carries filename, alt,
title and caption.

**Done when:** every starred v2.1 Content screen exists in the OS with the same fields, creates real public
content, and a non-technical person can go from “New article” to a published post without opening the block
editor.

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
