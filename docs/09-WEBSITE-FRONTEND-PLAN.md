# 09 · Website frontend plan (complete site, 142 URLs)

**Version 1.0 — 16 Oct 2026.** Owner decisions taken this morning (D-67…D-70):

| Decision | Choice |
|---|---|
| Build order | **CMS first, then import.** Every page becomes an editable record; nothing is hard-coded. |
| Service structure | **6 core service pages.** The other 54 service URLs 301 to the closest one. |
| Tracking | **GA4 + Google Tag Manager + Search Console + Microsoft Clarity.** |
| Content | **Import all 142 URLs, then humanize the pages that matter first** (home, 6 services, top cities, top insights). |
| Design | **Keep the old site's look.** Colour, font and tokens are approved (docs/05); sections and services need improvement. |

Everything here is a plan. Nothing in this document changes running code.

---

## 1. Where we are

- `woodex.com.pk` runs this repository (Hostinger Git deploy, no SSH). `/` serves the new **home page** (commit
  `54f04c4`), which is indexable, carries JSON-LD and a working enquiry form that creates a CRM lead.
- **Every other old URL returns 404.** The old site was replaced by the deploy, so 141 URLs are currently lost.
- The website is the last big phase (P6). Sales, CRM, quotations and the installer are done (docs/01).

**Consequence:** this is the highest-cost week for SEO. Batch 1 exists to stop the bleeding.

---

## 2. Architecture: CMS first

Pages live in the database, not in Blade files. Blade keeps the *section components*; the database holds *which
sections a page has, in what order, and with what text*.

### 2.1 Tables (one migration set, part of batch 1)

| Table | Purpose |
|---|---|
| `pages` | id, `type` (home, service, city, area, project, insight, about, contact, estimator, booking, legal), `slug` (unique, with trailing slash), `parent_id`, `title`, `excerpt`, `status` (draft / review / published), `published_at`, `template`, `author_id`, `seo` (JSON), `schema_type`, `featured_image_id`, `sort`, timestamps, soft deletes |
| `page_blocks` | id, `page_id`, `type` (hero, intro, services, process, gallery, faq, cta, form, specs, price_table, related, reviews, text, map), `position`, `data` (JSON: heading, body, list items, image ids, links), `visible` |
| `media` | id, path, disk, alt, width, height, `focal`, title, `folder`, `uploaded_by` (replaces ad-hoc files in `public/img`) |
| `redirects` | id, `from_path`, `to_path` (or external), `code` (301/302), `hits`, `note` — the 301 map of §3 |
| `page_versions` | a snapshot of a page's blocks + SEO before each publish (rollback in one click, audit trail) |
| `navigation` | menus (header, footer, service menu) as editable trees |
| `page_views`? | no — tracking lives in GA4/Clarity (§12), not in our database |

`seo` JSON per page: `title`, `description`, `canonical`, `og_image_id`, `robots` (index/noindex), `schema`
(optional override), `focus_keyword`, `last_reviewed`.

### 2.2 Admin screens (batch 1, then extended)

| Screen | What it does |
|---|---|
| Website → Pages | filterable list (type, status, URL, last updated, SEO score), duplicate, unpublish |
| Website → Pages → Edit | section list with drag ordering, add/remove section, inline editing of every text, image and link; SEO panel; live preview; Publish / Schedule / Revert |
| Website → Media | upload, folders, alt text required, WebP conversion on upload, size shown |
| Website → Redirects | the 301 map, hit counts, bulk import from CSV |
| Website → Menus | header, footer and service menus |
| Website → Settings | theme tokens (same builder as before), tracking IDs, consent banner text |

**Rule for later:** a page may only be published when title, description, H1, featured image with alt text and at
least one content section exist. The editor blocks publishing otherwise (and the tests assert it).

### 2.3 Rendering and speed on shared hosting

- One catch-all route `GET /{slug}` (trailing slash canonical, non-slash → 301) resolves `pages` by slug, then
  renders its blocks through a `SectionRegistry` that maps block type → Blade component. Unknown slug → the
  301 map → else 404 page.
