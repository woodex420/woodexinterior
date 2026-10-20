# 20 — Content screens against the files

21 Oct 2026. The owner asked for the same question that retired the header/footer fields and rewired the Media
alt text, one screen further along: **does this screen change the site?** Three answers were already yes — Pages,
Media and Settings — and one was no (the header/footer cards). This is the Content band.

**Headline: two screens change nothing a visitor can see (Blog, Portfolio), one is an empty library with no
reader (Content), and one is a sitemap that can advertise a 404. Forms and Estimator are honest and stay.**

## 1. What each screen writes, and what reads it

| Screen | URL | Writes | Read by | Reaches the site? |
|---|---|---|---|---|
| **Blog & insights** | `/website/blog` (in the sidebar) | `pages` + `page_blocks` rows through `cms_save`, `page_new`, `cms_delete` | nothing — the renderer was deleted in `570eed7` | **No.** A new post is a row with no file: the URL is a 404. Editing a row changes nothing on the page. |
| **Portfolio** | `/website/portfolio` (in the sidebar) | the same `cms_*` / `page_new` rows, type `project` | nothing | **No**, identical. |
| **Forms** | `/website/forms` (in the sidebar) | **nothing** — `cms_forms` → `formsList()` counts `leads` by `source_detail` | the files' own forms post to `/api/public/leads` and write those leads | **Yes, as a report.** It shows the real submissions, so it can be trusted. |
| **Estimator** | `/website/estimator` (in the sidebar) | `estimator_rates` **and** `public/assets/js/estimator-rates.js` (`RateBook`, line 38) | the `/estimator/` file loads that script | **Yes.** Saving changes the published estimator. |
| **Content library** | `/website/content` (route live, out of the nav since 20 Oct) | `content_entries` (kinds: `faq`, `testimonial`, `logo`) | the retired FAQ/Reviews/Clients screens and the old renderer; `ContentLibrary` is called only from `StudioApi` | **No.** The table holds **0 rows** and nothing in the file world reads it. |

## 2. The evidence

**Blog and Portfolio.** `StudioContent` (the class behind every `cms_*` action) contains **no** `file_put_contents`,
`BuilderFile` or `public_path` call — it writes rows and blocks only. `page_new` (`StudioApi::pageNew`) creates a
`Page` row, not a file. So the two ways of adding content there both end in a row.

Proved end to end: a published row was created exactly as the screen would create one
(`/insights/zz-new-post/`, type `insight`, status published). Then:

```
GET /sitemap.xml            -> listed  /insights/zz-new-post/   (143 <loc>s instead of 142)
GET /insights/zz-new-post/  -> 404
```

The row was deleted straight after; the database is back to 142 pages and the sitemap to 142 `<loc>`s. Nothing
about this is a test-only path: it is what the "New post" button does.

**Content library.** `ContentEntry::KINDS` is `faq`, `testimonial`, `logo`; the table is empty. `ContentLibrary`
(the only reader) is referenced from `StudioApi` alone, and no builder, import or file-writing path touches it.
The screen was taken out of the sidebar on 20 Oct because "its records are the same pages and blocks the screens
below edit" — but the route still answers and the add forms still save rows.

**Forms.** `admin-forms.js` makes exactly one call, `cms_forms`; the handler builds a read-only list from `leads`.
There is nothing in that screen that claims to edit the site.

**Estimator.** The screen's save goes through `RateBook`, which writes the JSON the `/estimator/` page reads
(`public/assets/js/estimator-rates.js`). This is the one Content screen that already works the new way.

## 3. The sitemap contradiction

`SitemapController` deliberately keeps listing **published rows that have no file**, with the comment *"A row that
has no file is still listed: `SiteRouter` answers it, so it is a real URL."* That was true before step 8. Since the
renderer was retired, `SiteRouter` answers nothing for a file-less row — it returns `null` and Laravel renders the
404. The controller's own class comment still promises *"Everything listed answers 200 — a sitemap that points at
a 404 or a redirect is worse for search than a short sitemap."*

Today there happen to be no file-less rows (142 rows, 142 files), so the live sitemap is clean. The Blog and
Portfolio screens are the way such a row appears, and the test above shows what happens next: **Google is handed a
404.** Fixing this is a two-line change in the same controller: drop the row branch, list the files.

## 4. What the retirement would not touch

- The **48 insight rows and 6 project rows** stay as records. Their URLs are files and keep answering.
- **Forms** and **Estimator** stay exactly as they are.
- No page file changes, so no URL, title or design changes.

