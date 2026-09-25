<?php

namespace App\Policies;

use App\Models\Subject;
use App\Models\User;

class SubjectPolicy
{
    public function viewAny(User $auth): bool
    {
        return true;
    }

    public function view(User $auth, Subject $subject): bool
    {
        return $auth->canAccessSubject($subject->getKey());
    }

    public function create(User $auth): bool
    {
        return $auth->isSuperAdmin();
    }

    public function update(User $auth, Subject $subject): bool
    {
        return $auth->isSuperAdmin();
    }

    public function delete(User $auth, Subject $subject): bool
    {
        return $auth->isSuperAdmin();
    }

    /**
     * Bật/tắt tính năng cho một môn.
     */
    public function manageFeatures(User $auth, Subject $subject): bool
    {
        return $auth->isSuperAdmin();
    }
}
