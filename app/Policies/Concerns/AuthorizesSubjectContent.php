<?php

namespace App\Policies\Concerns;

use App\Models\User;

trait AuthorizesSubjectContent
{
    protected function canManage(User $auth, int|string|null $subjectId): bool
    {
        if ($auth->isSuperAdmin()) {
            return true;
        }

        return $auth->isTeacher()
            && $auth->subject_id !== null
            && $auth->canAccessSubject($subjectId === null ? null : (int) $subjectId);
    }
}