## 5. Recommendation

1. **Retire Blog and Portfolio** the way Service, City, FAQs, Testimonials and Client logos were retired: remove
   the two sidebar items, switch the screens off in `WebsiteStudioController::SCREENS`, and leave `cms_*` /
   `page_new` answering nothing (or delete those actions once the JS no longer references them). Posts and studies
   are files, edited in the page builder like every other page.
2. **Retire the Content library**: the route and the Livewire component, since it holds nothing and nothing reads
   it. The logos are now in **Media** (step 14), which is where the owner can actually edit them.
3. **Fix the sitemap** so it lists only the files: no screen should be able to hand Google a URL that 404s.

Alternative if the owner would rather keep the two screens: they have to be rewired to write page files from their
blocks — the same size of work as porting the builder again, for a workflow the builder already provides.

---

## Update — 21 Oct 2026: the owner kept the screens and they now write the page files

The owner chose the alternative in §5, not the retirement: **Blog and Portfolio stay, and a save writes the page.**
Retiring them was rejected outright.

### What was built

- **`ArticleFile`** (`app/Domain/Website/Builder/ArticleFile.php`) renders a post into
  `public/insights/<slug>/index.html` and a study into `public/projects/<slug>/index.html`.
  It takes an existing page of the same kind as the **shell** — an imported article, or the first file in the
  folder — and replaces only the head's page metadata (title, description, `og:`/`twitter:` title, description
  and image, canonical) and `<main>`. The header, footer, stylesheet, scripts and every other page's markup are
  untouched; the body is written with the design's own classes (`dx-hero`, `dx-body`, `dx-toc`,
  `in-faq-list`, `dx-quote`, `dx-related`, `in-cta-grid`, `wx-tbl`). No new design is introduced.
- **The words come from the editor**: the `studio` JSON the editor saves when it exists, otherwise the row's
  `hero` / `intro` / `text` blocks — the same reading `cms_get` uses, so an imported article opens and publishes
  with its own text.
- **`cms_save`** writes the file when the page is published, so editing a live article changes the live page.
- **`cms_published`** writes it, and **`cms_status`** decides by the switch: *Live* writes the file, *draft*
  moves it to `storage/app/studio-retired/<folder>/<slug>-<time>.html`. Nothing is deleted: the file is kept,
  so a page can be brought back and nothing the design shipped is lost.
- **A rename retires the old address** before saving the new one — an article moved from `/insights/old/` to
  `/insights/new/` stops answering at the old URL.
- **`cms_delete`** retires the file and then removes the row (still refusing a live page, as before).

### The sitemap contradiction, closed

`SitemapController` no longer lists CMS rows at all — only the files — because a published row now has a file and
a row without one answers 404. The injected `<loc>`s are also **canonical** (`https://woodex.com.pk/…`) on every
host: built from the requesting host, a fetch through the admin domain put `admin.woodex.com.pk` in the sitemap,
and an environment that reports no host produced relative URLs, which are not valid in a sitemap.

### Verified in the sandbox (not just asserted)

- Published a new article through the same service the screen calls: the file appeared at
  `public/insights/<slug>/index.html` (71 KB), the URL answered **200**, `/sitemap.xml` listed it **once**
  (142 files + 1), and the browser rendered the v1 design — one `<h1>`, the TOC, the table, the quote, the FAQ,
  three related cards with images and the contact band; no broken images, no console errors
  (`/tmp/woodex-run/shots/published-article.png`).
- Took it off the site: the file left `public/` for `storage/app/studio-retired/`, the URL answered **404**, the
  row deleted, and the sitemap returned to **142**.
- Tests: `ArticleFileTest` — a draft writes nothing; publishing writes the file with the design's chrome, the
  new title/description/canonical and the article as written; a published save writes; draft retires and keeps a
  copy; a rename stops the old address; a study writes under `/projects/`; a published row without a file is not
  in the sitemap. `CmsPageTest` now holds the files-only sitemap and the canonical URLs.

### Still open (honest list)

- **`page_new`** (the row-creating action) still creates a **draft** row and writes no file — a draft has no URL.
  The file is written when that page is published (`cms_published`) for the two types these screens edit; the
  builder's own `page_new` already writes its file immediately.
- **Imported articles re-render from their blocks** when edited — same design and same shell, but a rebuild from
  the row is not byte-identical to the v1 markup that shipped. The v1 file is kept in `studio-retired/` when the
  page is taken off the site.
- **The Content library is untouched** (0 rows, no reader) and Forms/Estimator are unchanged.
