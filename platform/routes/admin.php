<?php

use App\Livewire\Admin\Crm\LeadsIndex;
use App\Livewire\Admin\Crm\PipelineBoard;
use App\Livewire\Admin\Dashboard;
use App\Livewire\Admin\Profile\Security;
use App\Livewire\Admin\System\Health;
use Illuminate\Support\Facades\Route;

/*
| Admin routes (prefix/domain and auth middleware are applied in routes/web.php).
| Navigation groups follow docs/01-MASTER-PLAN.md §4; screens arrive phase by phase.
*/

Route::livewire('/', Dashboard::class)->name('dashboard');

// CRM (P1)
Route::livewire('/leads', LeadsIndex::class)->name('leads.index');
Route::livewire('/pipeline', PipelineBoard::class)->name('pipeline');

// Profile
Route::livewire('/profile/security', Security::class)->name('profile.security');

// System
Route::livewire('/system/health', Health::class)->name('system.health')->middleware('can:system.health');
