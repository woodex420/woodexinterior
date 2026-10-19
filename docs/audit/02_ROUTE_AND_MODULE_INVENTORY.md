# 02 · Route and module inventory

Read-only inventory taken from `php artisan route:list --json` (158 routes), the sidebar definition
`app/Support/AdminNavigation.php`, and `routes/{web,admin,api,console,install}.php`.
Status: **Verified** unless marked otherwise.

---

## 1. Route totals

| Measure | Value |
|---|---:|
| Total routes | **158** |
| Named / unnamed | 150 / 8 |
| `GET\|HEAD` | 123 |
| `POST` | 34 |
| `PUT` | 1 |
| `DELETE` | 1 |
| Admin-prefixed routes | **107** |
| API routes (`api/*`) | 10 |
| Webhooks | 4 |
| Public portal routes (`q/`, `i/`, `p/`, `portal`) | 13 |
| Website utility routes (`robots.txt`, `sitemap.xml`, `media/*`, theme CSS, chat widget) | 7 |

Route groups by first path segment (top 12):

```
admin/website   16      api          10      admin/quotes   4
admin/user      10      webhooks      4      admin/sales    4
admin/system     6      portal        4      admin/accounts 4
admin/google     7      q / i / p     4 each
```

### 1.1 Permission enforcement (Verified, route middleware)

Of the 107 admin routes, **53 carry a route-level permission** (`Illuminate\Auth\Middleware\Authorize:…`).
The other 54 are:

- authentication/2FA/password endpoints (Fortify: login, logout, forgot/reset, two-factor, confirm) — **expected**;
- Google OAuth endpoints — **expected** (guest start, auth finish);
- admin screens whose component calls `authorize()` / a policy in `mount()` — e.g. `admin/contacts`
  (`ContactsIndex::mount()` → `$this->authorize('viewAny', Contact::class)`), `admin/leads`,
  `admin/quotes/{quote}`, `admin/projects/{project}`.

There are **8 policies** (Lead, Contact, Company, Quote, Invoice, Payment, Project, Conversation) and
**144 `authorize()` calls** across Livewire components. Route-level absence is therefore not by
itself a defect, but the 54 must each be checked in Phase 1 (finding S-01). Three screens whose
component guard was spot-checked in this phase: Contacts ✓, Tasks ✓, Leads (policy use inside) ✓.

---

## 2. Sidebar → route → component → test

`AdminNavigation::groups()` defines **53 items in 10 groups**. 48 are linked; **5 are deliberate
"planned" items with no route**; one route is listed twice.

