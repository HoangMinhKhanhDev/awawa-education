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

    public function mount(): void
    {
        Gate::authorize('viewAny', AiProvider::class);
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
        return view('livewire.admin.ai-providers.index', [
            'providers' => AiProvider::query()->orderBy('order')->get(),
        ]);
    }
}
