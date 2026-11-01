# 11 · Frontend v2: the upgrade plan for Woodex OS

**17 Oct 2026.** Written after the complete old website (frontend v1, 143 URLs) was loaded into the CMS and
checked page by page. This is the plan for what comes next: **SEO, then the sections, then the design** — with the
Woodex builder doing the work the studio can see.

Owner's words for this stage: *"next plan seo and section improve complete task with page builder etc."*

---

## 1. Where the website stands this morning

| | |
|---|---|
| Pages in the CMS | **143**, all published, all editable in Website → Pages |
| Old URLs | **143 of 143 kept** — no 301s left, nothing thrown away |
| Sections | 1 448 · Photographs 105 · Redirects 0 |
| Broken internal links | **0** · Orphan pages **0** |
| Words | min 489 · median 815 · max 1 738 — no thin pages |
| Every page | one `<h1>`, title ≤ 65 chars, description 120–165 chars, JSON-LD, alt text + dimensions on every image, a closing that asks for the next step |
| Sitemap | 143 URLs, all answering 200 |

The mechanics are finished. What v2 is about is whether those 143 pages *earn* their place — because 54 of them
were written by the old site as variations of the same six services.

### The one structural debt

| Kind of page | Count | State |
|---|---|---|
| The six disciplines the studio sells | 6 | Good, and the only services the footer and the OfferCatalog show |
| Specialist pages (kitchen design, office fit-out, 1-kanal house design, healthcare renovation…) | **54** | Each has its own URL, its own words and 500–900 of them; several read like siblings of a core service page |
| Cost guides | 48 | The strongest pages on the site |
| Cities + Lahore areas | 20 | Good, need local proof (photographs, a sentence that could only be about that place) |
| Projects, home, about, contact, estimator, booking, legal | 16 | Thin on photographs |

Publishing all 54 was the owner's decision, and it is the right one for a studio that really does all of that
work: they can be rewritten, they cannot be rewritten if they do not exist. Phase 1 is what stops them being a
liability.

---

## 2. What "v2" means

| | v1 (what is live now) | v2 (what this plan builds) |
|---|---|---|
| Pages | Imported from the old HTML, faithful to it | Rewritten in the studio's own voice, with real photographs |
| Look | The old site's design, kept by decision (D-70) | The same look, refined: design tokens the studio can change without a developer |
| Sections | The 15 block types the import produced | A deeper section library, and per-page patterns that repeat |
| Navigation | Built-in defaults (Website → Menus is empty) | The studio's own menus, built in the dashboard |
| Conversion | An enquiry form at the foot of every page | Chat, WhatsApp, the estimator and Book a visit working as one path |
| Measurement | Nothing connected | GA4 + GTM + Search Console + Clarity, and the audit running after every batch |

---

## 3. Phase 1 — SEO (do this first; it is where the money is)

### 1.1 Make the 54 specialist pages unmistakably different — *the one task that matters*

Duplicate pages do not just fail to rank, they split the signals of the pages they copy. For each of the 54: open
it in Website → Pages and give it

