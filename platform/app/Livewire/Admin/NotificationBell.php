<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Livewire\Concerns\InteractsWithAdmin;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * Header bell (GLB-008): unread count, latest notifications, mark read and deep links.
 * Polls every 30 s while the tab is visible (real-time broadcasting replaces this in P4).
 */
class NotificationBell extends Component
{
    use InteractsWithAdmin;

    public function open(string $id): void
    {
        $notification = $this->user()->notifications()->findOrFail($id);
        $notification->markAsRead();

        $url = $notification->data['url'] ?? null;

        if (is_string($url) && $url !== '') {
            $this->redirect($url, navigate: str_starts_with($url, url('/')));
        }
    }

    public function markAllRead(): void
    {
        $this->user()->unreadNotifications()->update(['read_at' => now()]);
    }

    public function render(): View
    {
        $user = $this->user();

        return view('livewire.admin.notification-bell', [
            'notifications' => $user->notifications()->latest()->limit(8)->get(),
            'unread' => $user->unreadNotifications()->count(),
        ]);
    }
}
