<?php

namespace App\Policies;

use App\Enums\MapVisibility;
use App\Models\KnowledgeMap;
use App\Models\User;

class KnowledgeMapPolicy
{
    public function viewAny(User $auth): bool
    {
        return true;
    }

    public function create(User $auth): bool
    {
        return $auth->isSuperAdmin() || $auth->subject_id !== null;
    }

    public function view(User $auth, KnowledgeMap $map): bool
    {
        if ($auth->isSuperAdmin() || $map->isOwnedBy($auth)) {
            return true;
        }

        if (! $auth->canAccessSubject($map->subject_id)) {
            return false;
        }

        if ($map->visibility === MapVisibility::Subject) {
            return true;
        }

        return $auth->isTeacher();
    }

    public function update(User $auth, KnowledgeMap $map): bool
    {
        return $auth->isSuperAdmin() || $map->isOwnedBy($auth);
    }

    public function delete(User $auth, KnowledgeMap $map): bool
    {
        if ($auth->isSuperAdmin() || $map->isOwnedBy($auth)) {
            return true;
        }

        return $auth->isTeacher() && $auth->canAccessSubject($map->subject_id);
    }

    public function share(User $auth, KnowledgeMap $map): bool
    {
        return $auth->isSuperAdmin() || $map->isOwnedBy($auth);
    }
}
