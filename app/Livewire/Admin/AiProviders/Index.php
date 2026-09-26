<?php

namespace App\Livewire\Admin\AiProviders;

use App\Enums\AiPurpose;
use App\Models\AiProvider;
use App\Services\Ai\AiManager;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Nhà cung cấp AI')]
class Index extends Component
{
    public bool $showForm = false;

    public ?int $editingId = null;

    public string $key = '';

    public string $label = '';

    public string $baseUrl = '';

    public string $apiKey = '';

    public string $defaultModel = '';

    public bool $isEnabled = true;

    public bool $isDefault = false;

    public string $quickPreset = 'openrouter';

    public string $quickKey = '';

    public function mount(): void
    {
        Gate::authorize('viewAny', AiProvider::class);
    }

    /**
     * Preset cấu hình nhanh cho các nhà cung cấp phổ biến.
     *
     * @return array<string, array<string, string>>
     */
    public function presets(): array
    {
        return [
            'openrouter' => [
                'key' => 'openrouter',
                'label' => 'OpenRouter',
                'base_url' => 'https://openrouter.ai/api/v1',
                'model' => 'openrouter/free',
                'docs' => 'https://openrouter.ai/keys',
                'hint' => 'Có model miễn phí (openrouter/free). Tạo key tại openrouter.ai/keys.',
            ],
            'agnes' => [
                'key' => 'agnes',
                'label' => 'Agnes AI',
                'base_url' => '',
                'model' => 'agnes-chat',
                'docs' => '',
                'hint' => 'Điền Base URL do Agnes cung cấp (chuẩn OpenAI) và key.',
            ],
            'openai' => [
                'key' => 'openai',
                'label' => 'OpenAI',
                'base_url' => 'https://api.openai.com/v1',
                'model' => 'gpt-4o-mini',
                'docs' => 'https://platform.openai.com/api-keys',
                'hint' => 'Tạo key tại platform.openai.com/api-keys.',
            ],
            'deepseek' => [
                'key' => 'deepseek',
                'label' => 'DeepSeek',
                'base_url' => 'https://api.deepseek.com/v1',
                'model' => 'deepseek-chat',
                'docs' => 'https://platform.deepseek.com/api_keys',
                'hint' => 'Rẻ, mạnh toán/lập trình. Tạo key tại platform.deepseek.com.',
            ],
            'gemini' => [
                'key' => 'gemini',
                'label' => 'Google Gemini',
                'base_url' => 'https://generativelanguage.googleapis.com/v1beta/openai',
                'model' => 'gemini-2.0-flash',
                'docs' => 'https://aistudio.google.com/app/apikey',
                'hint' => 'Dùng endpoint tương thích OpenAI. Tạo key tại Google AI Studio.',
            ],
            'custom' => [
                'key' => '',
                'label' => 'Tùy chỉnh',
                'base_url' => '',
                'model' => '',
                'docs' => '',
                'hint' => 'Tự nhập mã, Base URL và model (chuẩn OpenAI).',
            ],
        ];
    }

    public function quickSave(): void
    {
        Gate::authorize('create', AiProvider::class);

        $this->validate([
            'quickPreset' => ['required', 'string'],
            'quickKey' => ['required', 'string', 'min:8', 'max:500'],
        ], [
            'quickKey.required' => 'Dán API key vào ô bên dưới.',
            'quickKey.min' => 'API key có vẻ quá ngắn.',
        ]);

        $preset = $this->presets()[$this->quickPreset] ?? $this->presets()['custom'];

        if ($preset['key'] === '' || $preset['base_url'] === '') {
            $this->openCreate();
            $this->usePreset($this->quickPreset);
            $this->apiKey = $this->quickKey;
            $this->quickKey = '';

            return;
        }

        $provider = AiProvider::query()->firstOrNew(['key' => $preset['key']]);
        $provider->label = $provider->label ?: $preset['label'];
        $provider->base_url = $preset['base_url'];
        $provider->api_key = $this->quickKey;
        $provider->default_model = $provider->default_model ?: $preset['model'];
        $provider->is_enabled = true;

        if (! $provider->exists) {
            $provider->order = (int) AiProvider::query()->max('order') + 1;
        }

        $provider->save();

        if (! AiProvider::query()->where('is_default', true)->exists()) {
            $provider->forceFill(['is_default' => true])->save();
        }

        $this->quickKey = '';

        session()->flash('status', 'Đã lưu API key cho '.$provider->label.'.');
    }

    public function usePreset(string $presetKey): void
    {
        $preset = $this->presets()[$presetKey] ?? null;

        if ($preset === null) {
            return;
        }

        $this->key = $preset['key'];
        $this->label = $preset['label'];
        $this->baseUrl = $preset['base_url'];
        $this->defaultModel = $preset['model'];
        $this->isEnabled = true;
        $this->resetErrorBag();
    }

    public function openQuickCreate(string $presetKey): void
    {
        Gate::authorize('create', AiProvider::class);

        $this->resetForm();
        $this->usePreset($presetKey);
        $this->showForm = true;
    }

    public function openCreate(): void
    {
        Gate::authorize('create', AiProvider::class);

        $this->resetForm();
        $this->showForm = true;
    }

    public function openEdit(int $id): void
    {
        $provider = AiProvider::query()->findOrFail($id);
        Gate::authorize('update', $provider);

        $this->editingId = $provider->id;
        $this->key = $provider->key;
        $this->label = $provider->label;
        $this->baseUrl = (string) $provider->base_url;
        $this->apiKey = '';
        $this->defaultModel = (string) $provider->default_model;
        $this->isEnabled = $provider->is_enabled;
        $this->isDefault = $provider->is_default;
        $this->showForm = true;
        $this->resetErrorBag();
    }

