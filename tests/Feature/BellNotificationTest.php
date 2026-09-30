<?php

use App\Livewire\Notifications\Bell;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;
use Tests\Fixtures\DemoNotification;
use Tests\TestCase;

class BellNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::clear();
    }

    /**
     * Host dùng file cache nên giá trị bị serialize. Cached ở đây cũng vậy, để
     * test bắt được lỗi unserialize ra `__PHP_Incomplete_Class` — trước đây cache
     * lưu thẳng model Eloquent nên lần đọc thứ hai là object hỏng, view đọc
     * `->read_at` vỡ thành lỗi 500.
     */
    private function useSerializingCache(): void
    {
        config()->set('cache.default', 'file');
        Cache::clear();
    }

    public function test_it_lists_notifications_after_reading_from_cache(): void
    {
        $this->useSerializingCache();

        $user = User::factory()->create();
        $user->notify(new DemoNotification('Bài đã chấm', 'Điểm của em là 8'));

        // Lần đầu ghi cache, lần sau đọc lại từ cache.
        Livewire::actingAs($user)->test(Bell::class)->assertOk();
        Livewire::actingAs($user)->test(Bell::class)
            ->assertOk()
            ->assertSee('Bài đã chấm')
            ->assertSee('Điểm của em là 8');
    }

    public function test_it_renders_when_there_are_no_notifications(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)->test(Bell::class)
            ->assertOk()
            ->assertSee('Chưa có thông báo nào.');
    }

    public function test_marking_all_read_clears_the_unread_count(): void
    {
        $user = User::factory()->create();
        $user->notify(new DemoNotification('Thông báo', 'Nội dung'));

        Livewire::actingAs($user)->test(Bell::class)->call('markAllRead')->assertOk();

        $this->assertSame(0, $user->unreadNotifications()->count());
    }
}
