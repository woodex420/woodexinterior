<?php

use App\Http\Controllers\Admin\ClientStatementController;
use App\Http\Controllers\Admin\InvoicePdfController;
use App\Http\Controllers\Admin\MediaController;
use App\Http\Controllers\Admin\QuotePdfController;
use App\Http\Controllers\Admin\SystemUpdateController;
use App\Livewire\Admin\Accounts\ChartOfAccounts;
use App\Livewire\Admin\Accounts\JournalsIndex;
use App\Livewire\Admin\Accounts\MethodAccounts;
use App\Livewire\Admin\Accounts\PeriodsIndex;
use App\Livewire\Admin\Conversations\CannedRepliesIndex;
use App\Livewire\Admin\Conversations\Inbox;
use App\Livewire\Admin\Crm\AssignmentRules;
use App\Livewire\Admin\Crm\CompanyShow;
use App\Livewire\Admin\Crm\ContactShow;
use App\Livewire\Admin\Crm\LeadShow;
use App\Livewire\Admin\Hubs\ContactsHub;
use App\Livewire\Admin\Hubs\DashboardHub;
use App\Livewire\Admin\Hubs\DocumentsHub;
use App\Livewire\Admin\Hubs\InvoicesHub;
use App\Livewire\Admin\Hubs\LeadsHub;
use App\Livewire\Admin\Hubs\PaymentsHub;
use App\Livewire\Admin\Hubs\QuotationsHub;
use App\Livewire\Admin\Hubs\ReportsHub;
use App\Livewire\Admin\Hubs\TasksHub;
use App\Livewire\Admin\NotificationsPage;
use App\Livewire\Admin\Profile\Security;
use App\Livewire\Admin\Projects\ProjectBoard;
use App\Livewire\Admin\Projects\ProjectShow;
use App\Livewire\Admin\Projects\ProjectsIndex;
use App\Livewire\Admin\Sales\Approvals;
use App\Livewire\Admin\Sales\EstimateBuilder;
use App\Livewire\Admin\Sales\EstimationsIndex;
use App\Livewire\Admin\Sales\QuoteBuilder;
use App\Livewire\Admin\Sales\QuoteCreate;
use App\Livewire\Admin\System\AuditLog;
use App\Livewire\Admin\System\AutomationsIndex;
use App\Livewire\Admin\System\ChangeRequests;
use App\Livewire\Admin\System\DataImport;
use App\Livewire\Admin\System\Health;
use App\Livewire\Admin\System\Integrations;
use App\Livewire\Admin\System\RecycleBin;
use App\Livewire\Admin\System\SettingsPage;
use App\Livewire\Admin\System\UsersIndex;
use App\Livewire\Admin\System\WhatsAppTemplates;
use App\Livewire\Admin\Website\MediaLibrary;
use App\Livewire\Admin\Website\MenusIndex;
use App\Livewire\Admin\Website\PageEditor;
use App\Livewire\Admin\Website\PagesIndex;
use App\Livewire\Admin\Website\RedirectsIndex;
use App\Livewire\Admin\Website\ThemeBuilder;
use App\Livewire\Admin\Website\WebsiteSettings;
use Illuminate\Support\Facades\Route;

/*
| Admin routes (prefix/domain and auth middleware are applied in routes/web.php).
| Navigation groups follow docs/01-MASTER-PLAN.md §4; screens arrive phase by phase.
*/

// P10.1 workflow hubs (docs/27): one page per workflow, tabs inside; old URLs redirect to the tab.
Route::livewire('/', DashboardHub::class)->name('dashboard');
Route::livewire('/notifications', NotificationsPage::class)->name('notifications');
Route::get('/my-day', fn () => redirect()->route('admin.dashboard', ['tab' => 'my-day'], 301))->name('my-day');

