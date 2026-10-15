<?php

use App\Http\Controllers\Admin\MediaController;
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
use App\Livewire\Admin\System\DataImport;
use App\Livewire\Admin\System\Health;
use App\Livewire\Admin\System\Integrations;
use App\Livewire\Admin\System\RecycleBin;
use App\Livewire\Admin\System\SettingsPage;
use App\Livewire\Admin\System\UsersIndex;
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

// Private attachments (GLB-006)
Route::get('/media/{media}/{conversion?}', MediaController::class)->name('media.show')->whereIn('conversion', ['thumb']);

// Profile
Route::livewire('/profile/security', Security::class)->name('profile.security');

// System
Route::livewire('/settings', SettingsPage::class)->name('settings')->middleware('can:settings.manage');
Route::livewire('/settings/lead-assignment', AssignmentRules::class)->name('assignment-rules')->middleware('can:settings.manage');
Route::livewire('/users', UsersIndex::class)->name('users.index')->middleware('can:users.manage');
Route::livewire('/system/health', Health::class)->name('system.health')->middleware('can:system.health');
Route::livewire('/recycle-bin', RecycleBin::class)->name('recycle-bin')->middleware('can:leads.delete');
Route::livewire('/integrations', Integrations::class)->name('integrations')->middleware('can:integrations.manage');
Route::livewire('/data-import', DataImport::class)->name('data-import')->middleware('can:system.import');
