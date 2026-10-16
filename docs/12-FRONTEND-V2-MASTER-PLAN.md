# 12 · Frontend v2 — master plan

_The plan for the website's next version. **Nothing here is built yet.** The original v1 site is live on
woodex.com.pk and stays live until v2 is finished here, checked, approved, and only then deployed (owner, 17 Oct)._

Decisions this plan is built on: **D-105…D-110** (docs/07). Where this document and those decisions disagree, the
decisions win and this document is corrected.

---

## 1. What v2 is for

v1 is 142 hand-made HTML files. They work, they rank, and they bring the enquiries in. They are also frozen: a
copy change means editing HTML, a new page means copying an old one, and nothing the studio learns in the CRM
feeds back into the site.

v2 keeps every page and every URL, and makes three things true:

1. **The studio can change the site itself** — copy, images, pages, menus, navigation — from the dashboard, without
   a developer and without touching a file.
2. **The design becomes a system** — one set of tokens (colour, type, space, radius) that every page obeys, so the
   site can be re-skinned without rebuilding it, and no page can drift out of step with the rest.
3. **The site gets found** — by Google and by the AI answer engines people increasingly ask first, with redirects
   that never lose an old URL.

What v2 is **not**: a new look. The owner's words were "frontend v1 no need to change design same as need to use".
The design of v2 is the design of v1, expressed through tokens instead of hard-coded values. If, once the tokens
exist, the studio wants a different look, changing the tokens is a ten-minute job — but that is a separate decision,
not part of this plan.

---

## 2. The five decisions (owner, 17 Oct)

| # | Decision | Why it was taken |
|---|---|---|
| **D-105** | **Laravel + Blade + Tailwind v4, server-rendered.** Not React, not a static export. | Hostinger shared hosting runs PHP only — no Node, and no CI to build with. A JavaScript build step would mean building on a laptop and committing the output for every content change. Server rendering also keeps SEO, schema and Core Web Vitals honest, and **Livewire 4 has drag-and-drop built in**. |
| **D-106** | **Two editors: the Woodex dashboard owns pages, media, menus, redirects, SEO and settings; Sveltia CMS is added for long-form writing** (guides, insights) as Markdown/JSON in the repository. | Long-form writing wants the git workflow — versions, diffs, previews. The pages that earn money must stay next to the CRM, so a lead can become a quotation without leaving the system. Cost: two logins, two sources of truth. The split is drawn once (§7). |
| **D-107** | **Blocks by default; a free-form canvas only for campaign landing pages.** | Structured blocks cannot break spacing, mobile layout or the theme, and they keep 143 pages consistent when a token changes. The canvas exists because a campaign page sometimes must be different — and it is the one page type where that risk is acceptable. |
| **D-108** | **The SEO "agent system" is all three layers:** AI-crawler rules, an automatic auditor, and an AI assistant that proposes changes for a human to approve. | Training crawlers and search crawlers are now separate agents, so "block AI" is no longer one switch. An assistant that publishes on its own is a liability. |
| **D-109** | **A new design enters through a Preline/Tailwind theme pack**, adapted once into tokens and sections — not through a general HTML import. | No tool imports arbitrary HTML cleanly: not Webflow, not Framer, not Builder.io. The honest path is a real Tailwind theme mapped once into the system, after which every section is native to the builder. |

---

## 3. What exists today (so the plan builds on it, not over it)

Already built in this repository, tested, and working:

| Piece | State |
|---|---|
| Pages in the CMS | **143 pages** imported from the old site, editable, with `type`, `status`, SEO fields, revisions |
| Blocks | **14 block types** (hero, intro, text, services, process, gallery, faq, related, form, reviews, specs, author, map, clients) + a 15th template (`price-table`) |
| Page editor | Livewire screen: add, edit, reorder, show/hide blocks; version history (`page_versions`) |
| Rendering | `PageRenderer`, `SiteRouter` (reserved paths `/q`, `/i`), `PageCache`, `PageValidator` |
| Media | Media library with conversions; **74 imported images**, most without useful names |
| Menus | `navigation_items`, with city links and defaults (`Menus::defaults()`) |
| Redirects | `redirects` table + admin screen |
| Settings | Website settings + business settings (NAP, hours, social) |
| Live site | The 142 original files, served verbatim; forms bridged into the CRM (docs/11 §11.0.1) |

