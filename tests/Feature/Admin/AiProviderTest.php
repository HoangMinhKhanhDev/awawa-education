<?php

namespace Tests\Feature\Admin;

use App\Livewire\Admin\AiProviders\Index as AdminAiProviders;
use App\Models\AiProvider;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class AiProviderTest extends TestCase
{
    use RefreshDatabase;

    private function fakeModelsOk(): void
    {
        Http::fake([
            '*/models' => Http::response([
                'data' => [
                    ['id' => 'openrouter/free', 'name' => 'Free', 'pricing' => ['prompt' => '0', 'completion' => '0']],
                    ['id' => 'openai/gpt-4o-mini', 'name' => 'GPT-4o mini', 'pricing' => ['prompt' => '0.0001', 'completion' => '0.0002']],
                ],
            ], 200),
        ]);
    }

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
        $this->fakeModelsOk();

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
        $this->assertSame('https://openrouter.ai/api/v1', $provider->base_url);
    }

    public function test_quick_setup_replaces_existing_key_without_duplicate(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin);
        $this->fakeModelsOk();

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

    public function test_preset_sets_endpoint_automatically_without_admin_input(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin);

        Livewire::test(AdminAiProviders::class)
            ->call('openQuickCreate', 'openrouter')
            ->assertSet('baseUrl', 'https://openrouter.ai/api/v1');
    }

    public function test_custom_preset_has_empty_endpoint_field(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin);

        Livewire::test(AdminAiProviders::class)
            ->call('openQuickCreate', 'custom')
            ->assertSet('baseUrl', '');
    }

    public function test_quick_setup_agnes_uses_real_endpoint_and_picks_model(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin);

        Http::fake([
            '*/models' => Http::response([
                'data' => [
                    ['id' => 'agnes-2.0-flash', 'name' => 'Agnes 2.0 Flash'],
                    ['id' => 'agnes-2.5-flash', 'name' => 'Agnes 2.5 Flash'],
                ],
            ], 200),
        ]);

        Livewire::test(AdminAiProviders::class)
            ->set('quickPreset', 'agnes')
            ->set('quickKey', 'agnes-key-12345678')
            ->call('quickSave')
            ->assertHasNoErrors();

        $provider = AiProvider::query()->where('key', 'agnes')->firstOrFail();

        $this->assertSame('https://apihub.agnes-ai.com/v1', $provider->base_url);
        $this->assertSame('agnes-2.0-flash', $provider->default_model);
    }

    public function test_check_provider_surfaces_connection_error_without_crashing(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin);

        Http::fake(function (): void {
            throw new ConnectionException('SSL certificate problem');
        });

        $provider = AiProvider::create([
            'key' => 'openrouter',
            'label' => 'OpenRouter',
            'base_url' => 'https://openrouter.ai/api/v1',
            'api_key' => 'sk-test-12345678',
            'is_enabled' => true,
        ]);

        Livewire::test(AdminAiProviders::class)
            ->call('checkProvider', $provider->id)
            ->assertSee('Lỗi');
    }

    public function test_admin_can_open_api_keys_page(): void
    {
        $admin = User::factory()->superAdmin()->create();

        $this->actingAs($admin)->get(route('admin.api-keys'))->assertOk()->assertSee('Nhà cung cấp AI');
    }

    public function test_check_provider_reports_models(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin);
        $this->fakeModelsOk();

        $provider = AiProvider::create([
            'key' => 'openrouter',
            'label' => 'OpenRouter',
            'base_url' => 'https://openrouter.ai/api/v1',
            'api_key' => 'sk-test-12345678',
            'is_enabled' => true,
        ]);

        Livewire::test(AdminAiProviders::class)
            ->call('checkProvider', $provider->id)
            ->assertSee('kết nối thành công');
    }

    public function test_check_provider_reports_error_on_bad_key(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin);

        Http::fake([
            '*/models' => Http::response(['error' => ['message' => 'Invalid key']], 401),
            '*/chat/completions' => Http::response(['error' => ['message' => 'Invalid key']], 401),
        ]);

        $provider = AiProvider::create([
            'key' => 'openrouter',
            'label' => 'OpenRouter',
            'base_url' => 'https://openrouter.ai/api/v1',
            'api_key' => 'bad-key-12345678',
            'is_enabled' => true,
        ]);

        Livewire::test(AdminAiProviders::class)
            ->call('checkProvider', $provider->id)
            ->assertSee('Lỗi');
    }

    public function test_fetch_form_models_populates_model_list(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin);
        $this->fakeModelsOk();

        $component = Livewire::test(AdminAiProviders::class)
            ->call('openQuickCreate', 'openrouter')
            ->set('apiKey', 'sk-test-12345678')
            ->call('fetchFormModels')
            ->assertHasNoErrors();

        $models = $component->get('modelList');

        $this->assertCount(2, $models);
        $this->assertTrue($models[0]['free']);
        $this->assertNotNull($component->get('probeMessage'));
    }

    public function test_save_with_probe_failure_still_persists_key(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin);

        Http::fake([
            '*/models' => Http::response(['error' => ['message' => 'nope']], 500),
            '*/chat/completions' => Http::response(['error' => ['message' => 'nope']], 500),
        ]);

        Livewire::test(AdminAiProviders::class)
            ->set('quickPreset', 'openrouter')
            ->set('quickKey', 'sk-bad-12345678')
            ->call('quickSave')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('ai_providers', ['key' => 'openrouter', 'is_enabled' => true]);
    }
}
