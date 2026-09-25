<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $auth): bool
    {
        return $auth->isSuperAdmin() || $auth->isTeacher();
    }

    public function view(User $auth, User $target): bool
    {
        if ($auth->id === $target->id) {
            return true;
        }

        if ($auth->isSuperAdmin()) {
            return true;
        }

        return $auth->isTeacher()
            && $target->isStudent()
            && $auth->canAccessSubject($target->subject_id);
    }

    public function create(User $auth): bool
    {
        return $auth->isSuperAdmin();
    }

    /**
     * Giáo viên được quản lý (thêm/xóa khỏi đội) học sinh trong môn của mình.
     */
    public function manageStudents(User $auth): bool
    {
        return $auth->isSuperAdmin() || $auth->isTeacher();
    }

    public function update(User $auth, User $target): bool
    {
        if ($auth->isSuperAdmin()) {
            return true;
        }

        return $auth->isTeacher()
            && $target->isStudent()
            && $auth->canAccessSubject($target->subject_id);
    }

    public function delete(User $auth, User $target): bool
    {
        return $auth->isSuperAdmin() && $auth->id !== $target->id;
    }

    /**
     * Chỉ Super Admin được phân môn / đổi vai trò.
     */
    public function assignRole(User $auth, User $target): bool
    {
        return $auth->isSuperAdmin() && $auth->id !== $target->id;
    }
}
