<?php

namespace App\Policies;

use App\Models\ExamAttempt;
use App\Models\User;
use App\Policies\Concerns\AuthorizesSubjectContent;

class ExamAttemptPolicy
{
    use AuthorizesSubjectContent;

    public function viewAny(User $auth): bool
    {
        return $auth->isSuperAdmin() || $auth->isTeacher();
    }

    public function view(User $auth, ExamAttempt $attempt): bool
    {
        if ($auth->id === $attempt->student_id) {
            return true;
        }

        return $this->canManage($auth, $attempt->subject_id);
    }

    public function create(User $auth, ExamAttempt $attempt): bool
    {
        return $auth->id === $attempt->student_id
            && $auth->isStudent()
            && $auth->isActiveMemberOf($attempt->subject_id);
    }

    public function grade(User $auth, ExamAttempt $attempt): bool
    {
        return $this->canManage($auth, $attempt->subject_id);
    }
}
