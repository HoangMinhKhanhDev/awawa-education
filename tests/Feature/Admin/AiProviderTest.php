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

    public function test_quick_setup_creates_provider_and_sets_default(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin);

        Livewire::test(AdminAiProviders::class)
            ->set('quickPreset', 'openrouter')
            ->set('quickKey', 'sk-or-test-12345678')
            ->call('quickSave')
            ->assertHasNoErrors();

        $provider = AiProvider::query()->where('key', 'openrouter')->firstOrFail();

        $this->assertTrue($provider->is_enabled);
        $this->assertTrue($provider->is_default);
        $this->assertSame('openrouter/free', $provider->default_model);
        $this->assertSame('sk-or-test-12345678', $provider->api_key);
    }

    public function test_quick_setup_replaces_existing_key_without_duplicate(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin);

        AiProvider::create(['key' => 'openrouter', 'label' => 'OpenRouter', 'base_url' => 'https://openrouter.ai/api/v1', 'api_key' => 'old-key']);

        Livewire::test(AdminAiProviders::class)
            ->set('quickPreset', 'openrouter')
            ->set('quickKey', 'new-key-12345678')
            ->call('quickSave');

        $this->assertSame(1, AiProvider::query()->where('key', 'openrouter')->count());
        $this->assertSame('new-key-12345678', AiProvider::query()->where('key', 'openrouter')->first()->api_key);
    }

    public function test_quick_setup_requires_key(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin);

        Livewire::test(AdminAiProviders::class)
            ->set('quickPreset', 'openrouter')
            ->set('quickKey', '')
            ->call('quickSave')
            ->assertHasErrors('quickKey');
    }

    public function test_custom_preset_opens_form_prefilled(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin);

        Livewire::test(AdminAiProviders::class)
            ->call('openQuickCreate', 'openai')
            ->assertSet('key', 'openai')
            ->assertSet('baseUrl', 'https://api.openai.com/v1')
            ->assertSet('defaultModel', 'gpt-4o-mini')
            ->assertSet('showForm', true);
    }

    public function test_admin_can_open_api_keys_page(): void
    {
        $admin = User::factory()->superAdmin()->create();

        $this->actingAs($admin)->get(route('admin.api-keys'))->assertOk()->assertSee('Thiết lập nhanh');
    }
}
