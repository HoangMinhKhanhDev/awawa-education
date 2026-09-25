<?php

namespace App\Policies;

use App\Models\Announcement;
use App\Models\User;
use App\Policies\Concerns\AuthorizesSubjectContent;

class AnnouncementPolicy
{
    use AuthorizesSubjectContent;

    public function viewAny(User $auth): bool
    {
        return $auth->isSuperAdmin() || $auth->isTeacher() || $auth->isStudent();
    }

    public function view(User $auth, Announcement $announcement): bool
    {
        if ($auth->isStudent()) {
            return $auth->canAccessSubject($announcement->subject_id);
        }

        return $this->canManage($auth, $announcement->subject_id);
    }

    public function create(User $auth): bool
    {
        return $this->canManage($auth, $auth->subject_id);
    }

    public function update(User $auth, Announcement $announcement): bool
    {
        return $this->canManage($auth, $announcement->subject_id);
    }

    public function delete(User $auth, Announcement $announcement): bool
    {
        return $this->canManage($auth, $announcement->subject_id);
    }
}
