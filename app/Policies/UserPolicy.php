<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role === UserRole::OwnerClinic;
    }

    public function view(User $user, User $targetUser): bool
    {
        return $user->role === UserRole::OwnerClinic
            && $user->clinic_id === $targetUser->clinic_id;
    }

    public function create(User $user): bool
    {
        return $user->role === UserRole::OwnerClinic;
    }

    public function update(User $user, User $targetUser): bool
    {
        return $user->role === UserRole::OwnerClinic
            && $user->clinic_id === $targetUser->clinic_id;
    }

    public function delete(User $user, User $targetUser): bool
    {
        return $user->role === UserRole::OwnerClinic
            && $user->clinic_id === $targetUser->clinic_id;
    }

    public function changeRole(User $user, User $targetUser): bool
    {
        return $user->role === UserRole::OwnerClinic
            && $user->clinic_id === $targetUser->clinic_id;
    }
}
