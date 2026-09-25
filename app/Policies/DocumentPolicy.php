<?php

namespace App\Policies;

use App\Models\Document;
use App\Models\User;
use App\Policies\Concerns\AuthorizesSubjectContent;

class DocumentPolicy
{
    use AuthorizesSubjectContent;

    public function viewAny(User $auth): bool
    {
        return $auth->isSuperAdmin() || $auth->isTeacher();
    }

    public function view(User $auth, Document $document): bool
    {
        return $this->canManage($auth, $document->subject_id);
    }

    public function create(User $auth): bool
    {
        return $this->canManage($auth, $auth->subject_id);
    }

    public function update(User $auth, Document $document): bool
    {
        return $this->canManage($auth, $document->subject_id);
    }

    public function delete(User $auth, Document $document): bool
    {
        return $this->canManage($auth, $document->subject_id);
    }
}