| Group | Item | Route | URI | Component | Permission | Phase | Test evidence |
|---|---|---|---|---|---|---|---|
| Overview | Dashboard | admin.dashboard | admin | page controller | dashboard.view | — | AdminAccessTest |
| Overview | Approvals | admin.approvals | admin/approvals | Approvals | quotes.approve | — | P10SidebarHubsTest |
| Overview | Reports | admin.reports.index | admin/reports | ReportsHub | reports.view | — | — |
| CRM | Leads | admin.leads.index | admin/leads | LeadsHub | leads.view | — | AdminAccessTest |
| CRM | Contacts | admin.contacts.index | admin/contacts | ContactsHub | contacts.view | — | CompaniesTest |
| CRM | Tasks & Visits | admin.tasks.index | admin/tasks | TasksHub | leads.view | — | — |
| Sales | Estimations | admin.estimations.index | admin/estimations | EstimationsIndex | estimates.view | — | EstimatesTest |
| Sales | Quotations | admin.quotes.index | admin/quotes | QuotationsHub | quotes.view | — | QuoteBuilderTest |
| Sales | Invoices | admin.invoices.index | admin/invoices | InvoicesHub | invoices.view | — | AdvanceInvoiceTest |
| Sales | Payments | admin.payments.index | admin/payments | PaymentsHub | invoices.view | — | PaymentsTest |
| Sales | Documents | admin.sales.documents | admin/sales/documents | DocumentsHub | quotes.view | — | DocumentLibraryTest |
| Accounts | Chart of Accounts | admin.accounts.chart | admin/accounts/chart | ChartOfAccounts | accounts.view | — | AccountsLedgerTest |
| Accounts | Journals | admin.accounts.journals | admin/accounts/journals | JournalsIndex | accounts.view | — | AccountsLedgerTest |
| Accounts | Periods | admin.accounts.periods | admin/accounts/periods | PeriodsIndex | accounts.view | — | AccountsLedgerTest |
| Accounts | Payment Methods | admin.accounts.methods | admin/accounts/methods | MethodAccounts | accounts.view | — | GlSalesPostingTest |
| Projects | Projects | admin.projects.index | admin/projects | ProjectsIndex | projects.view | — | ProjectsTest |
| Projects | Board | admin.projects.board | admin/projects/board | ProjectBoard | projects.view | — | ProjectsTest |
| Projects | **Client Portal** | — | — | — | — | **P4b** | (portal routes exist) |
| Projects | **Handovers** | — | — | — | — | **P4b** | (portal handover route exists) |
| Inbox | Conversations | admin.inbox | admin/inbox | Inbox | inbox.view | — | CannedRepliesTest |
| Inbox | **Chat Bot** | — | — | — | — | **P4** | BotEngineTest |
| Automation | Automations | admin.automation.index | admin/automation | AutomationsIndex | automation.manage | — | AutomationsDashboardTest |
| Automation | Sequences | admin.automation.sequences | admin/automation/sequences | SequencesIndex | automation.manage | — | SequenceEngineTest |
| Automation | WhatsApp Templates | admin.whatsapp.templates | admin/integrations/whatsapp-templates | WhatsAppTemplates | integrations.manage | — | WhatsAppTemplateManagerTest |
| Automation | **Alert Rules** | — | — | — | — | **P5** | AlertRulesTest (System entry) |
| Marketing | Social Planner | admin.marketing.social | admin/marketing/social | SocialPlannerIndex | social.manage | — | SocialPlannerTest |
| Marketing | **Lead Ads** | — | — | — | — | **P7** | — |
| Website | All pages | admin.website.pages | admin/website/pages | PagesIndex | website.edit | — | CampaignCanvasTest |
| Website | Page builder | admin.website.pages.create | admin/website/pages/new | PageEditor | website.edit | — | BuilderHistoryAuditTest |
| Website | Section library | admin.website.sections | admin/website/sections | SectionLibraryIndex | website.edit | — | WebsiteAdminParityTest |
| Website | Header & footer | admin.website.menus | admin/website/menus | MenusIndex | website.edit | — | MenusTreeTest |
| Website | Hero slides | admin.website.heroes | admin/website/heroes | HeroSlidesIndex | website.edit | — | PageBuilderLiveTest |
| Website | Redirects | admin.website.redirects | admin/website/redirects | RedirectsIndex | website.edit | — | WebsiteAdminTest |
| Website | Media | admin.website.media | admin/website/media | MediaLibrary | website.edit | — | WebsiteAdminTest |
| Website | Content library | admin.website.content | admin/website/content | ContentLibraryIndex | website.edit | — | ContentLibraryTest |
| Website | Team | admin.website.team | admin/website/team | TeamMembersIndex | website.edit | — | TeamSectionTest |
| Website | SEO | admin.website.seo.overview | admin/website/seo-overview | SeoOverviewIndex | website.edit | — | WebsiteAdminParityTest |
| Website | SEO agent | admin.website.seo | admin/website/seo | SeoAgentIndex | website.edit | — | SeoAgentTest |
| Website | Speed | admin.website.speed | admin/website/speed | SpeedIndex | website.edit | — | SpeedDashboardTest |
| Website | Site health | admin.website.health | admin/website/health | SiteHealthIndex | website.edit | — | WebsiteAdminParityTest |
| Website | Theme | admin.website.theme | admin/website/theme | ThemeBuilder | theme.manage | — | ThemeTokensTest |
| Website | Website Settings | admin.website.settings | admin/website/settings | WebsiteSettings | settings.manage | — | WebChatWidgetTest |
| System | Settings | admin.settings | admin/settings | SettingsPage | settings.manage | — | BotEngineTest |
| System | Users & Roles | admin.users.index | admin/users | UsersIndex | users.manage | — | UsersAndSettingsTest |
| System | Lead Assignment | admin.assignment-rules | admin/settings/lead-assignment | AssignmentRules | settings.manage | — | SettingsAndRulesTest |
| System | Canned replies | admin.canned-replies | admin/settings/canned-replies | CannedRepliesIndex | settings.manage | — | CannedRepliesTest |
| System | Integrations | admin.integrations | admin/integrations | Integrations | integrations.manage | — | ChangeRequestsBoardTest |
| System | Data Import | admin.data-import | admin/data-import | DataImport | system.import | — | SystemScreensTest |
| System | Recycle Bin | admin.recycle-bin | admin/recycle-bin | RecycleBin | leads.delete | — | QuoteScreensTest |
| System | Health | admin.system.health | admin/system/health | Health | system.health | — | DatabaseUpdatesTest |
| System | Audit log | admin.system.audit | admin/system/audit | AuditLog | audit.view | — | AuditLogTest |
| System | Change Requests | admin.system.change-requests | admin/system/change-requests | ChangeRequests | system.change_requests | — | ChangeRequestsBoardTest |
| System | **Alert Rules** | admin.system.alert-rules | admin/system/alert-rules | AlertRulesIndex | settings.manage | — | AlertRulesTest |

