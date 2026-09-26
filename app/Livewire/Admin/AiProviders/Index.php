<?php

namespace App\Livewire\Admin\AiProviders;

use App\Enums\AiPurpose;
use App\Models\AiProvider;
use App\Services\Ai\AiManager;
use App\Services\Ai\AiProviderProbe;
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

    /**
     * @var array<int, array{id: string, name: string, free: bool}>
     */
    public array $modelList = [];

    public ?string $probeMessage = null;

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
                'base_url' => 'https://apihub.agnes-ai.com/v1',
                'model' => '',
                'docs' => 'https://platform.agnes-ai.com/',
                'hint' => 'Chuẩn OpenAI. Tạo key tại platform.agnes-ai.com.',
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
        $base = $this->knownBaseFor($this->quickPreset);

        if ($preset['key'] === '' || blank($base)) {
            $this->openCreate();
            $this->usePreset($this->quickPreset);
            $this->apiKey = $this->quickKey;
            $this->quickKey = '';

            return;
        }

        $provider = AiProvider::query()->firstOrNew(['key' => $preset['key']]);
        $provider->label = $provider->label ?: $preset['label'];
        $provider->base_url = $base;
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

        $probe = app(AiProviderProbe::class)->probe($provider->base_url, $provider->api_key, $provider->default_model);

        if ($probe['ok']) {
            $picked = $this->pickModel($provider->default_model, $probe['models']);

            if ($picked !== null) {
                $provider->forceFill(['default_model' => $picked])->save();
            }

            $suffix = $probe['models'] !== [] ? ' ('.count($probe['models']).' model)' : '';
            session()->flash('status', 'Đã lưu và kết nối OK cho '.$provider->label.$suffix.'.');
        } else {
            session()->flash('error', 'Đã lưu key nhưng kiểm tra thất bại: '.$probe['error']);
        }
    }

    /**
     * Chọn model: giữ model hiện tại nếu có trong danh sách, ngược lại lấy model đầu (ưu tiên miễn phí).
     *
     * @param  array<int, array{id: string, name: string, free: bool}>  $models
     */
    protected function pickModel(?string $current, array $models): ?string
    {
        if ($models === []) {
            return $current;
        }

        $ids = array_column($models, 'id');

        if (filled($current) && in_array($current, $ids, true)) {
            return $current;
        }

        return $models[0]['id'];
    }

    public function checkProvider(int $id, AiProviderProbe $probeService): void
    {
        $provider = AiProvider::query()->findOrFail($id);
        Gate::authorize('update', $provider);

        $result = $probeService->probe($provider->base_url, $provider->api_key, $provider->default_model);

        if (! $result['ok']) {
            session()->flash('error', 'Lỗi '.$provider->label.': '.$result['error']);

            return;
        }

        $count = count($result['models']);

        $picked = $this->pickModel($provider->default_model, $result['models']);

        if ($picked !== null && $picked !== $provider->default_model) {
            $provider->forceFill(['default_model' => $picked])->save();
        }

        session()->flash('status', 'OK — '.$provider->label.' kết nối thành công'.($count > 0 ? " ({$count} model)" : '').'.');
    }

    public function fetchFormModels(AiProviderProbe $probeService): void
    {
        $this->resetErrorBag();
        $this->probeMessage = null;

        $existingKey = $this->editingId !== null
            ? AiProvider::query()->find($this->editingId)?->api_key
            : null;

        $key = filled($this->apiKey) ? $this->apiKey : $existingKey;

        $result = $probeService->probe($this->baseUrl ?: null, $key, $this->defaultModel ?: null);
        $this->modelList = $result['models'];

        if ($result['ok']) {
            $this->probeMessage = $result['models'] !== []
                ? 'Kết nối OK — tìm thấy '.count($result['models']).' model.'
                : 'Kết nối OK (nhà cung cấp không liệt kê model).';

            return;
        }

        $this->addError('apiKey', 'Không kết nối được: '.$result['error']);
    }

    public function usePreset(string $presetKey): void
    {
        $preset = $this->presets()[$presetKey] ?? null;

        if ($preset === null) {
            return;
        }

        $this->key = $preset['key'];
        $this->label = $preset['label'];
        $this->baseUrl = (string) ($this->knownBaseFor($presetKey) ?? '');
        $this->defaultModel = $preset['model'];
        $this->isEnabled = true;
        $this->resetErrorBag();
    }

    /**
     * Endpoint tự suy ra theo dịch vụ (không cần admin nhập).
     */
    public function knownBaseFor(string $presetKey): ?string
    {
        $preset = $this->presets()[$presetKey] ?? null;

        if ($preset === null) {
            return null;
        }

        if (filled($preset['base_url'])) {
            return $preset['base_url'];
        }

        if ($preset['key'] === '') {
            return null;
        }

        return config("awawa.ai.providers.{$preset['key']}.base_url") ?: null;
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
        $this->modelList = [];
        $this->probeMessage = null;
        $this->showForm = true;
        $this->resetErrorBag();

        if (filled($provider->base_url) && filled($provider->api_key)) {
            $result = app(AiProviderProbe::class)->probe($provider->base_url, $provider->api_key, $provider->default_model);

            if ($result['ok'] && $result['models'] !== []) {
                $this->modelList = $result['models'];
                $this->probeMessage = 'Đã tải '.count($result['models']).' model từ nhà cung cấp.';
            }
        }
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
        $this->modelList = [];
        $this->probeMessage = null;
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