- **Full-page cache**: rendered HTML in the Laravel cache, keyed by page + `updated_at`; cleared on publish.
  Shared hosting has no Varnish, but the file/database cache is enough for ~90 mostly-static pages.
- Images: WebP, responsive `srcset`, explicit width/height, lazy below the fold, hero preloaded. Budget:
  **< 200 KB images per page above the fold, < 1.5 MB total**.
- No new JavaScript on public pages beyond the existing site bundle (Alpine + the form). No Livewire on the
  public site — it would double the payload.

---

## 3. URL map: 89 live pages + 54 redirects = 143 URLs

_The map itself is `platform/database/data/legacy-website.php` (read through `App\Domain\Website\Data\LegacyUrlMap`);
`tests/Feature/Website/LegacyUrlMapTest.php` checks it and `LegacyUrlParityTest.php` requests all 143 URLs._

One URL is new: `/furniture/` did not exist on the old site and now holds the wardrobe, kitchen and dressing room
work, so the old site's 142 URLs are joined by the one page the plan added.

**Live pages (89)**

| Group | Count | URLs |
|---|---|---|
| Home | 1 | `/` |
| Utility | 6 | `/services/`, `/about/`, `/contact/`, `/estimator/`, `/book-a-visit/`, `/privacy/` |
| Core services | 6 | `/interior-design/`, `/fit-out/`, `/renovation/`, `/architecture/`, `/3d-visualization/`, `/furniture/` |
| Cities | 12 | `/lahore/`, `/karachi/`, `/islamabad/`, `/rawalpindi/`, `/faisalabad/`, `/multan/`, `/peshawar/`, `/quetta/`, `/sialkot/`, `/hyderabad/`, `/gujranwala/`, `/bahawalpur/` |
| Lahore areas | 8 | `/lahore/dha/`, `/lahore/gulberg/`, `/lahore/bahria-town/`, `/lahore/cantt/`, `/lahore/johar-town/`, `/lahore/model-town/`, `/lahore/lake-city/`, `/lahore/wapda-town/` |
| Insights | 49 | `/insights/` + 48 articles (slugs unchanged) |
| Projects | 7 | `/projects/` + 6 case studies (slugs unchanged) |

**301 redirects (54)** — each old service URL to the core service that answers the same intent:

| → Target | Old URLs |
|---|---|
| `/interior-design/` (21) | apartment-interior-design, bathroom-design, beauty-salon-design, cafe-interior-design, commercial-interior, coworking-space-design, false-ceiling-design, gym-design, healthcare-design, hotel-interior-design, office-interior-design, residential-basement-design, residential-bedroom-design, residential-dining-room-design, residential-home-office-design, residential-kids-room-design, residential-living-room-design, restaurant-interior-design, retail-design, showroom-design, spa-design |
| `/architecture/` (13) | 1-kanal-house-design, 10-marla-house-design, 2-kanal-house-design, 5-marla-house-design, educational-buildings-design, farmhouse-design, front-elevation-design, healthcare-facilities-design, master-planning, office-buildings-design, restaurant-cafe-building-design, retail-buildings-design, shopping-mall-design |
| `/renovation/` (10) | commercial-renovation, complete-home-redesign, healthcare-renovation, interior-home-refurbishment, kitchen-renovation, office-renovation, residential-renovation, restaurant-cafe-renovation, retail-showroom-renovation, specialized-renovation-services |
| `/fit-out/` (7) | commercial-fit-out, healthcare-fit-out, office-fit-out, pharmacy-fit-out, restaurant-fit-out, retail-fit-out, turnkey-design-build |
| `/furniture/` (3) | dressing-room-design, kitchen-design, wardrobe-design |

**Safety valve (recommended, costs nothing once the CMS exists):** Search Console tells us within ~6 weeks which
of these 54 had impressions or clicks. Any URL with traffic becomes a real sub-service page on its old URL and the
redirect is removed. The redirect table keeps a `hits` counter, so we also see arrivals we should honour.

---

## 4. Section library and the improvements you asked for

