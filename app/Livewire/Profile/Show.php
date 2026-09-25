<?php

namespace App\Livewire\Profile;

use App\Models\StudentProfile;
use App\Models\TeacherProfile;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('components.layouts.app')]
#[Title('Hồ sơ')]
class Show extends Component
{
    use WithFileUploads;

    public string $name = '';

    public string $phone = '';

    public string $dateOfBirth = '';

    public string $className = '';

    public string $employeeCode = '';

    public string $bio = '';

    public $avatar = null;

    public function mount(): void
    {
        $user = auth()->user();

        $this->name = $user->name;

        if ($user->isStudent()) {
            $profile = $user->studentProfile ?? StudentProfile::query()->firstOrCreate(['user_id' => $user->id]);
            $this->phone = (string) $profile->phone;
            $this->dateOfBirth = $profile->date_of_birth?->format('Y-m-d') ?? '';
            $this->className = (string) $profile->class_name;
        }

        if ($user->isTeacher()) {
            $profile = $user->teacherProfile ?? TeacherProfile::query()->firstOrCreate(['user_id' => $user->id]);
            $this->phone = (string) $profile->phone;
            $this->employeeCode = (string) $profile->employee_code;
            $this->bio = (string) $profile->bio;
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'phone' => ['nullable', 'string', 'max:20'],
            'dateOfBirth' => ['nullable', 'date', 'before:today'],
            'className' => ['nullable', 'string', 'max:60'],
            'employeeCode' => ['nullable', 'string', 'max:60'],
            'bio' => ['nullable', 'string', 'max:500'],
            'avatar' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'name.required' => 'Vui lòng nhập họ tên.',
            'avatar.image' => 'Ảnh đại diện phải là tệp hình ảnh.',
            'avatar.max' => 'Ảnh đại diện tối đa 2MB.',
        ];
    }

    public function save(): void
    {
        $this->validate();

        $user = auth()->user();
        $user->name = $this->name;

        if ($this->avatar !== null) {
            if ($user->avatar !== null && ! str_starts_with($user->avatar, 'http')) {
                Storage::disk('public')->delete($user->avatar);
            }

            $user->avatar = $this->avatar->store("avatars/{$user->id}", 'public');
        }

        $user->save();

        if ($user->isStudent()) {
            $user->studentProfile()->updateOrCreate(
                ['user_id' => $user->id],
                [
                    'phone' => $this->phone ?: null,
                    'date_of_birth' => $this->dateOfBirth ?: null,
                    'class_name' => $this->className ?: null,
                ],
            );
        }

        if ($user->isTeacher()) {
            $user->teacherProfile()->updateOrCreate(
                ['user_id' => $user->id],
                [
                    'phone' => $this->phone ?: null,
                    'employee_code' => $this->employeeCode ?: null,
                    'bio' => $this->bio ?: null,
                ],
            );
        }

        $this->avatar = null;

        session()->flash('status', 'Đã cập nhật hồ sơ.');
    }

    public function removeAvatar(): void
    {
        $user = auth()->user();

        if ($user->avatar !== null && ! str_starts_with($user->avatar, 'http')) {
            Storage::disk('public')->delete($user->avatar);
        }

        $user->forceFill(['avatar' => null])->save();

        session()->flash('status', 'Đã xóa ảnh đại diện.');
    }

    public function render(): View
    {
        return view('livewire.profile.show', [
            'user' => auth()->user(),
        ]);
    }
}
