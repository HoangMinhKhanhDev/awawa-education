<?php

namespace App\Models;

use App\Enums\MembershipStatus;
use App\Enums\Role;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'subject_id',
        'google_id',
        'avatar',
        'must_change_password',
        'last_login_at',
        'is_active',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => Role::class,
            'must_change_password' => 'boolean',
            'last_login_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function avatarUrl(): ?string
    {
        if (blank($this->avatar)) {
            return null;
        }

        if (str_starts_with($this->avatar, 'http://') || str_starts_with($this->avatar, 'https://')) {
            return $this->avatar;
        }

        return Storage::disk('public')->url($this->avatar);
    }

    public function initials(): string
    {
        return Str::upper(Str::substr(trim($this->name), 0, 1)) ?: '?';
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function teacherProfile(): HasOne
    {
        return $this->hasOne(TeacherProfile::class);
    }

    public function studentProfile(): HasOne
    {
        return $this->hasOne(StudentProfile::class);
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(TeamMembership::class, 'student_id');
    }

    public function activeMembership(): HasOne
    {
        return $this->hasOne(TeamMembership::class, 'student_id')
            ->where('status', MembershipStatus::Active->value);
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === Role::SuperAdmin;
    }

    public function isTeacher(): bool
    {
        return $this->role === Role::Teacher;
    }

    public function isStudent(): bool
    {
        return $this->role === Role::Student;
    }

    public function hasRole(Role|string ...$roles): bool
    {
        $values = array_map(
            fn (Role|string $role) => $role instanceof Role ? $role->value : $role,
            $roles,
        );

        return in_array($this->role?->value, $values, true);
    }

    /**
     * Người dùng có được phép truy cập dữ liệu của môn này không.
     */
    public function canAccessSubject(?int $subjectId): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        if ($subjectId === null || $this->subject_id === null) {
            return false;
        }

        return $this->subject_id === $subjectId;
    }

    /**
     * Học sinh đã chính thức thuộc đội tuyển của môn mình chưa.
     */
    public function isActiveMemberOf(?int $subjectId): bool
    {
        if ($subjectId === null || $this->subject_id !== $subjectId) {
            return false;
        }

        return $this->memberships()
            ->where('subject_id', $subjectId)
            ->where('status', MembershipStatus::Active->value)
            ->exists();
    }
}
