<?php

namespace Database\Seeders;

use App\Models\AiProvider;
use Illuminate\Database\Seeder;

class AiProviderSeeder extends Seeder
{
    public function run(): void
    {
        $providers = [
            [
                'key' => 'openrouter',
                'label' => 'OpenRouter',
                'base_url' => config('awawa.ai.providers.openrouter.base_url'),
                'api_key' => config('awawa.ai.providers.openrouter.api_key'),
                'default_model' => config('awawa.ai.providers.openrouter.model'),
                'is_default' => true,
                'order' => 0,
            ],
            [
                'key' => 'agnes',
                'label' => 'Agnes AI',
                'base_url' => config('awawa.ai.providers.agnes.base_url'),
                'api_key' => config('awawa.ai.providers.agnes.api_key'),
                'default_model' => config('awawa.ai.providers.agnes.model'),
                'is_default' => false,
                'order' => 1,
            ],
        ];

        foreach ($providers as $data) {
            $provider = AiProvider::query()->firstOrNew(['key' => $data['key']]);

            $provider->label = $data['label'];
            $provider->base_url = $provider->base_url ?: ($data['base_url'] ?: null);
            $provider->default_model = $provider->default_model ?: ($data['default_model'] ?: null);
            $provider->order = $data['order'];

            // Chỉ nạp key từ .env khi chưa có key trong DB (không ghi đè key do admin nhập).
            if (blank($provider->api_key) && filled($data['api_key'])) {
                $provider->api_key = $data['api_key'];
            }

            if (! $provider->exists) {
                $provider->is_enabled = true;
                $provider->is_default = false;
            }

            $provider->save();
        }

        if (! AiProvider::query()->where('is_default', true)->exists()) {
            AiProvider::query()->where('key', 'openrouter')->update(['is_default' => true]);
        }
    }
}