The old look stays: dark photographic hero, eyebrow + big Nohemi heading, grid lines, pill buttons, soft section
bands, card grids. What changes is **what each section says and how it is structured** — the old pages were
slogan-heavy and thin on facts.

| Section | Old site | New (improved) |
|---|---|---|
| **Hero** | One headline + two buttons | Headline keeps a *promise + place* ("Interior design in Lahore that survives real family life"), one-line proof under it, two CTAs, and a 4-cell fact strip (since 2016 · 200+ spaces · 6 disciplines · 5.0/267 reviews). Hero image is the LCP element: preloaded, never lazy. |
| **Intro / E-E-A-T** | Two paragraphs of brand voice | "What you get" paragraph + a **key facts** definition list (founded, studio address, hours, coverage, services) — the block AI assistants quote. One photograph of the studio or a finished space. |
| **Services grid** | 6 cards, one line each | 6 cards with 2-line descriptions (kept from the old copy, they are good) + a "what's included" list on each service page itself. |
| **Process** | 4 stages | Same 4 stages, each with **what you receive** and **who does it** — the two questions clients actually ask. |
| **Proof** | Reviews + client logos | Reviews stay verbatim (never rewritten) with name, date and source; logos stay; **add** 3 project studies with numbers (area, duration, scope). |
| **Selected work** | 6 images, captions | 6 studies with category + discipline tags, each linking to a case study (once photos are real, P-12). |
| **FAQ** | 6 Q&A | 6 Q&A per page type, answer-first (first sentence answers, then detail), mirrored in FAQPage schema. |
| **CTA / form** | Form only | Form **+** NAP block (address, phone, WhatsApp, hours) **+** "what happens next" (we reply within one working day with 3 questions). |
| **Footer** | 4 columns | Same, plus service list generated from the CMS, NAP, and a "areas we serve" link to the cities index. |

New section types in the library: `specs` (materials/finishes table), `price_table` (indicative bands — ranges,
never fixed prices), `related` (internal links by type), `map` (Google Maps embed, lazy), `gallery`
(project photos), `author` (E-E-A-T byline on insights).

---

## 5. The 6 service pages — complete definition

Every service page has the same skeleton. This is the "complete services" you asked for; the copy is written
from the old site's content and expanded with what clients ask us.

1. **H1:** `[Service] in Lahore` + promise (e.g. "Fit-out that lands on the day we said it would").
2. **Intro (60–90 words):** who it is for and the one problem it removes.
3. **What's included** — 6–10 bullets, concrete deliverables (drawings, BOQ, samples, supervision, snagging).
4. **How it runs** — the 4 stages, tailored to that service (what happens, what you receive).
5. **Indicative investment** — a band per unit (per sq ft / per room / per drawing) with "what moves the number
   up or down" (3 bullets). Ranges only; the estimator gives a personal figure.
6. **Materials & finishes** — the `specs` table (only where it applies: fit-out, furniture, renovation).
7. **FAQs** — 6 questions, answer-first, from the real questions in the CRM.
8. **Related projects** (3) + **related insights** (3) + **areas served** (link to `/services/` + cities).
9. **CTA** — the enquiry form, prefilled service value.

| Service | Core promise | Included (summary) | Unit of price |
|---|---|---|---|
| **Interior design** | Rooms designed around how you actually live | Space planning, 2D layouts, 3D views, material palette, lighting plan, furniture layout, tender drawings | per sq ft of built-up area |
| **Fit-out** | One team, one programme, shell to handover | Partitions, ceilings, HVAC/electrical coordination, joinery, finishes, furniture install, snagging | per sq ft, shell vs full |
| **Renovation** | Keep using the space while it improves | Condition survey, phasing plan, protection/dust control, phased execution, handover snagging | per sq ft + itemised |
| **Architecture** | The building and the interior as one idea | Concept, elevations, 3D massing, working drawings, approval drawings, site supervision | per sq ft of covered area |
| **3D Studio** | See it before you pay for it | 4K stills, walkthrough, 360 tour, 2 revision rounds, material studies | per view / per walkthrough |
| **Furniture** | Joinery made to the drawing | Kitchens, wardrobes, vanities, workstations, fitted storage, hardware, installation | per rft / per sq ft of panel |

