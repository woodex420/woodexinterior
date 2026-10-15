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
                self::item('Approvals', 'check-circle', null, null, 'P2'),
            ]),
            self::group('CRM', [
                self::item('Leads', 'users', 'admin.leads.index', 'leads.view'),
                self::item('Pipeline', 'kanban', 'admin.pipeline', 'leads.view'),
                self::item('Contacts', 'contact', 'admin.contacts.index', 'contacts.view'),
                self::item('Companies', 'building', null, null, 'P2'),
                self::item('Tasks', 'tasks', 'admin.tasks.index', 'leads.view'),
                self::item('Site Visits', 'calendar', 'admin.visits.index', 'leads.view'),
            ]),
            self::group('Sales', [
                self::item('Quotations', 'quote', null, null, 'P2'),
                self::item('Invoices', 'invoice', null, null, 'P3'),
                self::item('Payments', 'wallet', null, null, 'P3'),
                self::item('Receivables', 'clock', null, null, 'P3'),
                self::item('Quote Templates', 'templates', null, null, 'P2'),
                self::item('Rate Book', 'book', null, null, 'P2'),
            ]),
            self::group('Projects', [
                self::item('Projects', 'hammer', null, null, 'P3'),
                self::item('Client Updates', 'image', null, null, 'P8'),
            ]),
            self::group('Inbox', [
                self::item('Conversations', 'inbox', null, null, 'P4'),
                self::item('Chat Bot', 'bot', null, null, 'P4'),
            ]),
            self::group('Automation', [
                self::item('Follow-up Sequences', 'zap', null, null, 'P4'),
                self::item('WhatsApp Templates', 'whatsapp', null, null, 'P4'),
                self::item('Alert Rules', 'bell', null, null, 'P5'),
            ]),
            self::group('Marketing', [
                self::item('Social Planner', 'megaphone', null, null, 'P7'),
                self::item('Lead Ads', 'sparkles', null, null, 'P7'),
            ]),
            self::group('Website', [
                self::item('Pages', 'layout', null, null, 'P6'),
                self::item('Theme Builder', 'palette', null, null, 'P6'),
                self::item('Media', 'image', null, null, 'P6'),
                self::item('SEO & Redirects', 'globe', null, null, 'P6'),
            ]),
            self::group('Reports', [
                self::item('Sales Funnel', 'chart', null, null, 'P3'),
                self::item('Revenue & Collections', 'banknote', null, null, 'P3'),
            ]),
            self::group('System', [
                self::item('Settings', 'settings', 'admin.settings', 'settings.manage'),
                self::item('Users & Roles', 'shield', 'admin.users.index', 'users.manage'),
                self::item('Integrations', 'link', null, null, 'P4'),
                self::item('Data Import', 'database', null, null, 'CLI'),
                self::item('Health', 'health', 'admin.system.health', 'system.health'),
                self::item('Change Requests', 'lightbulb', null, null, 'P5'),
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
