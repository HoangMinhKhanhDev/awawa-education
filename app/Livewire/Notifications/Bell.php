<?php

namespace App\Livewire\Notifications;

use Illuminate\Contracts\View\View;
use Livewire\Component;

class Bell extends Component
{
    public function markAllRead(): void
    {
        auth()->user()?->unreadNotifications->markAsRead();
    }

    public function open(string $id): void
    {
        $notification = auth()->user()?->notifications()->find($id);

        if ($notification === null) {
            return;
        }

        $notification->markAsRead();

        $this->redirect($notification->data['url'] ?? route('notifications.index'), navigate: true);
    }

    public function render(): View
    {
        $user = auth()->user();

        return view('livewire.notifications.bell', [
            'unreadCount' => $user->unreadNotifications()->count(),
            'recent' => $user->notifications()->limit(8)->get(),
        ]);
    }
}
