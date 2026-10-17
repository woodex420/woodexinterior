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
     * @return list<array{title: string, items: list<array{label: string, icon: string, route: string|null, permission: string|null, phase: string|null}>}>
     */
    public static function groups(): array
    {
        return [
            self::group('Overview', [
                self::item('Dashboard', 'dashboard', 'admin.dashboard', 'dashboard.view'),
                self::item('My Day', 'sun', 'admin.my-day', 'leads.view'),
                self::item('Approvals', 'check-circle', 'admin.approvals', 'quotes.approve'),
            ]),
            self::group('CRM', [
                self::item('Leads', 'users', 'admin.leads.index', 'leads.view'),
                self::item('Pipeline', 'kanban', 'admin.pipeline', 'leads.view'),
                self::item('Contacts', 'contact', 'admin.contacts.index', 'contacts.view'),
                self::item('Companies', 'building', 'admin.companies.index', 'contacts.view'),
                self::item('Tasks', 'tasks', 'admin.tasks.index', 'leads.view'),
                self::item('Site Visits', 'calendar', 'admin.visits.index', 'leads.view'),
            ]),
            self::group('Sales', [
                self::item('Quotations', 'quote', 'admin.quotes.index', 'quotes.view'),
                self::item('Invoices', 'invoice', 'admin.invoices.index', 'invoices.view'),
                self::item('Payments', 'wallet', 'admin.payments.index', 'invoices.view'),
                self::item('Due Payments', 'clock', 'admin.sales.dues', 'invoices.view'),
                self::item('Receivables', 'clock', 'admin.reports.receivables', 'invoices.view'),
                self::item('Quote Templates', 'templates', 'admin.quote-templates', 'quotes.view'),
                self::item('Rate Book', 'book', 'admin.rate-book', 'quotes.view'),
                self::item('Description Library', 'book', 'admin.description-library', 'quotes.view'),
                self::item('Document Design', 'templates', 'admin.document-design', 'settings.manage'),
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
                self::item('Follow-up Sequences', 'zap', null, null, 'P4'),
                self::item('WhatsApp Templates', 'whatsapp', 'admin.whatsapp.templates', 'integrations.manage'),
                self::item('Alert Rules', 'bell', null, null, 'P5'),
            ]),
            self::group('Marketing', [
                self::item('Social Planner', 'megaphone', null, null, 'P7'),
                self::item('Lead Ads', 'sparkles', null, null, 'P7'),
            ]),
            self::group('Website', [
                self::item('Pages', 'layout', 'admin.website.pages', 'website.edit'),
                self::item('Media', 'image', 'admin.website.media', 'website.edit'),
                self::item('Menus', 'menu', 'admin.website.menus', 'website.edit'),
                self::item('SEO & Redirects', 'globe', 'admin.website.redirects', 'website.edit'),
                self::item('Website Settings', 'settings', 'admin.website.settings', 'settings.manage'),
                self::item('Theme Builder', 'palette', 'admin.website.theme', 'theme.manage'),
            ]),
            self::group('Reports', [
                self::item('Sales Funnel', 'chart', 'admin.reports.funnel', 'reports.view'),
                self::item('Revenue & Collections', 'banknote', 'admin.reports.revenue', 'reports.finance'),
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
    private static function group(string $title, array $items): array
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
