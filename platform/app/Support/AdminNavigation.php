<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Route;

/**
 * Admin sidebar, grouped by job to be done (docs/01-MASTER-PLAN.md §4).
 * Screens that arrive in later phases are shown disabled with their phase badge,
 * so the team can see the whole roadmap from day one.
 */
final class AdminNavigation
{
    /**
     * Routes that answer with a full HTML document of their own (the Visual Page Builder). Livewire's
     * `wire:navigate` swaps only the body, so navigating there from the sidebar would drop the builder's
     * stylesheet and script — these links must do a normal page load.
     *
     * @var list<string>
     */
    public const array FULL_DOCUMENT = ['admin.website.builder', 'admin.website.logos', 'admin.website.blog', 'admin.website.portfolio', 'admin.website.forms', 'admin.website.estimator'];

    /**
     * @return list<array{title: string, items: list<array{label: string, icon: string, route: string|null, permission: string|null, phase: string|null}>}>
     */
    public static function groups(): array
    {
        return [
            // P10.1 (docs/27): one page per workflow, tabs inside — 49 live entries became 37.
            self::group('Overview', [
                self::item('Dashboard', 'dashboard', 'admin.dashboard', 'dashboard.view'),
                self::item('Approvals', 'check-circle', 'admin.approvals', 'quotes.approve'),
                self::item('Reports', 'chart', 'admin.reports.index', 'reports.view'),
            ]),
            self::group('CRM', [
                self::item('Leads', 'users', 'admin.leads.index', 'leads.view'),
                self::item('Contacts', 'contact', 'admin.contacts.index', 'contacts.view'),
                self::item('Tasks & Visits', 'tasks', 'admin.tasks.index', 'leads.view'),
            ]),
            self::group('Sales', [
                self::item('Estimations', 'calculator', 'admin.estimations.index', 'estimates.view'),
                self::item('Quotations', 'quote', 'admin.quotes.index', 'quotes.view'),
                self::item('Invoices', 'invoice', 'admin.invoices.index', 'invoices.view'),
                self::item('Payments', 'wallet', 'admin.payments.index', 'invoices.view'),
                self::item('Documents', 'book', 'admin.sales.documents', 'quotes.view'),
            ]),
            self::group('Accounts', [
                self::item('Chart of Accounts', 'book', 'admin.accounts.chart', 'accounts.view'),
                self::item('Journals', 'layers', 'admin.accounts.journals', 'accounts.view'),
                self::item('Periods', 'clock', 'admin.accounts.periods', 'accounts.view'),
                self::item('Payment Methods', 'wallet', 'admin.accounts.methods', 'accounts.view'),
            ]),
            self::group('Projects', [
                self::item('Projects', 'hammer', 'admin.projects.index', 'projects.view'),
                self::item('Board', 'kanban', 'admin.projects.board', 'projects.view'),
                self::item('Client Portal', 'link', null, null, 'P4b'),
                self::item('Handovers', 'check-circle', null, null, 'P4b'),
            ]),
            self::group('Inbox', [
                self::item('Conversations', 'inbox', 'admin.inbox', 'inbox.view'),
                self::item('Chat Bot', 'bot', null, null, 'P4'),
            ]),
            self::group('Automation', [
                self::item('Automations', 'zap', 'admin.automation.index', 'automation.manage'),
                self::item('Sequences', 'refresh', 'admin.automation.sequences', 'automation.manage'),
                self::item('WhatsApp Templates', 'whatsapp', 'admin.whatsapp.templates', 'integrations.manage'),
                self::item('Alert Rules', 'bell', null, null, 'P5'),
            ]),
            self::group('Marketing', [
                self::item('Social Planner', 'megaphone', 'admin.marketing.social', 'social.manage'),
                self::item('Lead Ads', 'sparkles', null, null, 'P7'),
            ]),
            // The Website section is the v2.1 front, grouped the way it is worked on (docs/16 §16.8):
            // build a page, fill it with content, then tune the site. The Content library left the nav on
            // 20 Oct — its records are the same pages and blocks the screens below edit, so nothing is lost.
            self::group('Pages & builder', [
                self::item('All pages', 'layout', 'admin.website.pages', 'website.edit'),
                self::item('Page builder', 'panel', 'admin.website.builder', 'website.edit'),
                self::item('Section library', 'layers', 'admin.website.sections', 'website.edit'),
                self::item('Header & footer', 'menu', 'admin.website.menus', 'website.edit'),
                self::item('Hero slides', 'image', 'admin.website.heroes', 'website.edit'),
                self::item('Redirects', 'globe', 'admin.website.redirects', 'website.edit'),
            ]),
            self::group('Content', [
                self::item('Blog', 'book', 'admin.website.blog', 'website.edit'),
                self::item('Portfolio', 'layout', 'admin.website.portfolio', 'website.edit'),
                // The six screens still to port; listed now so the section matches the target, disabled
                // until their actions are switched on in WebsiteStudioController::SCREENS.
                self::item('Page templates', 'templates', 'admin.website.templates', 'website.edit'),
                self::item('Forms', 'file', 'admin.website.forms', 'website.edit'),
                self::item('Estimator', 'calculator', 'admin.website.estimator', 'website.edit'),
                self::item('Client logos', 'image', 'admin.website.logos', 'website.edit'),
                self::item('Team', 'users', 'admin.website.team', 'website.edit'),
            ]),
            // Tools for the whole site, not for one page: no heading, the way the owner asked for them.
            self::group(null, [
                self::item('Media', 'folder', 'admin.website.media', 'website.edit'),
                self::item('SEO', 'target', 'admin.website.seo.overview', 'website.edit'),
                self::item('SEO agent', 'search', 'admin.website.seo', 'website.edit'),
                self::item('Speed', 'gauge', 'admin.website.speed', 'website.edit'),
                self::item('Site health', 'health', 'admin.website.health', 'website.edit'),
                self::item('Theme', 'palette', 'admin.website.theme', 'theme.manage'),
                self::item('Website Settings', 'settings', 'admin.website.settings', 'settings.manage'),
            ]),
            self::group('System', [
                self::item('Settings', 'settings', 'admin.settings', 'settings.manage'),
                self::item('Users & Roles', 'shield', 'admin.users.index', 'users.manage'),
                self::item('Lead Assignment', 'users', 'admin.assignment-rules', 'settings.manage'),
                self::item('Canned replies', 'message', 'admin.canned-replies', 'settings.manage'),
                self::item('Integrations', 'link', 'admin.integrations', 'integrations.manage'),
                self::item('Data Import', 'database', 'admin.data-import', 'system.import'),
                self::item('Recycle Bin', 'archive', 'admin.recycle-bin', 'leads.delete'),
                self::item('Health', 'health', 'admin.system.health', 'system.health'),
                self::item('Audit log', 'activity', 'admin.system.audit', 'audit.view'),
                self::item('Change Requests', 'lightbulb', 'admin.system.change-requests', 'system.change_requests'),
                self::item('Alert Rules', 'bell', 'admin.system.alert-rules', 'settings.manage'),
            ]),
        ];
    }