---

## 6. City and area pages (20)

Google treats near-duplicate city pages as doorway pages. Rule: **every city page must contain at least three
facts that are not on any other page**, or it does not get published.

Mandatory unique content per city: (1) the areas covered, named; (2) one local project note or photograph —
"work completed in DHA Phase 6, 2025"; (3) a local logistics note — travel, society approvals, material
lead times; (4) the studio's NAP and a link to the nearest area page.

Lahore area pages add: society/association approval process, typical plot sizes and the house types we design
there (5 marla, 10 marla, 1 kanal), and the area-specific services (renovation of older houses in Model Town,
new builds in Bahria Town). Schema: `LocalBusiness` areaServed + breadcrumbs; the city pages carry no fake
addresses.

---

## 7. Insights (48 + index)

- **Import**: all 48 keep their URL, title, meta and body text into `page_blocks` (type `text`).
- **Humanize the top 12 first** (cost, process and material questions — they carry the traffic), then the rest in
  batches of 12.
- **Author**: byline + role + one line of credentials (E-E-A-T). No invented people: if the owner does not name
  an author, the byline is "Woodex Studio" with the studio's founding year and city.
- **Clusters**: each insight links to 2 sibling articles and 1 service page; each service page links back to 3
  insights. Cluster map is part of the plan deliverable, not left to chance.
- **Schema**: `Article` (or `BlogPosting`) with author, datePublished, dateModified, image.

---

## 8. Projects (6 + index)

Fields: title, client type, location, area (sq ft), duration, disciplines, scope, materials, photograph set
(6–12), outcome (1–2 measured sentences), testimonial if the client agreed.

**Blocker: P-12** — real project photographs. The images currently on the live site came with the old template;
presenting them as completed Woodex work is a trust risk. Until real photos arrive, project pages are
**draft** and the home "Selected work" section keeps its neutral "studies" wording. This is the one thing I
cannot fix from my side.

---

## 9. Content: humanized, not generated

Rules every page follows (and the review queue checks):

- **Answer first.** The first sentence of any section answers the heading. No "In today's fast-paced world".
- **Banned phrases:** "elevate your space", "transform your home", "look no further", "nestled", "seamlessly",
  "cutting-edge", "one-stop solution", "we understand that", "delve", "it's important to note".
- **Concrete over abstract:** a number, a material, a place, a duration. "Wardrobes in moisture-resistant MDF
  with soft-close hardware" beats "premium storage solutions".
- **Local, always:** Lahore, PKR, marla/kanal, Mon–Sat 9:30–6:30, society names. Pakistani spelling
  ("visualisation", "aluminium").
- **Short sentences, long paragraphs avoided.** 2–4 sentence paragraphs; one idea each.
- **Never invent:** no invented clients, staff names, awards, certifications or reviews. Reviews are verbatim.
- **Voice:** plain, confident, first person plural ("we survey", "we price"). No exclamation marks, no emoji.

Per-section writing templates live with the CMS editor as placeholder text, so whoever edits later sees the
expected shape.

---

## 10. Design system (approved, extended)

Colour, font and tokens are approved and unchanged (docs/05): Nohemi headings, Inter body, navy `#0F1E36`,
wood `#A87442`, sand `#FCF2E8`, radius 8px. Extensions needed for the section library:

- **Type scale**: display `clamp(38px, 6vw, 72px)`, H2 `clamp(28px, 3.6vw, 44px)`, H3 20/24px, body 15px/1.65,
  small 13px. One scale, used everywhere (the old site mixed three).
- **Ratios**: hero 16:9, cards 4:3, portraits 4:5, logos 2:1. Fixed ratios stop the layout jumping.
- **Spacing**: sections `py-16 md:py-24`, container `max-w-6xl`, card gap 24px.
- **Accessibility floor** (D-66): text contrast ≥ 4.5:1, secondary text is the new `#6b6b6b`, standalone links
  ≥ 24px tall, focus rings visible, every image has alt text, headings in order (one H1).
