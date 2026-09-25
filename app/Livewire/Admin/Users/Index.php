<?php

namespace App\Livewire\Admin\Users;

use App\Enums\MembershipStatus;
use App\Enums\Role;
use App\Models\StudentProfile;
use App\Models\Subject;
use App\Models\TeacherProfile;
use App\Models\TeamMembership;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.app')]
#[Title('Quản lý người dùng')]
class Index extends Component
{
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $roleFilter = '';

    #[Url(except: '')]
    public string $subjectFilter = '';

    #[Url(except: '')]
    public string $statusFilter = '';

    public bool $showForm = false;

    public ?int $editingId = null;

    public string $name = '';

    public string $email = '';

    public string $role = 'student';

    public ?int $subjectId = null;

    public bool $isActive = true;

    public string $password = '';

    public bool $addToTeam = false;

    public ?string $generatedPassword = null;

    public function mount(): void
    {
        Gate::authorize('viewAny', User::class);
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingRoleFilter(): void
    {
        $this->resetPage();
    }

    public function updatingSubjectFilter(): void
    {
        $this->resetPage();
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    public function openCreate(): void
    {
        Gate::authorize('create', User::class);

        $this->resetForm();
        $this->showForm = true;
    }

    public function openEdit(int $id): void
    {
        $user = User::query()->findOrFail($id);
        Gate::authorize('update', $user);

        $this->editingId = $user->id;
        $this->name = $user->name;
        $this->email = $user->email;
        $this->role = $user->role->value;
        $this->subjectId = $user->subject_id;
        $this->isActive = $user->is_active;
        $this->password = '';
        $this->addToTeam = $user->isStudent()
            && $user->subject_id !== null
            && $user->isActiveMemberOf($user->subject_id);
        $this->generatedPassword = null;
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
        $emailRule = Rule::unique(User::class, 'email');

        if ($this->editingId !== null) {
            $emailRule = $emailRule->ignore($this->editingId);
        }

        return [
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'email' => ['required', 'string', 'email', 'max:180', $emailRule],
            'role' => ['required', Rule::in(Role::values())],
            'subjectId' => [
                'nullable',
                'integer',
                Rule::exists('subjects', 'id'),
                'required_if:role,'.Role::Teacher->value,
            ],
            'password' => ['nullable', 'string', Password::min(8)->letters()->numbers()],
            'isActive' => ['boolean'],
            'addToTeam' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'name.required' => 'Vui lòng nhập họ tên.',
            'email.required' => 'Vui lòng nhập email.',
            'email.unique' => 'Email này đã được sử dụng.',
            'subjectId.required_if' => 'Giáo viên bắt buộc phải được phân một môn.',
            'subjectId.exists' => 'Môn học không hợp lệ.',
            'password.min' => 'Mật khẩu tối thiểu 8 ký tự, gồm chữ và số.',
        ];
    }

    public function save(): void
    {
        $this->validate();

        $isTeacher = $this->role === Role::Teacher->value;
        $isStudent = $this->role === Role::Student->value;

        $subjectId = $isTeacher
            ? $this->subjectId
            : ($isStudent ? $this->subjectId : null);

        if ($this->editingId !== null) {
            $user = User::query()->findOrFail($this->editingId);
            Gate::authorize('update', $user);

            if ($user->id === auth()->id() && $this->role !== Role::SuperAdmin->value) {
                $this->addError('role', 'Bạn không thể tự hạ quyền của chính mình.');

                return;
            }

            $user->fill([
                'name' => $this->name,
                'email' => mb_strtolower($this->email),
                'role' => $this->role,
                'subject_id' => $subjectId,
                'is_active' => $this->isActive,
            ]);

            if (filled($this->password)) {
                $user->password = $this->password;
                $user->must_change_password = true;
                $this->generatedPassword = $this->password;
            }

            $user->save();
        } else {
            $plainPassword = filled($this->password) ? $this->password : Str::password(12);

            $user = User::create([
                'name' => $this->name,
                'email' => mb_strtolower($this->email),
                'password' => $plainPassword,
                'role' => $this->role,
                'subject_id' => $subjectId,
                'is_active' => $this->isActive,
                'must_change_password' => true,
                'email_verified_at' => now(),
            ]);

            $this->generatedPassword = $plainPassword;
        }

        $this->syncProfiles($user);
        $this->syncTeam($user, $subjectId);

        $this->showForm = false;
        $this->editingId = null;
        $this->resetForm();

        session()->flash('status', 'Đã lưu thông tin người dùng.');
    }

    public function toggleActive(int $id): void
    {
        $user = User::query()->findOrFail($id);
        Gate::authorize('update', $user);

        if ($user->id === auth()->id()) {
            session()->flash('error', 'Bạn không thể khóa tài khoản của chính mình.');

            return;
        }

        $user->forceFill(['is_active' => ! $user->is_active])->save();

        session()->flash('status', $user->is_active ? 'Đã mở khóa tài khoản.' : 'Đã khóa tài khoản.');
    }

    public function resetPassword(int $id): void
    {
        $user = User::query()->findOrFail($id);
        Gate::authorize('update', $user);

        $plainPassword = Str::password(12);

        $user->forceFill([
            'password' => $plainPassword,
            'must_change_password' => true,
        ])->save();

        $this->generatedPassword = $plainPassword;

        session()->flash('status', "Đã đặt lại mật khẩu cho {$user->name}.");
    }

    public function toggleTeam(int $id): void
    {
        $student = User::query()->findOrFail($id);
        Gate::authorize('manageStudents', User::class);

        if (! $student->isStudent() || $student->subject_id === null) {
            session()->flash('error', 'Học sinh cần được phân môn trước khi vào đội tuyển.');

            return;
        }

        $existing = TeamMembership::query()
            ->where('student_id', $student->id)
            ->where('subject_id', $student->subject_id)
            ->first();

        if ($existing !== null && $existing->status === MembershipStatus::Active) {
            $existing->update(['status' => MembershipStatus::Removed]);

            session()->flash('status', 'Đã gỡ học sinh khỏi đội tuyển.');
        } else {
            TeamMembership::updateOrCreate(
                ['student_id' => $student->id, 'subject_id' => $student->subject_id],
                [
                    'status' => MembershipStatus::Active,
                    'joined_at' => now(),
                    'added_by' => auth()->id(),
                ],
            );

            session()->flash('status', 'Đã thêm học sinh vào đội tuyển.');
        }
    }

    public function delete(int $id): void
    {
        $user = User::query()->findOrFail($id);
        Gate::authorize('delete', $user);

        $user->delete();

        session()->flash('status', 'Đã xóa người dùng.');
    }

    protected function syncProfiles(User $user): void
    {
        if ($user->isTeacher()) {
            TeacherProfile::query()->firstOrCreate(['user_id' => $user->id]);
        }

        if ($user->isStudent()) {
            StudentProfile::query()->firstOrCreate(['user_id' => $user->id]);
        }
    }

    protected function syncTeam(User $user, ?int $subjectId): void
    {
        if (! $user->isStudent() || $subjectId === null) {
            return;
        }

        $existing = TeamMembership::query()
            ->where('student_id', $user->id)
            ->where('subject_id', $subjectId)
            ->first();

        if ($this->addToTeam) {
            TeamMembership::updateOrCreate(
                ['student_id' => $user->id, 'subject_id' => $subjectId],
                [
                    'status' => MembershipStatus::Active,
                    'joined_at' => $existing?->joined_at ?? now(),
                    'added_by' => auth()->id(),
                ],
            );

            return;
        }

        if ($existing !== null && $existing->status === MembershipStatus::Active) {
            $existing->update(['status' => MembershipStatus::Removed]);
        }
    }

    protected function resetForm(): void
    {
        $this->name = '';
        $this->email = '';
        $this->role = Role::Student->value;
        $this->subjectId = null;
        $this->isActive = true;
        $this->password = '';
        $this->addToTeam = false;
        $this->resetErrorBag();
    }

    public function render(): View
    {
        $users = User::query()
            ->with('subject')
            ->withCount(['memberships as active_membership_count' => fn ($query) => $query->where('status', MembershipStatus::Active->value)])
            ->when($this->search !== '', function ($query): void {
                $term = '%'.$this->search.'%';
                $query->where(function ($query) use ($term): void {
                    $query->where('name', 'like', $term)->orWhere('email', 'like', $term);
                });
            })
            ->when($this->roleFilter !== '', fn ($query) => $query->where('role', $this->roleFilter))
            ->when($this->subjectFilter !== '', fn ($query) => $query->where('subject_id', $this->subjectFilter))
            ->when($this->statusFilter !== '', fn ($query) => $query->where('is_active', $this->statusFilter === 'active'))
            ->orderByDesc('created_at')
            ->paginate(12);

        return view('livewire.admin.users.index', [
            'users' => $users,
            'subjects' => Subject::query()->orderBy('order')->get(),
            'roles' => Role::cases(),
        ]);
    }
}