// CRM (P1): Leads (list · pipeline · tracking), Contacts (people · companies), Tasks & Visits
Route::livewire('/leads', LeadsHub::class)->name('leads.index');
Route::livewire('/leads/{lead}', LeadShow::class)->name('leads.show');
Route::get('/pipeline', fn () => redirect()->route('admin.leads.index', ['tab' => 'pipeline'], 301))->name('pipeline');
Route::livewire('/contacts', ContactsHub::class)->name('contacts.index');
Route::livewire('/contacts/{contact}', ContactShow::class)->name('contacts.show');
Route::livewire('/companies/{company}', CompanyShow::class)->name('companies.show');
Route::get('/companies', fn () => redirect()->route('admin.contacts.index', ['tab' => 'companies'], 301))->name('companies.index');
Route::livewire('/tasks', TasksHub::class)->name('tasks.index');
Route::get('/site-visits', fn () => redirect()->route('admin.tasks.index', ['tab' => 'visits'], 301))->name('visits.index');

// Sales (P2): Estimations → Quotations (all · tracking · templates) → Invoices (all · tracking) → Payments (all · dues · receivables) → Documents
Route::livewire('/quotes', QuotationsHub::class)->name('quotes.index')->middleware('can:quotes.view');
Route::livewire('/quotes/new', QuoteCreate::class)->name('quotes.create')->middleware('can:quotes.manage');
Route::livewire('/quotes/{quote}', QuoteBuilder::class)->name('quotes.show')->whereNumber('quote');
Route::get('/quotes/{quote}/pdf', QuotePdfController::class)->name('quotes.pdf')->whereNumber('quote');
Route::livewire('/invoices', InvoicesHub::class)->name('invoices.index')->middleware('can:invoices.view');
Route::get('/invoices/{invoice}/pdf', InvoicePdfController::class)->name('invoices.pdf')->whereNumber('invoice');
Route::livewire('/payments', PaymentsHub::class)->name('payments.index')->middleware('can:invoices.view');
Route::livewire('/estimations', EstimationsIndex::class)->name('estimations.index')->middleware('can:estimates.view');
Route::livewire('/estimations/new', EstimateBuilder::class)->name('estimations.create')->middleware('can:estimates.view');
Route::livewire('/estimations/{estimate}', EstimateBuilder::class)->name('estimations.show')->middleware('can:estimates.view')->whereNumber('estimate');
Route::get('/sales/dues', fn () => redirect()->route('admin.payments.index', ['tab' => 'dues'], 301))->name('sales.dues');
Route::get('/sales/tracking/quotes', fn () => redirect()->route('admin.quotes.index', ['tab' => 'tracking'], 301))->name('sales.tracking.quotes');
Route::get('/sales/tracking/invoices', fn () => redirect()->route('admin.invoices.index', ['tab' => 'tracking'], 301))->name('sales.tracking.invoices');
Route::get('/clients/{contact}/statement.pdf', ClientStatementController::class)->name('contacts.statement')->middleware('can:invoices.view')->whereNumber('contact');
Route::livewire('/accounts/chart', ChartOfAccounts::class)->name('accounts.chart')->middleware('can:accounts.view');
Route::livewire('/accounts/journals', JournalsIndex::class)->name('accounts.journals')->middleware('can:accounts.view');
Route::livewire('/accounts/periods', PeriodsIndex::class)->name('accounts.periods')->middleware('can:accounts.view');
Route::livewire('/accounts/methods', MethodAccounts::class)->name('accounts.methods')->middleware('can:accounts.view');
Route::livewire('/approvals', Approvals::class)->name('approvals')->middleware('can:quotes.approve');
Route::livewire('/sales/documents', DocumentsHub::class)->name('sales.documents')->middleware('can:quotes.view');
Route::get('/quote-templates', fn () => redirect()->route('admin.quotes.index', ['tab' => 'templates'], 301))->name('quote-templates');
Route::get('/rate-book', fn () => redirect()->route('admin.sales.documents', ['tab' => 'rate-book'], 301))->name('rate-book');
Route::get('/description-library', fn () => redirect()->route('admin.sales.documents', ['tab' => 'descriptions'], 301))->name('description-library');
Route::get('/document-design', fn () => redirect()->route('admin.sales.documents', ['tab' => 'design'], 301))->name('document-design');

