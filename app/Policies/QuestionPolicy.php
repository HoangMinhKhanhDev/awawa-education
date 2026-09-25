<?php

namespace App\Policies;

use App\Models\Question;
use App\Models\User;
use App\Policies\Concerns\AuthorizesSubjectContent;

class QuestionPolicy
{
    use AuthorizesSubjectContent;

    public function viewAny(User $auth): bool
    {
        return $auth->isSuperAdmin() || $auth->isTeacher();
    }

    public function view(User $auth, Question $question): bool
    {
        return $this->canManage($auth, $question->subject_id);
    }

    public function create(User $auth): bool
    {
        return $this->canManage($auth, $auth->subject_id);
    }

    public function update(User $auth, Question $question): bool
    {
        return $this->canManage($auth, $question->subject_id);
    }

    public function delete(User $auth, Question $question): bool
    {
        return $this->canManage($auth, $question->subject_id);
    }
}