**Missing, and therefore what v2 actually builds:** design tokens and a theme builder; a named section library; a
header/footer builder; drag-and-drop in the page editor; a theme import pipeline; the SEO agent system; and the
Sveltia bridge for long-form content.

---

## 4. Architecture: how the site is served

```
                    ┌──────────────────────────────────────────────┐
   Browser  ──────► │ Laravel (PHP) — Blade + Livewire 4            │
                    │  SiteRouter → PageRenderer → sections/*.blade │
                    └──────────────┬───────────────────────────────┘
                                   │ reads
                    ┌──────────────▼───────────────────────────────┐
                    │ pages · page_blocks · media · menus          │
                    │ redirects · theme_tokens (new) · sections    │
                    └──────────────┬───────────────────────────────┘
                                   │ written by
        ┌──────────────────────────┴─────────────────┐
        │ Woodex dashboard (Livewire, one login)     │ Sveltia CMS (git, long-form only)
        │ pages, media, menus, redirects, SEO, theme │ content/*.md → imported as pages
        └────────────────────────────────────────────┘
```

**Why no build step.** Tailwind v4 tokens are real CSS custom properties, so a theme can be swapped at runtime:
the dashboard saves tokens, the page prints them as one small `<style>` block, and every utility that reads a token
updates — no Node, no recompile, no deploy. This is the single most important technical fact in this plan: it is
what makes a theme builder possible on PHP-only hosting.

```
:root { --wx-bg: #0c1628; --wx-accent: #c8a24a; }   /* dashboard writes these */
@theme inline { --color-surface: var(--wx-bg); --color-accent: var(--wx-accent); }
```

**Hosting constraints that shape everything:** PHP only, no Node, no CI (the GitHub App cannot write
`.github/workflows/`), Git push deploys via hPanel, and Hostinger's deploy **replaces files but does not delete
removed ones**. Every page must be reachable by a real URL that never changes.

---

## 5. The modules

Each module below says what it is, what it is built on, and what "finished" means.

### 5.1 Theme Builder (design tokens) — *build first*

Everything else depends on it: a page that hard-codes `#0c1628` cannot be re-themed.

- **What:** a Settings screen that edits the site's tokens — colour (brand, surface, text, accent, borders),
  typography (family, scale, weights), spacing scale, radii, shadows, container width, button styles, dark mode.
- **How:** tokens live in the database (`theme_tokens`), are printed per page as CSS custom properties, and every
  section template reads tokens **only** — never a hard-coded value. A token change re-skins all 143 pages at once.
- **Guard rails:** contrast checked as you type (WCAG AA for body text), a live preview of a real page in an iframe,
  and **draft vs published themes**, so a half-finished palette never reaches woodex.com.pk.
- **Finished when:** the v1 palette is expressed as tokens, all 15 section templates use tokens only, and changing
  the accent token changes every page with no code edit and no deploy.

### 5.2 Section Library

- **What:** the 15 existing section templates become **named, described, previewable** entries with a thumbnail and
  the fields each one takes; plus a **saved-sections** feature (save any block you have configured, reuse it
  anywhere), and section **categories** (Hero, Proof, Content, Conversion, Footer-ish).
- **How:** each section declares its schema in PHP (fields, types, defaults, validations). The library is the same
  schema the page editor renders — one declaration, two uses.
- **Finished when:** a non-technical user can add "Gallery" to a page, fill four fields, and see it look right —
  and a new section can be added by a developer in one file with no other changes.

### 5.3 Page Builder

- **What:** the existing editor gains **drag-to-reorder** (Livewire 4 `wire:sort`, no third-party library), drag a
  section **in** from the library, inline text editing, per-block show/hide and duplication, device preview
  (mobile/tablet/desktop), and **draft → preview → publish** with a shareable preview link.
- **Campaign pages:** a separate, clearly-marked **free canvas** mode for landing pages only, where sections can be
  positioned freely. It is excluded from theme-token re-skins by design (a hand-placed page cannot be re-flowed
  automatically) and is flagged in the Pages list so nobody mistakes it for a real page.
