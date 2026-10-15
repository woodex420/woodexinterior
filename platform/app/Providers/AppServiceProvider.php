<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Crm\Events\LeadCaptured;
use App\Domain\Crm\Listeners\SendNewLeadAlerts;
use App\Domain\Crm\Models\Activity;
use App\Domain\Crm\Models\Company;
use App\Domain\Crm\Models\Contact;
use App\Domain\Crm\Models\Lead;
use App\Domain\Crm\Models\SiteVisit;
use App\Domain\Crm\Models\Task;
use App\Domain\Crm\Policies\CompanyPolicy;
use App\Domain\Crm\Policies\ContactPolicy;
use App\Domain\Crm\Policies\LeadPolicy;
use App\Domain\Projects\Models\Project;
use App\Domain\Sales\Models\Invoice;
use App\Domain\Sales\Models\Payment;
use App\Domain\Sales\Models\Quote;
use App\Domain\System\Models\ChangeRequest;
use App\Http\Middleware\EnsureUserIsActive;
use App\Integrations\Telegram\Clients\TelegramBotApi;
use App\Integrations\Telegram\Contracts\TelegramGateway;
use App\Models\User;
use Illuminate\Auth\Events\Login;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Livewire\Livewire;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(TelegramGateway::class, fn (): TelegramGateway => new TelegramBotApi(
            config('services.telegram.bot_token'),
            (string) config('services.telegram.api_base', 'https://api.telegram.org'),
        ));
    }

    public function boot(): void
    {
        Model::preventLazyLoading(! $this->app->isProduction());

        // Short, stable type names in polymorphic columns (docs/04 §1).
        Relation::enforceMorphMap([
            'user' => User::class,
            'company' => Company::class,
            'contact' => Contact::class,
            'lead' => Lead::class,
            'activity' => Activity::class,
            'task' => Task::class,
            'site_visit' => SiteVisit::class,
            'quote' => Quote::class,
            'invoice' => Invoice::class,
            'payment' => Payment::class,
            'project' => Project::class,
            'change_request' => ChangeRequest::class,
        ]);

        Gate::policy(Lead::class, LeadPolicy::class);
        Gate::policy(Contact::class, ContactPolicy::class);
        Gate::policy(Company::class, CompanyPolicy::class);

        // The owner can do everything (PRD §7).
        Gate::before(fn (User $user): ?bool => $user->hasRole('owner') ? true : null);

        Password::defaults(fn () => Password::min(10)->letters()->numbers());

        RateLimiter::for('public-leads', fn (Request $request): array => [
            Limit::perMinute(5)->by('leads-min:'.$request->ip()),
            Limit::perDay(50)->by('leads-day:'.$request->ip()),
        ]);

        RateLimiter::for('webhooks', fn (Request $request): Limit => Limit::perMinute(120)->by('webhook:'.$request->ip()));

        // Re-applied on every Livewire update request, not only on the first page load.
        Livewire::addPersistentMiddleware([EnsureUserIsActive::class]);

        Event::listen(LeadCaptured::class, SendNewLeadAlerts::class);

        Event::listen(Login::class, function (Login $event): void {
            if ($event->user instanceof User) {
                $event->user->forceFill([
                    'last_login_at' => now(),
                    'last_login_ip' => request()->ip(),
                ])->saveQuietly();
            }
        });
    }
}
