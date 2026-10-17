# Woodex OS: Design System ("Studio")

**Version:** 1.0 · **Date:** 15 October 2026
**Sources:** `woodex/assets/theme.css` (Studio tokens, this repo) · Preline UI v5 (`marketingwoodex-cloud/preline`)
· preline.co/blocks · TailAdmin Laravel v1.1.2
**Applies to:** public website (P6), admin (P0+), client pages (quote/invoice/portal), PDFs and emails.

---

## 1. Principles

1. **One token system everywhere.** Colours, fonts, radius and spacing come from tokens. No hex values in Blade
   files (CI greps for `#[0-9a-f]{6}` in `resources/views`).
2. **Calm, premium, architectural.** Plenty of whitespace, few colours, real photography, thin lines, pill
   buttons with the arrow.
3. **Blocks, not pages.** Every page is a stack of approved blocks. The same block looks right on every page.
4. **Fast by default.** System-font fallback, preloaded fonts, no layout shift, tiny JS.
5. **Accessible.** WCAG 2.2 AA contrast, visible focus (wood-tone outline), keyboard paths, reduced motion.
6. **Admin = clarity.** Dense but readable tables, obvious primary action per screen, status colours that mean
   the same thing on every screen.

---

## 2. Brand tokens

### 2.1 Core palette (from the Studio theme)

| Token | Value | Use |
|---|---|---|
| `ink` | `#111111` | Headings, primary text on light |
| `body` | `#525252` | Body text |
| `paper` | `#FFFFFF` | Page background |
| `soft` | `#F5F5F3` | Alternate section background |
| `beige` | `#FCF2E8` | Warm highlight, button hover |
| `navy` | `#0F1E36` | Brand colour, dark sections, primary buttons in admin |
| `line` | `#DEDEDB` | Borders, dividers |
| `muted` | `#6b6b6b` | Secondary text (5.1:1 on white; `#777` was 4.48:1 and failed AA at 14px) |
| `wood` | `#A87442` | Focus ring, accent details (from the Studio focus outline) |

### 2.2 Generated scales (used by Tailwind classes)

| Scale | 50 | 100 | 200 | 300 | 400 | 500 | 600 | 700 | 800 | 900 | 950 |
|---|---|---|---|---|---|---|---|---|---|---|---|
| **navy** (brand) | `#F2F5F9` | `#E3E9F1` | `#C5D1E2` | `#9DB0CB` | `#6E87AC` | `#4A6590` | `#344D75` | `#26395A` | `#1A2A45` | `#0F1E36` | `#0A1426` |
| **wood** (accent) | `#FBF6F0` | `#F5EADC` | `#EAD3B8` | `#DCB78E` | `#C99566` | `#A87442` | `#8E5F35` | `#734B2B` | `#5C3C24` | `#4A301D` | `#2A1A0F` |
| **sand** (warm neutral) | `#FDFAF6` | `#FCF2E8` | `#F6E6D5` | `#EBD5BE` | `#D9BFA3` | `#BFA285` | `#9C836A` | `#7A6653` | `#5A4C3F` | `#3D342C` | `#221D18` |
| **stone** (cool-warm gray) | `#FAFAF9` | `#F5F5F3` | `#EAEAE7` | `#DEDEDB` | `#BDBDB8` | `#8F8F8A` | `#777777` | `#525252` | `#3A3A38` | `#222221` | `#111111` |

Semantic status colours reuse TailAdmin's tested scales: **success** (`#12B76A` family), **warning** (`#F79009`),
**error** (`#F04438`), **info** (`#0BA5EC` "blue-light").

### 2.3 Typography

| Role | Font | Weights | Notes |
|---|---|---|---|
| Headings (site), page titles + KPI numbers (admin) | **Nohemi** (self-hosted OTF → WOFF2) | 400, 500, 600 | Letter-spacing −0.025em. **Licence check pending (P-09):** Nohemi is a Pangram Pangram font. Confirm the commercial licence before the website launch, or swap to the fallback heading font from the theme builder. |
| Body + UI | **Inter** (variable, OFL) | 100–900 | 15 px site body, 14 px admin body |
| Urdu content | **Noto Nastaliq Urdu** (OFL) | 400, 700 | Loaded only on pages/templates with `lang="ur"` |

