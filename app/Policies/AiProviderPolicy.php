<?php

namespace App\Policies;

use App\Models\AiProvider;
use App\Models\User;

class AiProviderPolicy
{
    public function viewAny(User $auth): bool
    {
        return $auth->isSuperAdmin();
    }

    public function view(User $auth, AiProvider $provider): bool
    {
        return $auth->isSuperAdmin();
    }

    public function create(User $auth): bool
    {
        return $auth->isSuperAdmin();
    }

    public function update(User $auth, AiProvider $provider): bool
    {
        return $auth->isSuperAdmin();
    }

    public function delete(User $auth, AiProvider $provider): bool
    {
        return $auth->isSuperAdmin();
    }
}
