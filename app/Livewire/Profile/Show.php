<?php

namespace App\Livewire\Profile;

use App\Models\ExamAttempt;
use App\Models\StudentProfile;
use App\Models\TeacherProfile;
use App\Models\User;
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

    public string $note = '';

    public $avatar = null;

    public function mount(): void
    {
        $user = auth()->user();

        $this->name = $user->name;

        if ($user->isStudent()) {
            $this->note = (string) $user->note;
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
            'note' => ['nullable', 'string', 'max:200'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function avatarRules(): array
    {
        return [
            'avatar' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'name.required' => 'Vui lòng nhập họ tên.',
            'avatar.required' => 'Vui lòng chọn một ảnh.',
            'avatar.image' => 'Tệp này không phải hình ảnh. Hãy chọn file JPG, PNG hoặc WebP.',
            'avatar.mimes' => 'Ảnh iPhone (HEIC) chưa được hỗ trợ. Hãy chọn file JPG, PNG hoặc WebP.',
            'avatar.max' => 'Ảnh đại diện tối đa 5MB. Hãy chọn ảnh nhẹ hơn.',
        ];
    }

    /**
     * Chọn ảnh xong là lưu ngay, không chờ nút "Lưu hồ sơ".
     */
    public function updatedAvatar(): void
    {
        $this->validateOnly('avatar', $this->avatarRules(), $this->messages());

        $user = auth()->user();

        $this->deleteStoredAvatar($user);

        $user->avatar = $this->avatar->store("avatars/{$user->id}", 'public');
        $user->save();

        $this->avatar = null;

        session()->flash('status', 'Đã cập nhật ảnh đại diện.');
    }

    public function save(): void
    {
        $this->validate();

        $user = auth()->user();
        $user->name = $this->name;

        if ($user->isStudent()) {
            $user->note = $this->note !== '' ? $this->note : null;
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

        $this->resetErrorBag();

        session()->flash('status', 'Đã cập nhật hồ sơ.');
    }

    public function removeAvatar(): void
    {
        $user = auth()->user();

        $this->deleteStoredAvatar($user);

        $user->forceFill(['avatar' => null])->save();

        session()->flash('status', 'Đã xóa ảnh đại diện.');
    }

    protected function deleteStoredAvatar(User $user): void
    {
        if ($user->avatar !== null && ! str_starts_with($user->avatar, 'http')) {
            Storage::disk('public')->delete($user->avatar);
        }
    }

    public function render(): View
    {
        $user = auth()->user();

        $history = $user->isStudent()
            ? ExamAttempt::query()
                ->where('student_id', $user->id)
                ->finished()
                ->with('exam')
                ->orderByDesc('submitted_at')
                ->limit(20)
                ->get()
            : collect();

        $totalScore = $history->sum(fn ($attempt) => (float) $attempt->score);

        return view('livewire.profile.show', [
            'user' => $user,
            'history' => $history,
            'totalScore' => $totalScore,
        ]);
    }
}
