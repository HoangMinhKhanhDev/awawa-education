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

    /**
     * Xem trong app: học sinh được xem tài liệu công khai của môn mình, giáo viên
     * thì xem được tài liệu riêng tư của môn đó.
     */
    public function read(User $auth, Document $document): bool
    {
        if ($auth->isSuperAdmin()) {
            return true;
        }

        if ($auth->canAccessSubject($document->subject_id)) {
            return $auth->isTeacher() || $document->is_public;
        }

        return false;
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