- **Dark sections** (hero, process, CTA) use navy with the `sand-*` text ramp only — no new colours.

---

## 11. SEO, AIO / GEO and E-E-A-T

### 11.1 Technical (per page, enforced by the editor + tests)

- One H1; title ≤ 60 chars; description 120–160 chars; canonical self-referencing; trailing-slash canonical with
  301 from the non-slash form; `robots.txt` allowing everything except `/admin` and `/q/`; `sitemap.xml`
  generated from published pages only (never a 404 — D-65).
- `hreflang` not needed (English only). `lang="en"`.
- Images: WebP, `srcset`, width/height, descriptive file names, alt text required in the media library.
- Core Web Vitals targets on 4G: LCP < 2.5 s, CLS < 0.1, INP < 200 ms. Measured in Search Console + Lighthouse
  in CI (the sandbox runs a real Chromium).

### 11.2 Schema per page type (JSON-LD @graph, as on the home page)

| Page | Schema |
|---|---|
| Home, service, city, area | `LocalBusiness`/`ProfessionalService` + `WebSite` + `WebPage` + `BreadcrumbList` + `FAQPage` + `Offer` (service pages) |
| Insight | `Article` + author + `BreadcrumbList` + `FAQPage` when it has Q&A |
| Project | `CreativeWork`/`Article` with `about` → the service, plus images |
| Contact/booking | `LocalBusiness` with `openingHoursSpecification` and `geo` once coordinates are confirmed |

### 11.3 AIO / GEO (being quoted by ChatGPT, Gemini, Perplexity, AI Overviews)

- **Answer-first** sections and FAQs (§9) — assistants lift the first sentence.
- **Entity consistency**: identical NAP, name and description everywhere (site, Google profile, LinkedIn,
  Instagram, PDFs). One canonical About paragraph reused verbatim.
- **`llms.txt`** at the root: a plain-text map of the site with 2-line summaries and the canonical facts
  (founded, services, areas, hours, contact) — cheap, and it is what crawlers read.
- **Key facts block** on home and service pages (already on the home page): a definition list of verifiable
  facts. Assistants prefer lists over prose.
- **Comparisons and numbers**: price bands, durations, deliverable lists — cited far more often than adjectives.
- **Measurement**: GA4 channel group "AI referrals" from the known assistant referrers, reviewed monthly.

### 11.4 E-E-A-T

- **Experience**: project studies with area, duration and scope; 200+ completed spaces; the 4-stage process.
- **Expertise**: 6 disciplines in one studio, 3D in-house, joinery made to drawing; insights written by named
  staff once the owner names them (P-10).
- **Authoritativeness**: client list (Shell, PSO, Total Parco, Attock, MG, Xiaomi), 267 Google reviews linked to
  the profile, memberships if any (P-14).
- **Trust**: NAP on every page, hours, privacy page, consent on the form, "we reply within one working day",
  real photographs (P-12), and no invented claims anywhere.

---

## 12. Tracking (GA4 + GTM + Search Console + Clarity)

| Tool | Job | Where the ID is entered |
|---|---|---|
| **GA4** | Numbers: sessions, pages, conversions | Website → Settings (env `GA4_MEASUREMENT_ID`) |
| **GTM** | All tags and events without code changes | Settings → `GTM_CONTAINER_ID` (hosted snippet, no third-party script sprawl) |
| **Search Console** | Google's own data: queries, indexing, CWV | Domain property, verified once by the owner (P-13) |
| **Clarity** | Heatmaps, session recordings, rage clicks | Settings → `CLARITY_PROJECT_ID` |

**Events (via GTM, defined once, tested in the browser check):** `enquiry_submit` (conversion), `whatsapp_click`,
`call_click`, `email_click`, `pdf_download`, `estimator_complete`, `booking_submit`, `scroll_50/90`,
`faq_open`, `gallery_next`, `outbound_click`. Each carries `page_type` and `service` from the CMS.