Type scale (site): `h1 clamp(46px, 5.55vw, 96px)` · `h2 clamp(35px, 3.7vw, 62px)` · `h3 clamp(23px, 2vw, 32px)` ·
`h4 22px` · body 15/1.65 · small 13 · kicker 11 uppercase +0.12em.
Admin: TailAdmin scale (`text-theme-xs 12/18`, `text-theme-sm 14/20`, `text-theme-xl 20/30`, `title-sm 30/38`, `title-md 36/44`).

### 2.4 Shape, space, motion

| Token | Value |
|---|---|
| `radius` | 8 px (cards, images) · buttons **pill** (`9999px`) · inputs 8 px (admin 8 px) |
| `gutter` | `clamp(20px, 3.3vw, 64px)` |
| `section` | `clamp(72px, 8vw, 132px)` vertical padding |
| `max` | 1790 px container (site) · admin content max `1536px` |
| `shadow` | site: none/very soft (`0 14px 35px #00000008` on hover) · admin: TailAdmin `shadow-theme-xs/sm/md` |
| Motion | 200–300 ms ease; hover lift −2 px on buttons, −5 px on cards; image zoom 1.035 over 700 ms; **all disabled under `prefers-reduced-motion`** |

---

## 3. Implementation (Tailwind v4 + Preline v5)

### 3.1 Files

```
platform/resources/css/
├── tokens.css        ← Woodex tokens: @theme (scales, fonts, radius) + :root semantic variables
├── preline-woodex.css← Preline semantic variables mapped to Woodex ([data-theme="woodex"])
├── admin.css         ← @import tailwindcss · preline variants · tokens · TailAdmin utilities (menu-item…) · admin components
└── site.css          ← @import tailwindcss · tokens · Studio components (btn, eyebrow, section…) · blocks (P6)
```

### 3.2 Token layers

1. **Tailwind `@theme`** defines static scales (`--color-navy-900`, `--font-heading`, `--radius-ui`), so classes like
   `bg-navy-900`, `font-heading` and `rounded-ui` exist.
2. **Semantic CSS variables** (`--wx-primary`, `--wx-ink`, `--wx-surface`, `--wx-accent`…) point at the scales.
   `@theme inline` exposes them as utilities: `bg-primary`, `text-ink`, `bg-surface`, `border-line`.
3. **Preline semantic variables** (`--primary`, `--layer`, `--surface`, `--navbar`, `--sidebar`, `--card`,
   `--dropdown`…) are set from the Woodex variables inside `[data-theme="woodex"]`. Every Preline component and
   block picks up the brand without editing its markup.
4. **Theme builder (P6)** publishes `/theme/{hash}.css` that **only overrides layer-2/3 variables** at runtime:

```css
/* generated by the theme builder: no rebuild needed */
:root[data-theme="woodex"] {
  --wx-primary: #0F1E36; --wx-primary-hover: #1A2A45; --wx-on-primary: #FFFFFF;
  --wx-accent: #A87442; --wx-ink: #111111; --wx-body: #525252;
  --wx-surface: #FFFFFF; --wx-soft: #F5F5F3; --wx-beige: #FCF2E8; --wx-line: #DEDEDB;
  --wx-font-heading: "Nohemi", Arial, sans-serif; --wx-font-body: "Inter", Arial, sans-serif;
  --wx-radius: 8px; --wx-btn-radius: 9999px; --wx-container: 1790px;
}
```

**Rule:** Blade templates use only semantic classes (`bg-primary`, `text-ink`, `bg-soft`, `border-line`,
`font-heading`). Raw scale classes (`bg-navy-900`) are allowed in admin components that must not change with the
website theme.

### 3.3 Admin mapping (TailAdmin → Woodex)

