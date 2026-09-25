<?php

namespace App\Livewire\Teacher;

use App\Enums\MembershipStatus;
use App\Enums\Role;
use App\Models\TeamMembership;
use App\Models\User;
use App\Support\SubjectContext;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Quản lý học sinh')]
class StudentsIndex extends Component
{
    public string $search = '';

    public bool $showCreate = false;

    public string $newName = '';

    public string $newEmail = '';

    public ?string $generatedPassword = null;

    public function mount(): void
    {
        Gate::authorize('manageStudents', User::class);
    }

    public function openCreate(): void
    {
        $this->resetCreateForm();
        $this->showCreate = true;
    }

    public function closeCreate(): void
    {
        $this->showCreate = false;
        $this->resetCreateForm();
    }

    public function addStudent(int $userId): void
    {
        $subjectId = app(SubjectContext::class)->id();

        if ($subjectId === null) {
            session()->flash('error', 'Bạn chưa được phân môn.');

            return;
        }

        $student = User::query()
            ->whereKey($userId)
            ->where('role', Role::Student->value)
            ->firstOrFail();

        if ($student->subject_id !== null && $student->subject_id !== $subjectId) {
            session()->flash('error', 'Học sinh này đang thuộc một môn khác. Mỗi học sinh chỉ thuộc một môn.');

            return;
        }

        $student->forceFill(['subject_id' => $subjectId])->save();

        TeamMembership::query()->updateOrCreate(
            ['student_id' => $student->id, 'subject_id' => $subjectId],
            [
                'status' => MembershipStatus::Active,
                'joined_at' => now(),
                'added_by' => auth()->id(),
            ],
        );

        session()->flash('status', "Đã thêm {$student->name} vào đội tuyển.");
    }

    public function removeStudent(int $userId): void
    {
        $subjectId = app(SubjectContext::class)->id();

        TeamMembership::query()
            ->where('student_id', $userId)
            ->where('subject_id', $subjectId)
            ->where('status', MembershipStatus::Active->value)
            ->update(['status' => MembershipStatus::Removed]);

        session()->flash('status', 'Đã gỡ học sinh khỏi đội tuyển.');
    }

    public function createStudent(): void
    {
        $this->validate([
            'newName' => ['required', 'string', 'min:2', 'max:120'],
            'newEmail' => ['required', 'email', 'max:180', Rule::unique(User::class, 'email')],
        ], [
            'newName.required' => 'Vui lòng nhập họ tên học sinh.',
            'newEmail.required' => 'Vui lòng nhập email.',
            'newEmail.unique' => 'Email này đã được sử dụng.',
        ]);

        $subjectId = app(SubjectContext::class)->id();

        if ($subjectId === null) {
            session()->flash('error', 'Bạn chưa được phân môn.');

            return;
        }

        $plainPassword = Str::password(12);

        $student = User::create([
            'name' => $this->newName,
            'email' => mb_strtolower($this->newEmail),
            'password' => $plainPassword,
            'role' => Role::Student,
            'subject_id' => $subjectId,
            'must_change_password' => true,
            'email_verified_at' => now(),
        ]);

        TeamMembership::create([
            'subject_id' => $subjectId,
            'student_id' => $student->id,
            'status' => MembershipStatus::Active,
            'joined_at' => now(),
            'added_by' => auth()->id(),
        ]);

        $this->generatedPassword = $plainPassword;
        $this->showCreate = false;
        $this->resetCreateForm();

        session()->flash('status', "Đã tạo tài khoản cho {$student->name} và thêm vào đội.");
    }

    protected function resetCreateForm(): void
    {
        $this->newName = '';
        $this->newEmail = '';
        $this->resetErrorBag();
    }

    public function render(): View
    {
        $subjectId = app(SubjectContext::class)->id();
        $subject = app(SubjectContext::class)->subject();

        $members = TeamMembership::query()
            ->active()
            ->with('student')
            ->where('subject_id', $subjectId)
            ->latest('joined_at')
            ->get();

        $memberIds = $members->pluck('student_id')->all();

        $candidates = User::query()
            ->where('role', Role::Student->value)
            ->where(function ($query) use ($subjectId): void {
                $query->whereNull('subject_id')->orWhere('subject_id', $subjectId);
            })
            ->whereNotIn('id', $memberIds)
            ->when($this->search !== '', function ($query): void {
                $term = '%'.$this->search.'%';
                $query->where(function ($query) use ($term): void {
                    $query->where('name', 'like', $term)->orWhere('email', 'like', $term);
                });
            })
            ->orderBy('name')
            ->limit(20)
            ->get();

        return view('livewire.teacher.students-index', [
            'subject' => $subject,
            'members' => $members,
            'candidates' => $candidates,
        ]);
    }
}
