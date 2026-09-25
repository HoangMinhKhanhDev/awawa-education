<?php

namespace App\Policies;

use App\Models\ApiKey;
use App\Models\User;

class ApiKeyPolicy
{
    public function viewAny(User $auth): bool
    {
        return $auth->isSuperAdmin();
    }

    public function view(User $auth, ApiKey $apiKey): bool
    {
        return $auth->isSuperAdmin();
    }

    public function create(User $auth): bool
    {
        return $auth->isSuperAdmin();
    }

    public function update(User $auth, ApiKey $apiKey): bool
    {
        return $auth->isSuperAdmin();
    }

    public function delete(User $auth, ApiKey $apiKey): bool
    {
        return $auth->isSuperAdmin();
    }
}
