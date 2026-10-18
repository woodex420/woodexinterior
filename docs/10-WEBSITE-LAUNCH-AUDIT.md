# 10 · Website launch audit (89 pages) and the plan that follows

**17 Oct 2026.** Every published page was rendered through the application itself — not guessed at, not sampled —
and the result was recorded page by page. This document is that record: what is sound, what was wrong, what has
been fixed, and what is left to a human being.

Numbers in the *before* column are the state of the site as it went live on 16 Oct. Numbers in the *after* column
are the state of this commit.

---

## 1. How the audit was run

A script renders each published page through the app's own HTTP kernel and reads the HTML the way a crawler
would:

```
89 pages × (status, title, description, h1/h2 count, visible word count,
            images + missing alt + missing width/height, internal links,
            JSON-LD types, form present, call to action present, seo.last_reviewed)
→ then every one of the 704 internal links is fetched and its status recorded
```

It lives at `tools/website/audit.php` — run it from the repository root: `php tools/website/audit.php`
(point `AUDIT_APP_ROOT` at the app directory if your checkout is not laid out the usual way). It prints a summary
and exits non-zero if any internal link is broken, so it can sit in CI. Run it again after
every content batch; it is the cheapest way to know the site is still whole.

---

## 2. What is sound

Every one of the 89 pages:

| Check | Result |
|---|---|
| Answers HTTP 200 | **89 / 89** |
| Broken internal links | **0** (of 704 links followed) |
| Has exactly one `<h1>` | **89 / 89** |
| Title ≤ 65 characters | **89 / 89** (longest 65) |
| Meta description 120–165 characters | **89 / 89** — no duplicates anywhere |
| Carries JSON-LD (`LocalBusiness` · `WebSite` · `WebPage` · `BreadcrumbList`, `FAQPage` where there are questions) | **89 / 89** |
| Every image has alt text and explicit width/height (so the page does not jump while loading) | **89 / 89** |
| `seo.last_reviewed` set | **89 / 89** (16 Oct 2026) |
| Thin content (under 300 words) | **0** — shortest page 489 words, median 929, longest 1 738 |

Word counts by kind of page before the fixes below (min / median / max): **service** 679 / 954 / 1 354 ·
**insight** 711 / 796 / 945 · **city** 717 / 744 / 925 · **area** 579 / 626 / 686 · **project** 538 / 567 / 572 ·
**listing** 453 / 1 016 / 1 663.

The mechanics are done. What follows is about whether the pages *work* on a person who arrives from Google.

---

## 3. What was wrong, and what is fixed

### 3.1 The site had no navigation (27 of 89 pages unreachable)

`navigation_items` was empty — no menu had been created in the dashboard — and the header and the footer showed
nothing at all. A page nobody links to is a page Google treats as an afterthought, and 27 pages had exactly that
status: the home page, `/about/`, `/projects/`, `/insights/`, `/privacy/`, all **12 city pages** and all **8 Lahore
area pages**. The sitemap was the only thing pointing at them.

**Fixed.** `Menus::defaults()` now supplies a real header and footer until the studio fills in Website → Menus
(services, cost guides, projects, about, contact — plus the estimator and Book a visit in the footer), and
`Menus::cityLinks()` puts **Where we work** in the footer, listing the twelve cities from the CMS. Whatever the
owner enters in the dashboard replaces it instantly, so nothing here is hard-coded against their judgement.

> Orphans: **27 → 0**. Average internal links per page: **2.2 → 22**.

### 3.2 Forty pages ended on a full stop

All 40 were cost guides. A guide is the page that brings a stranger in from Google; it answers their question and
then offered nothing — no form, no link, no next step.

