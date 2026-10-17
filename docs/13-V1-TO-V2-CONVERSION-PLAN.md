# 13 · v1 → v2 conversion plan

_How the live site moves from 142 hand-made files to the CMS, page group by page group, without losing a URL, a
ranking or an enquiry. Plan only — **nothing here is built yet**. The master plan it belongs to is docs/12._

The owner's rule, twice given: **the original site stays live until v2 is finished, checked and approved.** So the
conversion is not an event, it is a sequence of small, reversible moves.

---

## 1. Where we are

| | |
|---|---|
| **Live** | 142 original HTML files in `platform/public/`, served verbatim; forms bridged into the CRM (docs/11 §11.0.1) |
| **In the CMS** | The same 143 pages, imported, editable, with 15 section templates — **not served** |
| **Not built yet** | Design tokens, section library, drag-and-drop builder (docs/12 §5.1–5.3) |

The conversion cannot begin until **V2-1 and V2-2 are done** (tokens + section library + builder). Converting
before that would mean copying v1's hard-coded values into the CMS and doing the token work twice.

---

## 2. The four rules

1. **No URL ever changes.** The 142 paths are the asset: they carry the rankings, the backlinks and the business
   cards. Every page keeps its path exactly. Nothing 301s to anywhere.
2. **The site never goes dark.** Files keep serving every URL until that URL's group has been converted and
   checked. There is no moment when a page is "being migrated".
3. **Same design, proven — not asserted.** A group switches only when v1 and v2 render the same page to the same
   DOM and the same computed styles at three widths (§5).
4. **Every move is reversible** until the last group is done. Rollback is a deploy (§7).

---

## 3. What actually decides which version serves a URL

```
Browser → Apache (.htaccess / DirectoryIndex)
            ├─ a file exists at that path?                 → serve the v1 file
            └─ no                                          → front_controller.php → Laravel
                                                                └─ SiteRouter: is there a published page? → serve the CMS page
                                                                └─ no → the 404 page (or a redirect rule)
```

**This is the switch.** While a group's files are still on the server, the files win. Delete the group's files, and
the same URL is answered by the CMS page that was prepared and checked beforehand. That is the whole mechanism —
no flag to flip, no routing table to maintain, and a rollback that is just restoring the files.

Consequence to respect: Hostinger's deploy **replaces files but does not delete removed ones**. Deleting a group's
files therefore needs its own deploy step (or a small `artisan` command run on the server), not just a push.

---

## 4. The groups, in order

| # | Group | Pages | Why this order |
|---|---|---|---|
| **1** | Legal & utility: `/privacy/`, `/contact/`, `/estimator/`, `/book-a-visit/` | 4 | Lowest traffic, no rankings to lose, and they exercise the form, the estimator and the booking paths end to end. Any surprise in forms or tokens shows up here, where it costs least. |
| **2** | Insights (48 guides + `/insights/`) | 49 | Long-form, template-driven, and the group where Sveltia would live. Converting them proves the section library handles long content and that internal links survive. |
| **3** | Cities (12) + Lahore areas (8) | 20 | Near-identical pages: the fastest way to prove that one template with different data renders correctly everywhere, and the group most improved by the SEO layer. |
| **4** | Projects (6 + index) | 7 | Image-heavy — proves media conversions, `srcset` and alt text at scale. |
| **5** | Rooms (11) and property sizes (7) | 18 | High-intent money pages, but individually smaller than the service pages. |
| **6** | Services and specialists (41) | 41 | The commercial core: disciplines, fit-out, renovation, healthcare, retail, hospitality. Converted only after five groups have proven the pipeline. |
| **7** | `/about/`, `/services/` | 2 | Hub pages whose links must point at pages already converted. |
| **8** | **Home** | 1 | Last. It is the most-linked page and the one whose layout is least like a template. |

Total: **142**.

---

## 5. The gate: a group is converted when all of this is true

Checked per page, by a tool (`php tools/website/compare.php`, to be written in V2-1), not by eye:

**Structure** — same URL, same `<h1>`, same heading order, same number of sections, same internal links (all
resolving), same images (same `src`/`srcset`, alt text present, width/height set).

**Styles** — computed styles compared at 375 / 768 / 1440 px for the same selectors: colours, font sizes, weights,
line heights, spacing and radii. Any difference is listed; the studio decides for each whether it is an intended
improvement or a bug.

**SEO** — same title and description **or** a deliberately better one (recorded), canonical correct, JSON-LD valid
and of the same type, `robots` unchanged, `og` image set.

**Forms and tracking** — the enquiry form posts and creates a lead; the WhatsApp tap is counted; GA4/GTM/Clarity
fire the same events as v1.

**Performance** — no regression on Largest Contentful Paint and Cumulative Layout Shift; page weight not worse.

The output is a per-group report: *142/142 green*, or the list of differences with a decision recorded against
each. **A group with an unexplained difference does not switch.**

---

## 6. Sequence for one group

1. **Prepare** — the group's CMS pages are already imported; bring them up to the new sections and tokens.
2. **Compare** — run the comparison against the live files; fix every difference that is not an intended
   improvement.
3. **Check the links** — `php tools/website/audit.php`; zero broken links, zero redirect chains.
4. **Approve** — the owner sees the group on the preview domain and says go. **This is the owner's gate.**
5. **Switch** — delete the group's files, deploy, watch: 404 log, Search Console, analytics, enquiries.
6. **Settle** — one week minimum before the next group. If anything moves, roll back (§7) before continuing.

---

## 7. Rollback

Until group 8 is done, the files for every group remain in git. Rolling back a group is: restore its files, push,
deploy — a few minutes. The CMS pages stay as they are, so nothing learned is lost; the group is simply served by
files again until the problem is understood.

After group 8 (home) has settled, the remaining v1 files are deleted. From that point the CMS is the only source of
truth and rollback means fixing forwards — which is why home goes last.

---

## 8. What has to exist before group 1

- [ ] Design tokens + theme builder, and all 15 section templates reading tokens only (V2-1)
- [ ] Section library and page builder with drag, preview and publish (V2-2)
- [ ] `tools/website/compare.php` — the v1/v2 render diff (§5)
- [ ] The 404 log and redirect screen live in the dashboard, so a switch can be watched
- [ ] Analytics verified on the preview domain (GA4, GTM, Clarity)
- [ ] A named owner for each group's approval

---

## 9. Risks

| Risk | Answer |
|---|---|
| Hostinger does not delete removed files | Deleting a group is an explicit deploy step, verified by fetching a known URL from the group |
| A template silently changes 40 pages | The comparison runs on **every page** in the group, not a sample |
| Search Console noise after a switch | One group at a time with a week between, so a change can be attributed |
| The free-canvas pages cannot be converted | Campaign pages are excluded from conversion and rebuilt by hand, if they still matter |
| v1 files are edited by someone during the migration | Once group 1 starts, the files are frozen; edits happen in the CMS |