### 2.1 Findings from the map

| ID | Finding | Evidence | Severity |
|---|---|---|---|
| N-01 | **Alert Rules is duplicated** — a disabled `P5` item in Automation and the live screen in System | two `self::item('Alert Rules', …)` entries; `admin.system.alert-rules` exists | P2 |
| N-02 | **P4b is undefined in the source and docs** (no file in the original 36-document set mentions it). The brief also asks what P4/P4B/P5/P7 mean | P4 = Conversations, P5 = Telegram command centre, P7 = Social in `docs/01-MASTER-PLAN.md` §integration table; P4b has no definition anywhere | P2 (needs owner) |
| N-03 | **"Client Portal" and "Handovers" are shown as planned (P4b)** although the client portal is built: `portal` routes, `p/{project}/handover`, `ClientPortalTest`, `ProjectPortalController`, `portal_access_codes` table | route list + tests | P2 (nav out of date) |
| N-04 | **Phase codes are shown raw** — planned items display `P4`, `P5`, `P7`, `P4b` with no legend anywhere in the UI or docs | `AdminNavigation.php`; `grep P4b docs/` → 0 hits at audit time; convention added in A-05 (`docs/07`) | P3 (wording) |
| N-05 | The brief lists **System → Alert Rules** and **Automation → Alert Rules**; the canonical screen is `admin.system.alert-rules`. Recommendation: keep System, delete the Automation duplicate | as above | P2 |
| Q-07 | **Reports and Tasks & Visits have no component behaviour test.** `P10SidebarHubsTest` renders both routes and `ReportsKitTest` covers report maths, but neither component's actions are exercised | `P10SidebarHubsTest`, `ReportsKitTest` | P2 (coverage gap) |

Navigation behaviour (grouped/collapsible, active state, breadcrumbs, direct URL, refresh,
back/forward, mobile) was **not** exercised in Phase 0; the Blade shell and the 68-route smoke prove
the routes resolve, not the interaction details. Scheduled for Phase 1 (`05`).

---

## 3. Public website routes and URL ownership

| Pattern | Owner | Notes |
|---|---|---|
| `/` and `/{path}/` | **Static file if one exists**, otherwise `SiteRouter` (CMS page or 301) | `public/<slug>/index.html` wins at the web-server level |
| `/media/{media}/{name}` | Laravel controller | uploads outside the web root |
| `/assets/theme.css`, `/theme/{hash}.css` | Laravel (`ThemeCss`) | theme tokens from the database |
| `/robots.txt`, `/sitemap.xml` | Laravel (`SitemapController`) | sitemap lists **143** URLs (Verified) |
| `/chat/widget.js` | Laravel | 404s while the widget is off |
| `/q/{public_id}`, `/i/{public_id}`, `/p/{project}` | signed links (`signed:relative`) | client quote/invoice/project portals |
| `/portal` + code entry | `ClientPortalController` | six-digit code, throttled 5/10 per minute |