| TailAdmin token | Woodex value | Effect |
|---|---|---|
| `--color-brand-*` | **navy** scale (brand-500 → navy-600 `#344D75` for links/active; brand-950 → navy-950) | Active menu, focus rings and primary buttons become Woodex navy |
| `--font-outfit` | replaced by `--font-sans: Inter` + `--font-heading: Nohemi` | Brand fonts in admin |
| `--color-gray-*` | TailAdmin gray kept (UI legibility) | Neutral UI |
| Sidebar | white / dark `gray-900`; active item `bg-navy-50 text-navy-700` (dark: `bg-white/5 text-white`) | |
| Primary button | `bg-navy-900 hover:bg-navy-800 text-white`, rounded-lg (admin uses 8 px, not pills) | |

### 3.4 JavaScript rules

- Admin: Livewire 4 provides Alpine. **Do not import Alpine.** Import `preline` once; re-init with
  `window.HSStaticMethods.autoInit()` on `livewire:navigated` and after Livewire morphs (`Livewire.hook('morphed', …)`).
- Public site: no Livewire. Bundle Alpine + small islands (forms, estimator, booking, chat loader). Preline plugins
  are imported à la carte (`preline/dist/collapse.js`, `dropdown.js`, `overlay.js`, `carousel.js`, `accordion.js`).
- Heavy libraries load lazily: ApexCharts (dashboard/reports), SortableJS (pipeline, builder), FullCalendar
  (visits calendar, social calendar).

---

## 4. Status colours (same meaning everywhere)

| Meaning | Colour | Lead stage | Quote | Invoice | Payment | Conversation |
|---|---|---|---|---|---|---|
| New / draft | **gray** | New | Draft | Draft | — | Closed |
| In progress | **info (blue-light)** | Contacted, Site visit | Sent, Viewed | Issued | Pending verification | Open |
| Waiting / attention | **warning (amber)** | Negotiation, On hold | Pending approval, Changes requested | Partially paid, Overdue (≤ 15 d) | — | Pending / snoozed |
| Positive | **success (green)** | Won | Accepted | Paid | Verified | Resolved |
| Negative | **error (red)** | Lost | Declined, Expired, Void | Overdue (> 15 d), Void | Rejected, Refunded | SLA breached |
| Brand highlight | **navy** | Hot lead (score ≥ 80) | Hot (≥ 3 views/24 h) | — | — | VIP |

Badges: `<x-admin.badge color="success">Paid</x-admin.badge>` with a dot. Text plus colour, never colour alone.

---

## 5. Admin component inventory

| Component | Blade | Based on |
|---|---|---|
| App shell (sidebar, header, backdrop) | `layouts/admin.blade.php`, `components/admin/sidebar.blade.php`, `header.blade.php` | TailAdmin layout + Woodex nav groups |
| Page header (title, breadcrumbs, primary action) | `<x-admin.page-header>` | TailAdmin breadcrumb |
| Card / section | `<x-admin.card>` | TailAdmin component-card |
| KPI stat | `<x-admin.stat>` | Preline **KPI Cards** |
| Badge | `<x-admin.badge>` | TailAdmin badge |
| Button | `<x-admin.button>` (variants: primary, secondary, ghost, danger; sizes sm/md) | TailAdmin button |
| Input / select / textarea / phone / money | `<x-admin.input>` … | TailAdmin form elements + Preline advanced select |
| Data table | `<x-admin.table>` + Livewire list trait (search, filters, sort, pagination, saved views) | Preline **User & Contact Tables**, **Order & Billing Tables** |
| Drawer (record details) | `<x-admin.drawer>` | Preline **Detail Drawers** (HSOverlay) |
| Modal / confirm | `<x-admin.modal>` | TailAdmin modal / Preline overlay |
| Toasts | `<x-admin.toasts>` (Livewire dispatch `toast`) | Preline toast |
| Empty state | `<x-admin.empty>` | Preline empty states |
| Skeleton | `<x-admin.skeleton>` | — |
| Timeline | `<x-admin.timeline>` | Preline **Timelines** / **Activity Feeds** |
| Kanban | `<x-admin.kanban>` + SortableJS | custom (TailAdmin task board style) |
| Description list | `<x-admin.dl>` | Preline **Description Lists** |
| Charts | `<x-admin.chart>` (ApexCharts, lazy) | Preline **Chart Compositions** + TailAdmin charts |
| Chat (inbox) | `livewire/admin/inbox/*` | Preline **AI chat interface** template patterns |
| Calendar | `<x-admin.calendar>` (FullCalendar, lazy) | TailAdmin calendar |
| Icons | `<x-icon name="…">` (Lucide subset, inline SVG, ISC licence) | — |