1. **A first paragraph that could only be on that page** — the client, the room, the constraint. ("A clinic's
   reception has to survive a queue at 9am and a mop at 6pm…")
2. **Two photographs of that kind of work** (see §3.5 — this is the blocker).
3. **A price or process table of its own** (the `price_table` and `specs` blocks exist for this).
4. **Questions only that page's client asks** (the `faq` block feeds the FAQPage schema).
5. **A title and description written for the searcher**, not for the site: "Office fit-out in Lahore: cost per
   sq ft, timeline and what is included".

Order: the pages people actually search (kitchen design, office fit-out, wardrobe design, 1-kanal and 10-marla
houses, restaurant and retail fit-out) before the long tail. Twelve pages done well beat 54 done quickly.

**Exit test:** no two pages share a title, a description or a first paragraph; every one of the 54 has at least
one photograph and one table that exists nowhere else.

### 1.2 Then the pages that carry the studio's name

The humanizing queue, in this order: **home** → **6 disciplines** → **/lahore/ + DHA + Gulberg** → the five cost
guides people land on most → **/about/** (the page that decides between two shortlisted studios).

### 1.3 Internal linking that is planned, not accidental

- Each specialist page links **up** to its discipline and **across** to two siblings (the `related` block).
- Each cost guide links to the service it prices and to the city it prices it for.
- Each city page links to its areas (done) and to two cost guides.
- **Exit test:** no page under eight internal links; every one of the 143 reachable in three clicks from `/`.

### 1.4 Structured data and the search console

- `Service` + `OfferCatalog` per discipline, `FAQPage` wherever there are questions, `BreadcrumbList` everywhere
  (already), `ImageObject` with real dimensions (already), `AggregateRating` **only when there is a verifiable
  Google profile** (P-11 — do not invent a rating).
- A `lastmod` the studio's edits actually move (it follows `updated_at` today).
- **Owner:** connect Search Console (P-13) and submit the sitemap, then watch which of the 143 get impressions.

### 1.5 Tracking (small, and everything else depends on it)

GA4 + GTM + Clarity IDs into Settings → Website (P-15). Nothing in phase 2 or 4 can be judged without it.

### 1.6 Speed

143 pages sharing the same 105 photographs: keep every image under 200 KB, lazy-load everything below the fold,
and measure Core Web Vitals on `/`, a discipline page and a cost guide. Shared hosting has no Varnish, so the
page cache (already per page, keyed on `updated_at`) is the whole story.

---

## 4. Phase 2 — Sections (the builder)

The import produced 15 block types. v2 deepens the library and, more importantly, gives the studio **patterns**
to reuse.

| Work | Why | Kind |
|---|---|---|
| **Before/after** block | The single most persuasive thing a renovation studio can show | New block type |
| **Testimonial with a face and a place** | Reviews are on the site as text; a named client in DHA is worth ten | New block type |
| **Team / principal** block | E-E-A-T: a named person with credentials (P-10) | New block type |
| **Coverage map** block | 12 cities and 8 Lahore areas, one glance | New block type |
| **Specifications + price bands** used on every service page | Answers the question the visitor came with | Content, existing blocks |
| Reorder-and-duplicate in the editor | Moving a section between pages by hand is tedious today | Builder |
| A page pattern the studio can apply to a new page ("service page", "cost guide") | 54 pages need the same shape | Builder |

**Exit test:** a new specialist page can be built, filled and published inside 30 minutes without a developer.

---

## 5. Phase 3 — Design (tokens, not a redesign)

The look stays (D-70). What changes is that the studio can move it: Settings → Website → **Design tokens** —
colour, type scale, spacing, radius, shadow — written to CSS variables, previewed live, versioned so a change can
be undone. v1's colours and fonts are the defaults; nothing about the site looks different on the day it ships.

---

## 6. Phase 4 — Conversion

Everything already on the site — the enquiry form, the estimator, Book a visit, WhatsApp — plus:

- **The website chat widget** (CNV-001/008): the next item in P4. A question asked on a cost guide becomes a
  conversation in the same inbox the WhatsApp messages arrive in.
- **Canned replies** (CNV-004) for the questions every client asks first.
- **One path**: whichever door a visitor uses (form, chat, WhatsApp, phone), the studio sees one thread.

---

## 7. Order of work

| # | Work | Who | Depends on |
|---|---|---|---|
| 1 | **Database update on woodex.com.pk** (one click) | Owner | — |
| 2 | GA4/GTM/Clarity/Search Console IDs, connect Search Console | Owner (P-13, P-15) | — |
| 3 | Real project photographs into the media library | Owner | — |
| 4 | Fill Website → Menus (the built-ins are good; this is the studio's wording) | Owner, 15 min | 1 |
| 5 | **SEO phase 1.1** — the 54 specialist pages, best twelve first | Owner + agent | 3 |
| 6 | Humanizing queue (1.2) | Owner + agent | 5 |
| 7 | Tracking wired (1.5) | Agent | 2 |
| 8 | Chat widget + public chat API (P4) | Agent | — |
| 9 | New block types + page patterns (phase 2) | Agent | 8 |
| 10 | Design tokens (phase 3) | Agent | 9 |
| 11 | Structured data pass, Core Web Vitals (1.4, 1.6) | Agent | 7 |

Items 1–4 are the owner's and unblock everything. 7–11 are code and can run alongside 5–6.

---

## 8. How we know a batch is finished

1. `php tools/website/audit.php` — 143 pages, 0 broken links, 0 orphans, no page without a photograph or a
   closing, no title over 65 characters, no description outside 120–165.
2. Pest, Larastan and Pint clean; the preview checked (frontend + dashboard) before every push.
3. The batch's pages read as if the studio wrote them, because they did.

---

## 9. Left over from v1, noted here so it is not lost

- **`/furniture/`** was seeded from the wardrobe page (the old site had no furniture page). Its words are its own
  now, its photographs are not — it needs them, or it stays a copy.
- **Five pages carry no photograph**: `/services/`, `/projects/`, `/book-a-visit/`, `/estimator/`, `/privacy/`.
  The last two are meant to be plain; the first two are not.
- **The old system's own admin** (Admin v2 in the P22.1 package) had CRM, WhatsApp, social and backup features.
  Each has its counterpart in Woodex OS; its *data* is imported with the v2.1 runbook (docs/08), not with this
  plan.
