<?php

namespace App\Livewire\Admin\ApiKeys;

use App\Enums\ApiScope;
use App\Models\ApiKey;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('API key')]
class Index extends Component
{
    public bool $showForm = false;

    public string $name = '';

    public int $rateLimitPerMinute = 60;

    public string $expiresAt = '';

    /**
     * @var array<string, bool>
     */
    public array $scopes = [];

    public ?string $generatedKey = null;

    public function mount(): void
    {
        Gate::authorize('viewAny', ApiKey::class);
        $this->scopes = $this->defaultScopes();
    }

    public function openCreate(): void
    {
        Gate::authorize('create', ApiKey::class);

        $this->resetForm();
        $this->showForm = true;
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
        return [
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'rateLimitPerMinute' => ['required', 'integer', 'min:1', 'max:100000'],
            'expiresAt' => ['nullable', 'date', 'after:today'],
            'scopes' => ['array'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'name.required' => 'Vui lòng nhập tên khóa.',
            'rateLimitPerMinute.required' => 'Vui lòng nhập giới hạn tốc độ.',
            'expiresAt.after' => 'Ngày hết hạn phải ở tương lai.',
        ];
    }

    public function create(): void
    {
        $this->validate();

        Gate::authorize('create', ApiKey::class);

        $enabledScopes = array_keys(array_filter($this->scopes));

        [$apiKey, $plain] = ApiKey::issue([
            'name' => $this->name,
            'scopes' => $enabledScopes,
            'rate_limit_per_minute' => $this->rateLimitPerMinute,
            'expires_at' => $this->expiresAt !== '' ? $this->expiresAt : null,
            'created_by' => auth()->id(),
        ]);

        $this->generatedKey = $plain;
        $this->showForm = false;
        $this->resetForm();

        session()->flash('status', "Đã tạo khóa \"{$apiKey->name}\". Hãy sao chép khóa ngay bây giờ.");
    }

    public function toggleActive(int $id): void
    {
        $apiKey = ApiKey::query()->findOrFail($id);
        Gate::authorize('update', $apiKey);

        $apiKey->forceFill(['is_active' => ! $apiKey->is_active])->save();

        session()->flash('status', $apiKey->is_active ? 'Đã kích hoạt khóa.' : 'Đã tạm khóa.');
    }

    public function revoke(int $id): void
    {
        $apiKey = ApiKey::query()->findOrFail($id);
        Gate::authorize('update', $apiKey);

        $apiKey->forceFill(['revoked_at' => now(), 'is_active' => false])->save();

        session()->flash('status', 'Đã thu hồi khóa.');
    }

    public function delete(int $id): void
    {
        $apiKey = ApiKey::query()->findOrFail($id);
        Gate::authorize('delete', $apiKey);

        $apiKey->delete();

        session()->flash('status', 'Đã xóa khóa.');
    }

    protected function resetForm(): void
    {
        $this->name = '';
        $this->rateLimitPerMinute = 60;
        $this->expiresAt = '';
        $this->scopes = $this->defaultScopes();
        $this->resetErrorBag();
    }

    /**
     * @return array<string, bool>
     */
    protected function defaultScopes(): array
    {
        $scopes = [];

        foreach (ApiScope::cases() as $scope) {
            $scopes[$scope->value] = true;
        }

        return $scopes;
    }

    public function render(): View
    {
        return view('livewire.admin.api-keys.index', [
            'keys' => ApiKey::query()->with('creator')->orderByDesc('created_at')->get(),
            'scopeCases' => ApiScope::cases(),
        ]);
    }
}