---

## 6. Admin screen → block map

| Screen | Layout and blocks |
|---|---|
| **Dashboard** | Row of 4–6 KPI cards → "Needs attention" list (Preline List Groups) + Funnel chart → Revenue/collections chart (Chart Compositions) + Upcoming visits (Timeline) → Recent activity (Activity Feed) |
| **My Day** | Three columns: Overdue · Today · New for me (task cards with quick actions: call, WhatsApp, done) |
| **Leads list** | Toolbar (search, filters, saved views, + Lead) → table (contact, phone actions, line, source, stage badge, owner avatar, next action with due colour, score) → row click opens **lead drawer** (summary, next action, timeline, quick log) |
| **Pipeline** | Kanban columns per stage (count + value), cards (name, service, area, value, owner, next-action chip, score dot), filter bar; drag → modals for visit/lost reason |
| **Contact 360** | Header (name, client badge, phones with call/WhatsApp, owner) → tabs: Overview (Description List + open leads) · Timeline · Quotes · Invoices · Projects · Conversations · Files |
| **Quote builder** | Two columns: left = sections + editable line table (sticky totals bar at bottom); right = client card, template, milestones, terms, approval/sending panel; top actions: Preview PDF · Send · More |
| **Quotes list / invoices / payments** | Order & Billing Tables with status chips, amount columns right-aligned, aging chips |
| **Inbox** | Three panes: conversation list (filters, channel icons, unread), thread (messages, window countdown, composer with templates/canned/AI), contact panel (lead, quotes, actions) |
| **Reports** | Filter bar (range, compare) → KPI cards → chart compositions → table with export |
| **Website → Page builder** | Left: block list (drag) · centre: iframe preview (device toggle) · right: field form (schema-driven) · top: draft/published status, preview, publish, versions |
| **Theme builder** | Left: token groups (colours, fonts, buttons, layout) with contrast checker · right: live preview of key blocks · publish / rollback |
| **Settings** | Vertical tabs (Business, Sales, Banks, Notifications, Booking, Bot, Website, Privacy) with form sections and "Save" per section |
| **Integrations** | Card grid (WhatsApp, Telegram, Meta, LinkedIn, AI, Realtime, Email, Turnstile, Google): status dot, last success/error, Configure, Test |

---

## 7. Website block library (Studio look on Preline blocks)

Each block = PHP schema class + Blade view(s) per variant + preview thumbnail. Free Preline blocks are used as
structural starting points. Studio styling comes from tokens and the Studio component classes. **Pro blocks
are used only if a licence is bought (P-04).**

| Block (key) | Variants | Preline family (starting point) | Used on |
|---|---|---|---|
| `header` (global) | transparent-over-hero, solid | Website Headers / Floating Headers | all |
| `hero` | photo-carousel (Studio home), split-form, minimal-title, image-wide | Hero Sections / Hero Sliders / Hero Forms | home, service, area, landing |
| `disciplines` | strip | Marquee / Clients | home |
| `intro-story` | image-left, two-column copy | Content Showcases | home, about |
| `services-grid` | cards-3, list | Feature Sections / Icon Blocks | home, services index, area |
| `split-feature` | with accordion, with list | Content Showcases / FAQ | home, service |
| `project-grid` | staggered-2, grid-3 | Gallery Grids | home, portfolio, area |
| `process` | 4-steps, timeline | Timelines / Icon Blocks | home, service |
| `stats` | inline, cards | Stats | home, about, area |
| `testimonials` | carousel, cards | Testimonials / Testimonial Cards | home, service, area |
| `logos` | grid, marquee | Clients / Marquee | home, about |
| `faq` | accordion-2col | FAQ | service, area, landing (+ FAQPage schema) |
| `cta-band` (global) | photo-background, navy | CTA | all |
| `contact` | form + details, map | Contact Pages | contact, landing |
| `estimate-teaser` | calculator preview | Pricing Cards | service, home |
| `gallery` | lightbox grid, slider | Gallery Grids / Sliders | project |
| `project-facts` | definition grid | Description Lists | project |
| `rich-text` | prose | Content Details | insight, legal |
| `insight-list` | cards-3, list | Feature Sections (cards) | insights index, service, area |
| `area-list` | chips, columns | List Groups | service, city pages |
| `team` | grid | Team | about |
| `form` | inline, card | Hero Forms | any |
| `footer` (global) | studio (big wordmark) | Footers | all |
| `spacer`, `divider` | — | — | any |

