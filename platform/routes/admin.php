<?php

use App\Http\Controllers\Admin\InvoicePdfController;
use App\Http\Controllers\Admin\MediaController;
use App\Http\Controllers\Admin\QuotePdfController;
use App\Http\Controllers\Admin\SystemUpdateController;
use App\Livewire\Admin\Crm\AssignmentRules;
use App\Livewire\Admin\Crm\CompaniesIndex;
use App\Livewire\Admin\Crm\CompanyShow;
use App\Livewire\Admin\Crm\ContactShow;
use App\Livewire\Admin\Crm\ContactsIndex;
use App\Livewire\Admin\Crm\LeadShow;
use App\Livewire\Admin\Crm\LeadsIndex;
use App\Livewire\Admin\Crm\PipelineBoard;
use App\Livewire\Admin\Crm\TasksIndex;
use App\Livewire\Admin\Crm\VisitsIndex;
use App\Livewire\Admin\Dashboard;
use App\Livewire\Admin\MyDay;
use App\Livewire\Admin\NotificationsPage;
use App\Livewire\Admin\Profile\Security;
use App\Livewire\Admin\Sales\Approvals;
use App\Livewire\Admin\Sales\InvoicesIndex;
use App\Livewire\Admin\Sales\PaymentsIndex;
use App\Livewire\Admin\Sales\QuoteBuilder;
use App\Livewire\Admin\Sales\QuoteCreate;
use App\Livewire\Admin\Sales\QuotesIndex;
use App\Livewire\Admin\Sales\QuoteTemplates;
use App\Livewire\Admin\Sales\RateBook;
use App\Livewire\Admin\System\DataImport;
use App\Livewire\Admin\System\Health;
use App\Livewire\Admin\System\Integrations;
use App\Livewire\Admin\System\RecycleBin;
use App\Livewire\Admin\System\SettingsPage;
use App\Livewire\Admin\System\UsersIndex;
use App\Livewire\Admin\Website\MediaLibrary;
use App\Livewire\Admin\Website\MenusIndex;
use App\Livewire\Admin\Website\PageEditor;
use App\Livewire\Admin\Website\PagesIndex;
use App\Livewire\Admin\Website\RedirectsIndex;
use App\Livewire\Admin\Website\WebsiteSettings;
use Illuminate\Support\Facades\Route;

/*
| Admin routes (prefix/domain and auth middleware are applied in routes/web.php).
| Navigation groups follow docs/01-MASTER-PLAN.md §4; screens arrive phase by phase.
*/

Route::livewire('/', Dashboard::class)->name('dashboard');
Route::livewire('/my-day', MyDay::class)->name('my-day');
Route::livewire('/notifications', NotificationsPage::class)->name('notifications');

// CRM (P1)
Route::livewire('/leads', LeadsIndex::class)->name('leads.index');
Route::livewire('/leads/{lead}', LeadShow::class)->name('leads.show');
Route::livewire('/pipeline', PipelineBoard::class)->name('pipeline');
Route::livewire('/contacts', ContactsIndex::class)->name('contacts.index');
Route::livewire('/contacts/{contact}', ContactShow::class)->name('contacts.show');
Route::livewire('/companies', CompaniesIndex::class)->name('companies.index');
Route::livewire('/companies/{company}', CompanyShow::class)->name('companies.show');
Route::livewire('/tasks', TasksIndex::class)->name('tasks.index');
Route::livewire('/site-visits', VisitsIndex::class)->name('visits.index');

// Sales (P2)
Route::livewire('/quotes', QuotesIndex::class)->name('quotes.index')->middleware('can:quotes.view');
Route::livewire('/quotes/new', QuoteCreate::class)->name('quotes.create')->middleware('can:quotes.manage');
Route::livewire('/quotes/{quote}', QuoteBuilder::class)->name('quotes.show')->whereNumber('quote');
Route::get('/quotes/{quote}/pdf', QuotePdfController::class)->name('quotes.pdf')->whereNumber('quote');
Route::livewire('/invoices', InvoicesIndex::class)->name('invoices.index')->middleware('can:invoices.view');
Route::get('/invoices/{invoice}/pdf', InvoicePdfController::class)->name('invoices.pdf')->whereNumber('invoice');
Route::livewire('/payments', PaymentsIndex::class)->name('payments.index')->middleware('can:invoices.view');
Route::livewire('/approvals', Approvals::class)->name('approvals')->middleware('can:quotes.approve');
Route::livewire('/quote-templates', QuoteTemplates::class)->name('quote-templates')->middleware('can:quotes.view');
Route::livewire('/rate-book', RateBook::class)->name('rate-book')->middleware('can:quotes.view');

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

// Profile
Route::livewire('/profile/security', Security::class)->name('profile.security');

// System
Route::livewire('/settings', SettingsPage::class)->name('settings')->middleware('can:settings.manage');
Route::livewire('/settings/lead-assignment', AssignmentRules::class)->name('assignment-rules')->middleware('can:settings.manage');
Route::livewire('/users', UsersIndex::class)->name('users.index')->middleware('can:users.manage');
Route::livewire('/system/health', Health::class)->name('system.health')->middleware('can:system.health');
// Database update after a deploy (D-58): reachable while migrations are pending and before 2FA setup.
Route::get('/system/updates', [SystemUpdateController::class, 'show'])->name('system.updates')
    ->middleware('can:system.health')->withoutMiddleware(['db-current', 'two-factor']);
Route::post('/system/updates', [SystemUpdateController::class, 'apply'])->name('system.updates.apply')
    ->middleware('can:system.health')->withoutMiddleware(['db-current', 'two-factor']);
Route::livewire('/recycle-bin', RecycleBin::class)->name('recycle-bin')->middleware('can:leads.delete');
Route::livewire('/integrations', Integrations::class)->name('integrations')->middleware('can:integrations.manage');
Route::livewire('/data-import', DataImport::class)->name('data-import')->middleware('can:system.import');