**Fixed** (`..._130000_add_call_to_action_to_pages.php`): every published page that had no enquiry form now ends
with one, with a heading written for its kind of page (*"Ask us about your own numbers"* on a cost guide, *"Start a
project like this"* on a study, *"Design in your city"* on a city page). It is an ordinary block the studio can
move, rewrite or delete in Website → Pages.

> Pages without a call to action: **40 → 0**.

### 3.3 The eight Lahore area pages had no way in

The pages a person in DHA or Gulberg actually searches for were not linked from the Lahore page above them.

**Fixed** (`..._130100_link_city_pages_to_their_areas.php`): each city page ends with **"Areas of X we work in"**,
listing its areas. Lahore now points at all eight; the other eleven cities have no area pages yet, so nothing was
invented for them.

---

## 4. What is still open

### 4.1 Five pages carry no photograph

`/services/`, `/projects/`, `/book-a-visit/`, `/estimator/`, `/privacy/`. The library holds 74 imported images
with **no names and no alt text**, so picking one at random would be worse than an empty space — a photograph on
a design studio's website is a claim about their work.

**This is an owner task:** upload real photographs (six project studies, and one per service) and they can be
placed in minutes from Website → Pages. `/privacy/` and `/estimator/` are meant to be plain; they need nothing.

### 4.2 The content is imported, not yet written

The pages are complete and correct; they are not yet *the studio's own voice*. This is the humanizing queue from
docs/09 §5, in the order that pays:

| # | Pages | Why first |
|---|---|---|
| 1 | `/` (home) | The page every campaign and every business card points at |
| 2 | 6 service pages (`/interior-design/`, `/fit-out/`, `/renovation/`, `/architecture/`, `/3d-visualization/`, `/furniture/`) | They are what is sold, and what is bid on |
| 3 | `/lahore/` and `/lahore/dha/`, `/lahore/gulberg/` | The money pages for a Lahore studio |
| 4 | The 5 cost guides people land on most (`/insights/interior-design-cost-pakistan/`, `.../house-renovation-cost-lahore-2026/`, `.../office-fit-out-cost-pakistan-2026/`, `.../modular-kitchen-price-pakistan-2026/`, `.../interior-designer-cost-lahore-2026/`) | Long-tail traffic, and the first thing a price shopper reads |
| 5 | `/about/` | The page that decides between two shortlisted studios |

One page at a time: rewrite the hero, the closing and the middle sections in your own words, add two photographs,
publish. Run `tools/website/audit.php` after each batch.

### 4.3 One manual step left on the live server

**woodex.com.pk → /admin → System → Database update.** Until it runs, the 89 pages are drafts on that server and
`/` answers 404 *"This page has moved"*. It also creates the conversation tables for the inbox. Everything else
in this and the previous commit is already live there.

---

## 5. Plan: the order of the next work

| Order | Work | Kind | Notes |
|---|---|---|---|
| 1 | Database update on woodex.com.pk (**owner click**) | Owner | Publishes the 89 pages; nothing else is waiting on it |
| 2 | Fill Website → Menus (or keep the built-ins) | Owner, 15 min | The defaults are good; this is where the studio's own wording goes |
| 3 | Upload real project photographs, place them on `/projects/` and the 6 service pages | Owner | Closes §4.1; also unblocks the case studies |
| 4 | Humanize the pages in §4.2, in that order | Owner + agent | One batch, one commit, one audit run |
| 5 | Connect GA4, GTM, Search Console, Clarity (docs/09 §5 tracking batch) | Agent, small | Needs the IDs from the owner (P-15) |
| 6 | **Resume P4 part 2**: the website chat widget + public chat API (CNV-001/008) | Agent | The next item in the phase plan; the inbox is waiting for it |
| 7 | P4 continued: templates and broadcasts (WAP-005/007), usage meter (WAP-008), canned replies (CNV-004), AI bot (BOT-001…010), sequences (AUT-001/002/005/006), broadcasting with a polling fallback | Agent | In that order |

Items 1–3 are the owner's. Item 4 we do together. Items 5–7 are code and can start at once.

---

## 6. Verification of this commit

- Pest **413 passed**, 2 878 assertions (two new navigation tests: the built-in links are real URLs, and the footer
  lists the cities) · Larastan level 6 clean · Pint clean on 450 files.
- Audit re-run: **89 pages, 0 broken links, 0 orphans, 0 pages without a call to action.**
- Preview checked end to end before committing: home, six service pages, `/services/`, `/insights/` and two guides,
  `/projects/`, `/lahore/`, `/lahore/dha/`, `/contact/`, `/estimator/`, `/book-a-visit/`, `/sitemap.xml`,
  `/robots.txt` — all 200.
