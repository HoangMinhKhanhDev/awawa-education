<?php

namespace App\Livewire\Notifications;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.app')]
#[Title('Thông báo')]
class Index extends Component
{
    use WithPagination;

    public function markAllRead(): void
    {
        auth()->user()?->unreadNotifications->markAsRead();

        session()->flash('status', 'Đã đánh dấu tất cả là đã đọc.');
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
        return view('livewire.notifications.index', [
            'notifications' => auth()->user()->notifications()->paginate(20),
        ]);
    }
}
