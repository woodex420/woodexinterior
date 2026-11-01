| Group | Item | Route name | URI | Component | Permission | Phase | Test evidence |
|---|---|---|---|---|---|---|---|
| Overview | Dashboard | admin.dashboard | admin | invokable | dashboard.view | — | AdminAccessTest.php |
| Overview | Approvals | admin.approvals | admin/approvals | Approvals | quotes.approve | — | P10SidebarHubsTest.php |
| Overview | Reports | admin.reports.index | admin/reports | ReportsHub | reports.view | — | — |
| CRM | Leads | admin.leads.index | admin/leads | LeadsHub | leads.view | — | AdminAccessTest.php |
| CRM | Contacts | admin.contacts.index | admin/contacts | ContactsHub | contacts.view | — | CompaniesTest.php |
| CRM | Tasks & Visits | admin.tasks.index | admin/tasks | TasksHub | leads.view | — | — |
| Sales | Estimations | admin.estimations.index | admin/estimations | EstimationsIndex | estimates.view | — | EstimatesTest.php |
| Sales | Quotations | admin.quotes.index | admin/quotes | QuotationsHub | quotes.view | — | QuoteBuilderTest.php |
| Sales | Invoices | admin.invoices.index | admin/invoices | InvoicesHub | invoices.view | — | AdvanceInvoiceTest.php |
| Sales | Payments | admin.payments.index | admin/payments | PaymentsHub | invoices.view | — | PaymentsTest.php |
| Sales | Documents | admin.sales.documents | admin/sales/documents | DocumentsHub | quotes.view | — | DocumentLibraryTest.php |
| Accounts | Chart of Accounts | admin.accounts.chart | admin/accounts/chart | ChartOfAccounts | accounts.view | — | AccountsLedgerTest.php |
| Accounts | Journals | admin.accounts.journals | admin/accounts/journals | JournalsIndex | accounts.view | — | AccountsLedgerTest.php |
| Accounts | Periods | admin.accounts.periods | admin/accounts/periods | PeriodsIndex | accounts.view | — | AccountsLedgerTest.php |
| Accounts | Payment Methods | admin.accounts.methods | admin/accounts/methods | MethodAccounts | accounts.view | — | GlSalesPostingTest.php |
| Projects | Projects | admin.projects.index | admin/projects | ProjectsIndex | projects.view | — | ProjectsTest.php |
| Projects | Board | admin.projects.board | admin/projects/board | ProjectBoard | projects.view | — | ProjectsTest.php |
| Projects | Client Portal | — | — | — | — | P4b | — |
| Projects | Handovers | — | — | — | — | P4b | — |
| Inbox | Conversations | admin.inbox | admin/inbox | Inbox | inbox.view | — | CannedRepliesTest.php |
| Inbox | Chat Bot | — | — | — | — | P4 | — |
| Automation | Automations | admin.automation.index | admin/automation | AutomationsIndex | automation.manage | — | AutomationsDashboardTest.php |
| Automation | Sequences | admin.automation.sequences | admin/automation/sequences | SequencesIndex | automation.manage | — | SequenceEngineTest.php |
| Automation | WhatsApp Templates | admin.whatsapp.templates | admin/integrations/whatsapp-templates | WhatsAppTemplates | integrations.manage | — | WhatsAppTemplateManagerTest.php |
| Automation | Alert Rules | — | — | — | — | P5 | — |
| Marketing | Social Planner | admin.marketing.social | admin/marketing/social | SocialPlannerIndex | social.manage | — | SocialPlannerTest.php |
| Marketing | Lead Ads | — | — | — | — | P7 | — |
| Website | All pages | admin.website.pages | admin/website/pages | PagesIndex | website.edit | — | CampaignCanvasTest.php |
| Website | Page builder | admin.website.pages.create | admin/website/pages/new | PageEditor | website.edit | — | BuilderHistoryAuditTest.php |
| Website | Section library | admin.website.sections | admin/website/sections | SectionLibraryIndex | website.edit | — | WebsiteAdminParityTest.php |
| Website | Header & footer | admin.website.menus | admin/website/menus | MenusIndex | website.edit | — | MenusTreeTest.php |
| Website | Hero slides | admin.website.heroes | admin/website/heroes | HeroSlidesIndex | website.edit | — | PageBuilderLiveTest.php |
| Website | Redirects | admin.website.redirects | admin/website/redirects | RedirectsIndex | website.edit | — | WebsiteAdminTest.php |
| Website | Media | admin.website.media | admin/website/media | MediaLibrary | website.edit | — | WebsiteAdminTest.php |
| Website | Content library | admin.website.content | admin/website/content | ContentLibraryIndex | website.edit | — | ContentLibraryTest.php |
| Website | Team | admin.website.team | admin/website/team | TeamMembersIndex | website.edit | — | TeamSectionTest.php |
| Website | SEO | admin.website.seo.overview | admin/website/seo-overview | SeoOverviewIndex | website.edit | — | WebsiteAdminParityTest.php |
| Website | SEO agent | admin.website.seo | admin/website/seo | SeoAgentIndex | website.edit | — | SeoAgentTest.php |
| Website | Speed | admin.website.speed | admin/website/speed | SpeedIndex | website.edit | — | SpeedDashboardTest.php |
| Website | Site health | admin.website.health | admin/website/health | SiteHealthIndex | website.edit | — | WebsiteAdminParityTest.php |
| Website | Theme | admin.website.theme | admin/website/theme | ThemeBuilder | theme.manage | — | ThemeTokensTest.php |
| Website | Website Settings | admin.website.settings | admin/website/settings | WebsiteSettings | settings.manage | — | WebChatWidgetTest.php |
| System | Settings | admin.settings | admin/settings | SettingsPage | settings.manage | — | BotEngineTest.php |
| System | Users & Roles | admin.users.index | admin/users | UsersIndex | users.manage | — | UsersAndSettingsTest.php |
| System | Lead Assignment | admin.assignment-rules | admin/settings/lead-assignment | AssignmentRules | settings.manage | — | SettingsAndRulesTest.php |
| System | Canned replies | admin.canned-replies | admin/settings/canned-replies | CannedRepliesIndex | settings.manage | — | CannedRepliesTest.php |
| System | Integrations | admin.integrations | admin/integrations | Integrations | integrations.manage | — | ChangeRequestsBoardTest.php |
| System | Data Import | admin.data-import | admin/data-import | DataImport | system.import | — | SystemScreensTest.php |
| System | Recycle Bin | admin.recycle-bin | admin/recycle-bin | RecycleBin | leads.delete | — | QuoteScreensTest.php |
| System | Health | admin.system.health | admin/system/health | Health | system.health | — | DatabaseUpdatesTest.php |
| System | Audit log | admin.system.audit | admin/system/audit | AuditLog | audit.view | — | AuditLogTest.php |
| System | Change Requests | admin.system.change-requests | admin/system/change-requests | ChangeRequests | system.change_requests | — | ChangeRequestsBoardTest.php |
| System | Alert Rules | admin.system.alert-rules | admin/system/alert-rules | AlertRulesIndex | settings.manage | — | AlertRulesTest.php |