`SiteRouter` reserves `install, up, build, vendor, storage, api, webhooks, livewire, q, i, p, portal,
admin` so a CMS page can never shadow them.

### 3.1 Static vs CMS — the number that matters

| Measure | Value | Evidence |
|---|---:|---|
| Exported frontend page folders (`public/*/index.html`) | 79 (+ home `public/index.html` = **80 URL owners**) | `ls -d` |
| CMS pages | **143** | DB |
| CMS pages whose URL is owned by a static file | **80** | set intersection of slugs |
| CMS pages served by Laravel (no static twin) | **63** | set difference |
| CMS pages with no static twin (sample) | `/lahore/dha/`, `/lahore/bahria-town/`, `/lahore/gulberg/`, `/lahore/johar-town/`, `/lahore/cantt/`, `/lahore/lake-city/`, … | smoke |
| Sitemap URLs | 143 | `curl /sitemap.xml \| grep -c "<loc>"` |

Verified byte-for-byte: `GET /about/` returns **exactly** `public/about/index.html`
(sha256 `ee0ac387308942d0…`, 83,451 bytes both). This is **by design** — `docs/13-V1-TO-V2-CONVERSION-PLAN.md`
§3 calls the file's presence "the switch" — but the conversion has not started, so **publishing a
CMS page does not change its public URL today**. That is a scope decision for the owner, not a bug,
and it is the single largest gap between "Page Builder publish" and "live site" (finding W-01).

---

## 4. API and integration surface (10 + 4 routes)

| Method | Path | Purpose | Auth | Throttle |
|---|---|---|---|---|
| GET | `api/bridge/v1/leads` | MCP bridge: read leads | `AuthenticateMcpBridge` token | 120/min |
| GET | `api/bridge/v1/quotes` | MCP bridge: read quotations | same | 120/min |
| GET | `api/bridge/v1/status` | bridge health | same | 120/min |
| GET/POST | `api/forms.php` | legacy form bridge (v1 pages → CRM) | public + origin check | `public-site` |
| GET/POST | `api/chat.php` | legacy chat bridge | public | `public-site` |
| POST | `api/public/leads` | website enquiry | public + Turnstile/origin | `public-leads` |
| POST | `api/public/chat/start|send` | web chat | public | `chat-write` |
| GET | `api/public/chat/messages` | web chat poll | public | `chat-poll` |
| POST | `webhooks/whatsapp` | WhatsApp Cloud API | signature (`X-Hub-Signature-256`) | `webhooks` |
| POST | `webhooks/telegram`, `/telegram/{secret}` | Telegram bot | secret header + throttle | `telegram-webhooks` |

The API surface is small on purpose: the admin is server-rendered Livewire, not an SPA. There is **no
public API versioning beyond `bridge/v1`**, and no OpenAPI document. Phase 1 (`06`) will verify each
contract, idempotency and error shape.

---

## 5. Modules that exist in code but not in the sidebar

These are real routes reachable by URL but not listed in `AdminNavigation` — either deliberate (hub
tabs) or a discoverability defect:

`admin/my-day`, `admin/notifications`, `admin/pipeline`, `admin/recycle-bin` (linked),
`admin/sales/dues`, `admin/sales/tracking/{quotes,invoices}`, `admin/receivables`,
`admin/reports/{revenue-collections,sales-funnel}`, `admin/quote-templates`, `admin/rate-book`,
`admin/description-library`, `admin/document-design`, `admin/companies`, `admin/site-visits`,
`admin/invoices/{invoice}/pdf`, `admin/quotes/{quote}`, `admin/projects/{project}`,
`admin/media/{media}`.

Most are hub tabs (the sidebar links the hub, and `smoke.sh` follows the 301 into the right tab), so
they are **Observed as intentional**; Phase 3 will confirm each has an in-page entry point and that
no URL is orphaned.
