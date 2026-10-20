<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Hosts the ported v2.1 admin screens inside Woodex OS (docs/audit/16.7, 16.8).
 *
 * One URL per screen, full document — the same exception the page builder already has, because these
 * screens bring their own stylesheet and their own layout and must not fight the OS theme. The OS
 * session, not theirs, decides who is here.
 */
final class WebsiteStudioController
{
    /** Screens that are ported and switched on. A screen is added here only when its actions work. */
    public const array SCREENS = [
        'logos' => ['title' => 'Client logos', 'view' => 'logos'],
        'blog' => ['title' => 'Blog & insights', 'view' => 'blog'],
        'portfolio' => ['title' => 'Portfolio', 'view' => 'portfolio'],
        'services' => ['title' => 'Service & site pages', 'view' => 'services'],
        'cities' => ['title' => 'City pages', 'view' => 'cities'],
    ];

    public function show(Request $request, string $screen): View
    {
        abort_unless(isset(self::SCREENS[$screen]), 404);

        /** @var User $user */
        $user = $request->user();

        $definition = self::SCREENS[$screen];

        return view('admin.website.studio', [
            'screen' => $definition['view'],
            'title' => $definition['title'],
            'user' => [
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->hasRole('owner') ? 'owner' : 'admin',
            ],
        ]);
    }
}
