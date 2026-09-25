<?php

namespace App\Policies;

use App\Models\Exam;
use App\Models\User;
use App\Policies\Concerns\AuthorizesSubjectContent;

class ExamPolicy
{
    use AuthorizesSubjectContent;

    public function viewAny(User $auth): bool
    {
        return $auth->isSuperAdmin() || $auth->isTeacher();
    }

    public function view(User $auth, Exam $exam): bool
    {
        return $this->canManage($auth, $exam->subject_id);
    }

    public function create(User $auth): bool
    {
        return $this->canManage($auth, $auth->subject_id);
    }

    public function update(User $auth, Exam $exam): bool
    {
        return $this->canManage($auth, $exam->subject_id);
    }

    public function delete(User $auth, Exam $exam): bool
    {
        return $this->canManage($auth, $exam->subject_id);
    }

    public function publish(User $auth, Exam $exam): bool
    {
        return $this->canManage($auth, $exam->subject_id);
    }
}