**Consent:** Pakistan has no cookie law, but GA4 + Clarity need a notice; a small banner with Accept/Decline sets
`consent mode` before tags fire. Decline = no analytics cookies.

**Also:** conversion import from the CRM is already possible — every web lead stores UTM/gclid/fbclid and
`page_url` (CAP-001/002), so offline "won" values can be sent back to GA4 later without touching the forms.

**KPIs reviewed monthly:** qualified enquiries (not sessions), enquiry rate by page type, top queries and pages,
AI referrals, LCP/CLS, 404s and redirect hits.

---

## 13. Import pipeline (frontend-v1 → CMS)

1. **Extract** — parse each of the 142 HTML files: title, meta description, canonical, H1–H3, paragraphs, lists,
   images (with alt), links, FAQ pairs, tables, schema already present.
2. **Classify** — map to a page type and slug using §3 (the map, not a guess). Unknown → review queue, never
   published blind.
3. **Convert to blocks** — from what a section *contains*, not from its CSS classes: a section with an H1 is the
   hero, `.faq-item`s are questions, a grid of headings with copy becomes services, an ordered list the process,
   a table the price bands, a list of links to other pages the "related" section, a form the enquiry block. An
   article keeps its whole body in one `<section>`, so a long one is split at its H2s and each sub-heading becomes
   its own block. Old inline styles and scripts are dropped; only the token classes remain.
4. **Images** — copied to `media`, scaled to 1600px and re-encoded as WebP, keeping the original alt text and
   deduplicated by content (68 photographs carry the whole site).
5. **SEO** — carried over: title (≤ 60), description (120–165) and canonical. All 88 imported pages passed the
   editor's checklist without a rewrite, which is why they could be published straight away.
6. **Review queue** — every imported page is a **draft** with the editor's checklist (H1, description, image alt,
   unique content ≥ 3 facts for city pages, no banned phrases). Publish is per page or per batch.
7. **Redirects** — the 54 rows of §3 are inserted as 301s with `hits = 0` (a migration seeds them, the owner can
   edit them in the admin).
8. **Links** — a second pass points every imported link at a page that exists: an old URL that is now a redirect
   points at its target, and a card that links to the page it stands on loses its link.
9. **Parity test** — `tests/Feature/Website/LegacyUrlParityTest.php` requests all 143 URLs and asserts: pages 200
   with the canonical trailing slash, redirected URLs one 301 to a 200 page, no 404s, no chains.

**How the content reaches the server.** The old HTML lives on a laptop, not on the server, so
`woodex:export-website` writes `database/data/website-bundle` (pages.json + the photographs) into the repository,
`woodex:import-website` reads it back, and the migration that ships it runs with the owner's one-click
"Database update". Both imports are idempotent (`legacy_refs`: one row per slug with a checksum of what was
imported), so a re-import never overwrites a page the owner has since edited.

---

## 14. Delivery plan

| Batch | Content | Result |
|---|---|---|
| **1 — CMS** | Tables, admin editor, media library, redirects, menus, routing, page cache | **Delivered 16 Oct 2026** (§14.1) |
| **2 — Stop the 404s** | `woodex:import-legacy` + the 143-URL parity test, insert the 54 redirects, publish 6 services + services index + about + contact + privacy + estimator + book-a-visit | **Delivered 16 Oct 2026** (§14.2): 12 pages live, 54 URLs rescued, 89 drafts waiting in the admin, parity green |
| **3 — Depth** | 12 cities + 8 Lahore areas + insights index; humanize home and the 6 services | Local search covered; the money pages read human |
| **4 — Content** | 48 insights imported and humanized in 4 waves of 12; internal linking clusters | Topical authority |
| **5 — Proof** | 6 project case studies + tracking + `llms.txt` + consent banner | Trust signals and measurement |
| **6 — Tools** | Estimator and book-a-visit as real pages with the CRM | Lead quality up |

