<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Livewire\Concerns\InteractsWithAdmin;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Notification centre (GLB-008): everything the bell has shown, with unread filter.
 */
#[Layout('layouts::admin')]
#[Title('Notifications')]
class NotificationsPage extends Component
{
    use InteractsWithAdmin, WithPagination;

    #[Url(except: 'all')]
    public string $filter = 'all';

    public function updatingFilter(): void
    {
        $this->resetPage();
    }

    public function open(string $id): void
    {
        $notification = $this->user()->notifications()->findOrFail($id);
        $notification->markAsRead();

        if (is_string($url = $notification->data['url'] ?? null) && $url !== '') {
            $this->redirect($url, navigate: str_starts_with($url, url('/')));
        }
    }

    public function markAllRead(): void
    {
        $this->user()->unreadNotifications()->update(['read_at' => now()]);
        $this->toast('All notifications marked as read.');
    }

    public function render(): View
    {
        $query = $this->filter === 'unread' ? $this->user()->unreadNotifications() : $this->user()->notifications();

        return view('livewire.admin.notifications-page', [
            'notifications' => $query->latest()->paginate(25),
            'unread' => $this->user()->unreadNotifications()->count(),
        ]);
    }
}