// Projects (P4, PRJ-001…PRJ-005)
Route::livewire('/projects', ProjectsIndex::class)->name('projects.index')->middleware('can:projects.view');
Route::livewire('/projects/board', ProjectBoard::class)->name('projects.board')->middleware('can:projects.view');
Route::livewire('/projects/{project}', ProjectShow::class)->name('projects.show')->whereNumber('project');

// Conversations (P4, CNV-001/CNV-002, WAP-001…WAP-004): one inbox for WhatsApp and the website chat widget.
Route::livewire('/inbox', Inbox::class)->name('inbox')->middleware('can:inbox.view');

// Private attachments (GLB-006)
Route::get('/media/{media}/{conversion?}', MediaController::class)->name('media.show')->whereIn('conversion', ['thumb']);

// Website (phase P6, docs/09): the public website is content, edited here.
Route::livewire('/website/pages', PagesIndex::class)->name('website.pages')->middleware('can:website.edit');
Route::livewire('/website/pages/new', PageEditor::class)->name('website.pages.create')->middleware('can:website.edit');
Route::livewire('/website/pages/{page}', PageEditor::class)->name('website.pages.edit')->middleware('can:website.edit');
Route::livewire('/website/media', MediaLibrary::class)->name('website.media')->middleware('can:website.edit');
Route::livewire('/website/menus', MenusIndex::class)->name('website.menus')->middleware('can:website.edit');
Route::livewire('/website/redirects', RedirectsIndex::class)->name('website.redirects')->middleware('can:website.edit');
Route::livewire('/website/settings', WebsiteSettings::class)->name('website.settings')->middleware('can:settings.manage');
Route::livewire('/website/theme', ThemeBuilder::class)->name('website.theme')->middleware('can:theme.manage');

// Profile
Route::livewire('/profile/security', Security::class)->name('profile.security');

// Reports (P3): the group folded into Overview — one page, two views (P10.1)
Route::livewire('/reports', ReportsHub::class)->name('reports.index')->middleware('can:reports.view');
Route::get('/reports/sales-funnel', fn () => redirect()->route('admin.reports.index', ['tab' => 'funnel'], 301))->name('reports.funnel');
Route::get('/reports/revenue-collections', fn () => redirect()->route('admin.reports.index', ['tab' => 'revenue'], 301))->name('reports.revenue');
Route::get('/receivables', fn () => redirect()->route('admin.payments.index', ['tab' => 'receivables'], 301))->name('reports.receivables');

// System
Route::livewire('/settings', SettingsPage::class)->name('settings')->middleware('can:settings.manage');
Route::livewire('/settings/canned-replies', CannedRepliesIndex::class)->name('canned-replies')->middleware('can:settings.manage');
Route::livewire('/settings/lead-assignment', AssignmentRules::class)->name('assignment-rules')->middleware('can:settings.manage');
Route::livewire('/users', UsersIndex::class)->name('users.index')->middleware('can:users.manage');
Route::livewire('/system/health', Health::class)->name('system.health')->middleware('can:system.health');

Route::livewire('/automation', AutomationsIndex::class)->name('automation.index')->middleware('can:automation.manage');
Route::livewire('/system/change-requests', ChangeRequests::class)->name('system.change-requests')->middleware('can:system.change_requests');
Route::livewire('/system/audit', AuditLog::class)->name('system.audit')->middleware('can:audit.view');
// Database update after a deploy (D-58): reachable while migrations are pending and before 2FA setup.
Route::get('/system/updates', [SystemUpdateController::class, 'show'])->name('system.updates')
    ->middleware('can:system.health')->withoutMiddleware(['db-current', 'two-factor']);
Route::post('/system/updates', [SystemUpdateController::class, 'apply'])->name('system.updates.apply')
    ->middleware('can:system.health')->withoutMiddleware(['db-current', 'two-factor']);
Route::livewire('/recycle-bin', RecycleBin::class)->name('recycle-bin')->middleware('can:leads.delete');
Route::livewire('/integrations', Integrations::class)->name('integrations')->middleware('can:integrations.manage');
Route::livewire('/integrations/whatsapp-templates', WhatsAppTemplates::class)->name('whatsapp.templates')->middleware('can:integrations.manage');
Route::livewire('/data-import', DataImport::class)->name('data-import')->middleware('can:system.import');