    /**
     * Groups filtered for a user: live items need their permission; planned items are always listed.
     *
     * @return list<array{title: string, items: list<array{label: string, icon: string, route: string|null, permission: string|null, phase: string|null}>}>
     */
    public static function for(?User $user): array
    {
        $groups = [];

        foreach (self::groups() as $group) {
            $items = array_values(array_filter($group['items'], static function (array $item) use ($user): bool {
                if ($item['route'] === null) {
                    return true;
                }

                if (! Route::has($item['route'])) {
                    return false;
                }

                return $item['permission'] === null || ($user?->can($item['permission']) ?? false);
            }));

            if ($items !== []) {
                $groups[] = ['title' => $group['title'], 'items' => $items];
            }
        }

        return $groups;
    }

    /**
     * @param  list<array{label: string, icon: string, route: string|null, permission: string|null, phase: string|null}>  $items
     * @return array{title: string, items: list<array{label: string, icon: string, route: string|null, permission: string|null, phase: string|null}>}
     */
    /**
     * A titled group collapses; a group with no title is a flat run of items (the Website tools).
     *
     * @param  list<array{label: string, icon: string, route: string|null, permission: string|null, phase: string|null}>  $items
     * @return array{title: string|null, items: list<array{label: string, icon: string, route: string|null, permission: string|null, phase: string|null}>}
     */
    private static function group(?string $title, array $items): array
    {
        return ['title' => $title, 'items' => $items];
    }

    /**
     * @return array{label: string, icon: string, route: string|null, permission: string|null, phase: string|null}
     */
    private static function item(string $label, string $icon, ?string $route, ?string $permission, ?string $phase = null): array
    {
        return ['label' => $label, 'icon' => $icon, 'route' => $route, 'permission' => $permission, 'phase' => $phase];
    }
}