- **Finished when:** the studio builds a new page from the library without help, and no page can be published while
  it fails the validator (one h1, alt text, no broken links, title and description set).

### 5.4 Header & Footer

- **What:** a visual builder for the two things that appear on every page: logo, navigation (with dropdowns and the
  12 city links), a CTA button, and the footer columns with the studio's NAP, hours and social links.
- **How:** built on the existing `navigation_items`, extended with a small set of header/footer layouts (not a free
  canvas — these must stay consistent across 143 pages).
- **Finished when:** the header and footer are edited from the dashboard, and the change appears on every page.

### 5.5 Media

- **What:** the existing library, plus: alt-text required at upload, automatic WebP/AVIF conversion with `srcset`
  sizes, renaming, folders, duplicate detection, and usage counts ("used on 7 pages") so nothing is deleted blindly.
- **Also:** finish naming the 74 imported images and fill the 5 pages that carry no photograph
  (`/services/`, `/projects/`, `/book-a-visit/`, `/estimator/`, `/privacy/`) — an open item since the launch audit.

### 5.6 Menus, Pages, Website Settings

Existing screens, completed rather than rebuilt: menus get drag ordering and scheduled visibility; the Pages list
gets filters by template/status/author, bulk actions and a last-reviewed date; Website Settings gains analytics
(GA4, GTM, Clarity), Search Console verification, and the business identity that schema.org repeats everywhere.

### 5.7 SEO & Redirects, and the agent system (D-108)

Three layers, in this order:

1. **Crawler rules.** A managed `/robots.txt` with a rule per agent — allow the search crawlers that bring
   citations (OAI-SearchBot, Claude-SearchBot, PerplexityBot, Google-Extended), decide deliberately about training
   crawlers (GPTBot, ClaudeBot, CCBot), block the non-compliant ones at the edge; plus `/llms.txt` as a curated map
   of the best pages, a maintained `sitemap.xml`, canonicals, and JSON-LD on every page.
   *Honest note: Google says llms.txt is not needed for its own AI search. It is cheap, it is what agentic tools
   read, and it is published for that reason — not because it moves rankings.*
2. **The auditor.** A scheduled crawl of the live site: broken links, redirect chains, thin pages, duplicate
   titles and descriptions, missing alt text, orphan pages, Core Web Vitals from the field. Reported in the
   dashboard as a list, not a score.
3. **The assistant.** Suggests a title, a description, FAQ entries and schema for a page — **and never publishes
   them**. A person approves; the suggestion and the approval are both recorded.

**Redirects** stay a first-class screen: every URL ever published is kept (the old site's 54 specialist URLs were
turned into pages; nothing 301s away), with a warning when a redirect points at a redirect, and a log of which old
URLs still receive traffic.

---

## 6. The theme import pipeline (D-109)

Buying or choosing a Preline/Tailwind theme is the easy part; getting it *into* the system is the work. The
pipeline is deliberately **assisted, not automatic**:

1. **Parse** the theme's HTML into a DOM (server-side, PHP).
2. **Extract tokens** — every repeated colour, font size, spacing value and radius becomes a candidate token, with
   the most-used value proposed as the default.
3. **Extract sections** — repeated structural patterns (hero, feature grid, testimonial band, CTA) become candidate
   sections, each with its fields inferred from the content that varies.
4. **Human review** — the studio sees "we found 12 sections and 24 tokens; here is each one" and keeps, renames or
   discards each. Nothing enters the library un-reviewed.
5. **Conform** — the kept sections are rewritten as Blade templates that read tokens, and are tested on a real page
   before they are offered to anyone.

Arbitrary HTML will not survive this, and that is accepted: the pipeline's promise is *"a designer's theme becomes
native sections in an afternoon"*, not *"paste any URL"*.

---

## 7. How Sveltia CMS fits (D-106)

| | Woodex dashboard | Sveltia CMS |
|---|---|---|
| Owns | Pages, sections, media, menus, redirects, SEO, theme | Long-form: insights, guides, case studies |
| Storage | Database | Markdown/JSON in the repository |
| Login | The studio's own accounts and roles | **Each editor needs a GitHub account with push rights** |
| Sees the CRM | Yes — leads, quotes, projects | No |

