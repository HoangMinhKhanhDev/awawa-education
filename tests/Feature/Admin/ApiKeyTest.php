<?php

namespace Tests\Feature\Admin;

use App\Livewire\Admin\ApiKeys\Index as AdminApiKeys;
use App\Models\ApiKey;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ApiKeyTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_api_key_and_sees_plaintext_once(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin);

        $component = Livewire::test(AdminApiKeys::class)
            ->call('openCreate')
            ->set('name', 'Ứng dụng điểm danh')
            ->set('rateLimitPerMinute', 30)
            ->call('create')
            ->assertHasNoErrors();

        $plain = $component->get('generatedKey');

        $this->assertIsString($plain);
        $this->assertStringStartsWith('awawa_', $plain);
        $this->assertSame(1, ApiKey::query()->count());

        $key = ApiKey::query()->first();
        $this->assertSame(ApiKey::hashKey($plain), $key->key_hash);
        $this->assertSame(30, $key->rate_limit_per_minute);
        $this->assertSame($admin->id, $key->created_by);
    }

    public function test_revoked_key_is_not_usable(): void
    {
        $key = ApiKey::factory()->create(['revoked_at' => now(), 'is_active' => false]);

        $this->assertFalse($key->isUsable());
        $this->assertSame(0, ApiKey::query()->usable()->count());
    }

    public function test_expired_key_is_not_usable(): void
    {
        $key = ApiKey::factory()->create(['expires_at' => now()->subDay()]);

        $this->assertTrue($key->isExpired());
        $this->assertFalse($key->isUsable());
    }

    public function test_non_admin_cannot_open_api_key_page(): void
    {
        $teacher = User::factory()->teacher()->create();

        $this->actingAs($teacher)->get(route('admin.api-keys'))->assertForbidden();
    }
}