### Page-type compositions (defaults the editor starts from)

| Page type | Default block stack |
|---|---|
| **Home** | header(transparent) · hero(photo-carousel) · disciplines · intro-story · services-grid · split-feature(accordion) · project-grid · process · testimonials · insight-list · cta-band · footer |
| **Service** (65) | header · hero(split-form) · intro-story · split-feature(list: deliverables) · process · estimate-teaser · project-grid(filtered by service) · faq · area-list · insight-list(related) · cta-band · footer |
| **City/Area** (20) | header · hero(minimal-title + local photo) · services-grid(local) · stats(local projects) · project-grid(in area) · testimonials(in area) · faq(local) · contact(map) · cta-band · footer |
| **Insight** (48) | header(solid) · article header · rich-text (TOC) · related insight-list · cta-band · footer |
| **Project** (6) | header(solid) · hero(image-wide) · project-facts · rich-text(story) · gallery · testimonials(single) · project-grid(next) · cta-band · footer |
| **Contact / Book a visit / Estimator** | header(solid) · hero(minimal) · contact or booking/estimator island · faq · footer |
| **Landing (campaign)** | header(minimal) · hero(split-form) · logos · services-grid · testimonials · faq · form · footer(minimal) |

---

## 8. Client-facing documents

- **Quote/invoice web pages:** Studio look (paper background, navy header band, Nohemi headings), mobile-first,
  sticky "Accept" / "Upload payment proof" bar.
- **PDF (dompdf):** table-based A4 layout. Navy title bar, Inter body, embedded fonts (Inter + Nohemi or fallback).
  Section tables with zebra rows, right-aligned money, totals box, amount in words, milestones table, terms,
  bank box, two signature blocks, footer with address, phone, email and page numbers.
- **Emails:** single-column 600 px, logo, navy button, plain-text alternative.
- **WhatsApp templates:** short, polite, EN + UR, one clear button (View quotation / View invoice / Confirm visit).

---

## 9. Voice and microcopy

- Plain, warm, confident English. Short sentences. Verbs on buttons ("Send quotation", "Record payment").
- Staff UI explains consequences: "Sending creates revision R1 and notifies the client on WhatsApp."
- Client-facing text in the contact's language (EN/UR). Urdu reviewed by a native speaker before templates are
  submitted to Meta.
- Numbers: `Rs 4,50,000` or `Rs 450,000` (Settings), dates `15 Oct 2026`, times `3:45 pm`.

---

## 10. Licences and attribution

| Asset | Licence | Obligation |
|---|---|---|
| Preline UI (free components + free blocks) | MIT + Preline Fair Use | Keep the copyright notice (`docs/THIRD-PARTY.md` + footer credits page) |
| Preline Pro blocks | Commercial | Only with a purchased licence (P-04) |
| TailAdmin Laravel | MIT | Keep the notice in `platform/THIRD-PARTY.md` |
| Lucide icons | ISC | Keep the notice |
| Inter, Noto Nastaliq Urdu | SIL OFL 1.1 | Ship the licence file |
| Nohemi | Pangram Pangram licence | **Confirm commercial licence (P-09)** |
| Photography | Own photos of real Woodex projects (replace Unsplash stock before launch) | Model/property consent where needed |