**The split, drawn once:** Sveltia writes content files; a scheduled import turns them into pages (or updates the
page they belong to); the dashboard publishes. Content never exists in two editable places at once: if a page came
from a Sveltia file, the dashboard says so and offers "edit the file" instead of a second editor.

**This is the plan's biggest risk.** Two logins and two sources of truth are a real cost, and Sveltia is still pre-
1.0 with no built-in authentication of its own. It is worth it only if long-form writing is genuinely frequent.
**Recommendation: build the dashboard side first, and add Sveltia only after v2 is live** — the site works
perfectly well without it, and the decision can be revisited with real writing habits observed. Flagged for the
owner.

---

## 8. Moving from v1 to v2 without losing anything

v1 is live and earning. The move is a **page-group migration with the old files still serving until each group is
checked**:

1. **Tokens first.** Express v1's design as tokens (§5.1). Nothing is published yet.
2. **Sections second.** Rewrite the 15 section templates to read tokens and to match v1's design exactly, verified
   by rendering the same page in v1 and v2 and diffing the DOM and the computed styles.
3. **Then page groups, cheapest risk first:** the long tail (legal, specialist) → city and area pages → service
   pages → insights → **home last**. Each group: render → compare → check links, schema, titles → switch that group
   from files to the CMS → watch for a week.
4. **Never break a URL.** The 142 URLs are the asset. Every one keeps its path; the file and the CMS page cannot
   both answer, so the switch is per group and reversible.
5. **Rollback:** files stay in the repository until the whole site is on v2. Switching back is a deploy.

**The comparison step is not optional** and is not "looks the same to me": for each group, render both versions,
diff the visible DOM, and compare computed styles at three widths. Any difference that is not an intended
improvement is a bug.

---

## 9. Phases

| Phase | Deliverable | Gate before starting the next |
|---|---|---|
| **V2-1** | Theme tokens + Section templates converted to tokens | Changing a token re-skins every page; nothing published |
| **V2-2** | Section Library + Page Builder (drag, preview, publish) | The studio builds a page unaided; validator blocks bad pages |
| **V2-3** | Header/Footer builder + Media improvements + Pages/Menus completion | Site-wide chrome edited from the dashboard |
| **V2-4** | Crawler rules + sitemap/canonical/schema, then the auditor | Search Console clean; auditor reports real issues |
| **V2-5** | Migration: page group by group, home last | Each group compared and switched; rollback still possible |
| **V2-6** | The assistant + Sveltia bridge (only if wanted) | Suggestions recorded; nothing publishes itself |

Each phase ends with: Pest green, Larastan clean, Pint clean, every page rendered and link-checked
(`php tools/website/audit.php`), the preview checked in a browser, and **the owner's approval before the deploy.**

---

## 10. Risks

| Risk | Answer |
|---|---|
| Two editors drift apart (D-106) | Draw the split once; Sveltia last, and only if long-form is frequent (§7) |
| The free canvas makes pages that break on mobile | Canvas for campaign pages only; clearly labelled; excluded from re-skins |
| A token change breaks a page nobody re-checks | Pages are templates; the audit renders every page after every deploy |
| Hostinger's deploy leaves stale files behind | Uploaded files are never the source of truth after V2-5; the CMS is |
| No CI to build or test automatically | Tests run here before every push; the deploy is a Git push the owner triggers |
| Someone edits `public/*.html` by accident | v2 serves from the database; the old files are deleted at the end of V2-5 |
| The design drifts during the rewrite | The v2/v1 render diff per group is the gate (§8) |

---

## 11. Open questions for the owner

1. **Sveltia: now or never?** (§7) My recommendation is to defer it until the dashboard is finished.
2. **Theme pack:** which Preline/Tailwind theme should the import pipeline start from — or should v1's own design
   be the source, with no purchased theme at all?
3. **Long-form:** how often does the studio actually publish guides? It decides whether Sveltia earns its keep.
4. **Who may publish?** Today, anyone with `website.publish` can. Should pages need a second pair of eyes, the way
   quotations need approval above a discount?