    public function closeForm(): void
    {
        $this->showForm = false;
        $this->resetForm();
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        $keyRule = Rule::unique(AiProvider::class, 'key');

        if ($this->editingId !== null) {
            $keyRule = $keyRule->ignore($this->editingId);
        }

        return [
            'key' => ['required', 'string', 'max:60', 'regex:/^[a-z0-9-]+$/', $keyRule],
            'label' => ['required', 'string', 'max:120'],
            'baseUrl' => ['nullable', 'url', 'max:255'],
            'apiKey' => ['nullable', 'string', 'max:500'],
            'defaultModel' => ['nullable', 'string', 'max:120'],
            'isEnabled' => ['boolean'],
            'isDefault' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'key.required' => 'Vui lòng nhập mã nhà cung cấp.',
            'key.regex' => 'Mã chỉ gồm chữ thường, số và dấu gạch ngang.',
            'key.unique' => 'Mã nhà cung cấp đã tồn tại.',
            'label.required' => 'Vui lòng nhập tên hiển thị.',
            'baseUrl.url' => 'Địa chỉ API không hợp lệ.',
        ];
    }

    public function save(): void
    {
        $this->validate();

        if ($this->editingId !== null) {
            $provider = AiProvider::query()->findOrFail($this->editingId);
            Gate::authorize('update', $provider);

            $attributes = [
                'key' => $this->key,
                'label' => $this->label,
                'base_url' => $this->baseUrl ?: null,
                'default_model' => $this->defaultModel ?: null,
                'is_enabled' => $this->isEnabled,
            ];

            if (filled($this->apiKey)) {
                $attributes['api_key'] = $this->apiKey;
            }

            $provider->update($attributes);
        } else {
            Gate::authorize('create', AiProvider::class);

            $provider = AiProvider::create([
                'key' => $this->key,
                'label' => $this->label,
                'base_url' => $this->baseUrl ?: null,
                'api_key' => $this->apiKey ?: null,
                'default_model' => $this->defaultModel ?: null,
                'is_enabled' => $this->isEnabled,
                'is_default' => false,
                'order' => (int) AiProvider::query()->max('order') + 1,
            ]);
        }

        if ($this->isDefault) {
            $this->makeDefault($provider);
        }

        $this->showForm = false;
        $this->resetForm();

        session()->flash('status', 'Đã lưu nhà cung cấp AI.');
    }

    public function makeDefault(int $id): void
    {
        $provider = AiProvider::query()->findOrFail($id);
        Gate::authorize('update', $provider);

        AiProvider::query()->whereKeyNot($provider->id)->update(['is_default' => false]);
        $provider->forceFill(['is_default' => true, 'is_enabled' => true])->save();

        session()->flash('status', "Đã đặt {$provider->label} làm nhà cung cấp mặc định.");
    }

    public function testConnection(AiManager $ai): void
    {
        try {
            $result = $ai->chat(
                [
                    ['role' => 'system', 'content' => 'Bạn là trợ lý kiểm tra kết nối.'],
                    ['role' => 'user', 'content' => 'Trả lời đúng một từ: OK'],
                ],
                [
                    'purpose' => AiPurpose::ConnectionTest,
                    'user_id' => auth()->id(),
                    'max_tokens' => 20,
                    'temperature' => 0,
                ],
            );

            session()->flash('status', 'Kết nối thành công qua '.$result->providerKey.' ('.$result->model.').');
        } catch (\Throwable $exception) {
            session()->flash('error', 'Kết nối thất bại: '.$exception->getMessage());
        }
    }

    public function toggleEnabled(int $id): void
    {
        $provider = AiProvider::query()->findOrFail($id);
        Gate::authorize('update', $provider);

        if ($provider->is_default && $provider->is_enabled) {
            session()->flash('error', 'Không thể tắt nhà cung cấp đang là mặc định.');

            return;
        }

        $provider->forceFill(['is_enabled' => ! $provider->is_enabled])->save();

        session()->flash('status', 'Đã cập nhật trạng thái nhà cung cấp.');
    }

    public function delete(int $id): void
    {
        $provider = AiProvider::query()->findOrFail($id);
        Gate::authorize('delete', $provider);

        if ($provider->is_default) {
            session()->flash('error', 'Không thể xóa nhà cung cấp mặc định. Hãy đặt mặc định khác trước.');

            return;
        }

        $provider->delete();

        session()->flash('status', 'Đã xóa nhà cung cấp.');
    }

    protected function resetForm(): void
    {
        $this->editingId = null;
        $this->key = '';
        $this->label = '';
        $this->baseUrl = '';
        $this->apiKey = '';
        $this->defaultModel = '';
        $this->isEnabled = true;
        $this->isDefault = false;
        $this->resetErrorBag();
    }

    public function render(): View
    {
        $providerModels = AiProvider::query()->orderBy('order')->get()->keyBy('key');

        $presets = collect($this->presets())->map(function (array $preset, string $presetKey) use ($providerModels): array {
            $existing = $preset['key'] !== '' ? $providerModels->get($preset['key']) : null;

            return $preset + [
                'preset' => $presetKey,
                'configured' => $existing !== null && filled($existing->api_key) && filled($existing->base_url),
            ];
        })->values();

        return view('livewire.admin.ai-providers.index', [
            'providers' => $providerModels->values(),
            'presets' => $presets,
            'ready' => $providerModels->contains(fn (AiProvider $provider) => $provider->is_enabled && filled($provider->api_key) && filled($provider->base_url)),
        ]);
    }
}
