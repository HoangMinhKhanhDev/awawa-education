<?php

namespace App\Livewire\Notifications;

use App\Support\SafeCache;
use Illuminate\Contracts\View\View;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Cache;
use Livewire\Component;

class Bell extends Component
{
    public static function forgetCache(int $userId): void
    {
        Cache::forget('bell-unread:'.$userId);
        Cache::forget('bell-recent:'.$userId);
    }

    public function markAllRead(): void
    {
        auth()->user()?->unreadNotifications->markAsRead();

        if (auth()->id() !== null) {
            self::forgetCache(auth()->id());
        }
    }

    public function open(string $id): void
    {
        $notification = auth()->user()?->notifications()->find($id);

        if ($notification === null) {
            return;
        }

        $notification->markAsRead();

        if (auth()->id() !== null) {
            self::forgetCache(auth()->id());
        }

        $url = $notification->data['url'] ?? route('notifications.index');

        if (! is_string($url) || ! str_starts_with($url, '/')) {
            $url = route('notifications.index');
        }

        $this->redirect($url, navigate: true);
    }

    public function render(): View
    {
        $user = auth()->user();

        // Layout render Bell 2 lần (desktop + mobile) mỗi trang. Cache count
        // và list 30 giây để lần 2 không query lại; xóa cache khi đọc xong.
        $unreadCount = $user
            ? SafeCache::remember(
                'bell-unread:'.$user->id,
                30,
                fn (): int => $user->unreadNotifications()->count(),
            )
            : 0;

        $recent = $user
            ? SafeCache::remember(
                'bell-recent:'.$user->id,
                30,
                // Chỉ cache mảng dữ liệu thô. Cache đang serialize, nếu lưu
                // model Eloquent thì lần đọc lại sẽ không còn là model và view
                // đọc `->read_at` sẽ vỡ. Xem `App\Support\SafeCache`.
                fn (): array => $user->notifications()->limit(5)->get()
                    ->map(fn (DatabaseNotification $n): array => [
                        'id' => $n->id,
                        'title' => (string) ($n->data['title'] ?? 'Thông báo'),
                        'body' => (string) ($n->data['body'] ?? ''),
                        'unread' => $n->read_at === null,
                        'created_at' => $n->created_at?->diffForHumans(),
                    ])
                    ->all(),
            )
            : [];

        return view('livewire.notifications.bell', [
            'unreadCount' => $unreadCount,
            'recent' => $recent,
        ]);
    }
}
