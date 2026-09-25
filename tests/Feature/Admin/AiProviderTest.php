<?php

namespace Tests\Feature\Admin;

use App\Livewire\Admin\AiProviders\Index as AdminAiProviders;
use App\Models\AiProvider;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class AiProviderTest extends TestCase
{
    use RefreshDatabase;

    public function test_making_provider_default_unsets_others(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin);

        $first = AiProvider::create(['key' => 'openrouter', 'label' => 'OpenRouter', 'is_default' => true, 'is_enabled' => true]);
        $second = AiProvider::create(['key' => 'agnes', 'label' => 'Agnes AI', 'is_enabled' => true]);

        Livewire::test(AdminAiProviders::class)->call('makeDefault', $second->id);

        $this->assertFalse($first->fresh()->is_default);
        $this->assertTrue($second->fresh()->is_default);
        $this->assertTrue($second->fresh()->is_enabled);
    }

    public function test_cannot_disable_the_default_provider(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin);

        $provider = AiProvider::create(['key' => 'openrouter', 'label' => 'OpenRouter', 'is_default' => true, 'is_enabled' => true]);

        Livewire::test(AdminAiProviders::class)->call('toggleEnabled', $provider->id);

        $this->assertTrue($provider->fresh()->is_enabled);
    }

    public function test_api_key_is_encrypted_in_database(): void
    {
        $provider = AiProvider::create([
            'key' => 'openrouter',
            'label' => 'OpenRouter',
            'base_url' => 'https://openrouter.ai/api/v1',
            'api_key' => 'secret-key-value',
        ]);

        $raw = DB::table('ai_providers')->where('id', $provider->id)->value('api_key');

        $this->assertNotSame('secret-key-value', $raw);
        $this->assertSame('secret-key-value', $provider->fresh()->api_key);
    }
}
