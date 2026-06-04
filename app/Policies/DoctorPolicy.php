<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Doctor;
use App\Models\User;

class DoctorPolicy
{
    public function viewAny(User $user): bool
    {
        return in_array($user->role, [
            UserRole::OwnerClinic,
            UserRole::Receptionist,
            UserRole::Doctor,
        ], true);
    }

    public function view(User $user, Doctor $doctor): bool
    {
        return $user->clinic_id === $doctor->clinic_id
            && in_array($user->role, [
                UserRole::OwnerClinic,
                UserRole::Receptionist,
                UserRole::Doctor,
            ], true);
    }

    public function create(User $user): bool
    {
        return $user->role === UserRole::OwnerClinic;
    }

    public function update(User $user, Doctor $doctor): bool
    {
        return $user->role === UserRole::OwnerClinic
            && $user->clinic_id === $doctor->clinic_id;
    }

    public function delete(User $user, Doctor $doctor): bool
    {
        return $user->role === UserRole::OwnerClinic
            && $user->clinic_id === $doctor->clinic_id;
    }
}