**Definition of done per batch:** Pest green · Larastan green · Pint green · `browser-check.mjs`,
`home-check.mjs`, `page-check.mjs`, `smoke.sh` and `iframe-check.mjs` green · parity test green (from batch 2) ·
`npm run build` committed · one commit pushed (which deploys to woodex.com.pk).

### 14.1 Batch 1 delivered (16 Oct 2026, D-71…D-77)

- **Database:** `pages` (title, slug, type, status, excerpt, SEO fields, schema type, author, publish dates,
  `updated_at` used as the cache key), `page_blocks` (section type, JSON `data`, `visible`, sort),
  `page_versions` (a copy of the input before each save, capped), `website_media` (path, disk, mime, size,
  dimensions, alt text, uploader), `redirects` (from → to, status, hit counter), `navigation_items`
  (header/footer, nested, "pages" dropdown, CTA flag).
- **Admin → Website:** Pages (search, filter by type/status, duplicate, take offline, delete), **Page editor**
  (fields, sections with add/remove/reorder, SEO panel with a live Google preview, publish checklist, version
  history with preview + restore), **Media** (upload, rename alt text, sees where an image is used),
  **Menus** (header/footer, nested, CTA), **SEO & Redirects** (add, CSV import/export, hit counter,
  the 404 log as a redirect suggestion), **Settings** (GA4, GTM, Search Console, Clarity, tracking on/off).
- **Public:** published pages answer at their own URL with the canonical trailing slash, `/sitemap.xml`
  lists them, and SEO & Redirects serves the 301s. Nothing is served for the admin domain.
- **Not in batch 1:** the importer (batch 2), the consent banner, `llms.txt` and the estimator and book-a-visit
  pages as real tools (batch 5/6).

### 14.2 Batch 2 delivered (16 Oct 2026, D-78…D-79)

- **All 142 old URLs are in the CMS:** 89 pages with 955 sections and 68 photographs, imported from the old HTML
  and stored as drafts. `/furniture/` was written by hand from the wardrobe, kitchen and dressing room pages it
  replaces.
- **Live now (12 pages + the home page):** the six core services, `/services/`, `/about/`, `/contact/`,
  `/privacy/`, `/estimator/` and `/book-a-visit/`.
- **54 URLs rescued:** every old service URL 301s to the service that answers it, with a hit counter.
- **Still draft (76 pages):** 12 cities, 8 Lahore areas, 48 insights, 6 projects and the two remaining index
  pages — they go live per batch once a person has read them (batch 3 and 4).
- **Parity:** `LegacyUrlParityTest` requests all 143 URLs in one test run (3.7s) and is green.

---

## 15. What I need from you

| # | Item | Why it blocks |
|---|---|---|
| **P-12** | Real project photographs (10–20 per project, phone is fine) | Project pages and "Selected work" cannot claim work we cannot show |
| **P-13** | Search Console access (add `owner@woodex.com.pk` / the Gmail as owner) | We cannot see which of the 54 redirects carried traffic, or indexing status |
| **P-10** | A named principal + one paragraph of credentials and, ideally, a photo | The strongest single E-E-A-T signal on the site |
| **P-14** | Any memberships, registrations, or supplier accreditations | Authoritativeness |
| **P-15** | GA4 / GTM / Clarity IDs (or "create them for me") | Tracking (batch 5) |
| **P-16** | Confirm business email (`info@woodex.com.pk`) and hours (9:30–18:30) in Settings → Business | NAP must be identical everywhere for local search |

None of these blocks batch 1 or 2. P-12 blocks batch 5's project pages; the rest are 10-minute tasks.

---

## 16. Risks

| Risk | Likelihood | Mitigation |
|---|---|---|
| Traffic loss during the cut-over | High (already happening) | Batch 2 first; parity test; 301s with hit counts; weekly Search Console review |
| Thin/duplicate city pages | Medium | §6 rule (3 unique facts) enforced by the editor |
| Old photos presented as real projects | Medium | P-12; neutral "studies" wording until then |
| Page speed on shared hosting | Medium | Full-page cache, image budget, no Livewire on public pages |
| Content drift after import | Medium | Review queue checklist; banned-phrase check; one owner approves batches |
