<?php

namespace App\Livewire\Notifications;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Cache;
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

        // Layout render Bell 2 lần (desktop + mobile) mỗi trang. Cache count
        // 30 giây để lần 2 không phải COUNT lại; recent giữ tươi theo request.
        $unreadCount = $user
            ? Cache::remember(
                'bell-unread:'.$user->id,
                30,
                fn (): int => $user->unreadNotifications()->count(),
            )
            : 0;

        return view('livewire.notifications.bell', [
            'unreadCount' => $unreadCount,
            'recent' => $user->notifications()->limit(5)->get(),
        ]);
    }
}
