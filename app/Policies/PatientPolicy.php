<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Patient;
use App\Models\User;

class PatientPolicy
{
    public function viewAny(User $user): bool
    {
        return in_array($user->role, [
            UserRole::OwnerClinic,
            UserRole::Receptionist,
            UserRole::Doctor,
        ], true);
    }

    public function view(User $user, Patient $patient): bool
    {
        if ($user->clinic_id !== $patient->clinic_id) {
            return false;
        }

        if (in_array($user->role, [
            UserRole::OwnerClinic,
            UserRole::Receptionist,
        ], true)) {
            return true;
        }

        if ($user->role === UserRole::Doctor) {
            return $patient->appointments()
                ->whereHas('doctor', function ($query) use ($user) {
                    $query->where('user_id', $user->id);
                })
                ->exists();
        }

        return false;
    }

    public function create(User $user): bool
    {
        return in_array($user->role, [
            UserRole::OwnerClinic,
            UserRole::Receptionist,
        ], true);
    }

    public function update(User $user, Patient $patient): bool
    {
        return $user->clinic_id === $patient->clinic_id
            && in_array($user->role, [
                UserRole::OwnerClinic,
                UserRole::Receptionist,
            ], true);
    }

    public function delete(User $user, Patient $patient): bool
    {
        return $user->clinic_id === $patient->clinic_id
            && in_array($user->role, [
                UserRole::OwnerClinic,
                UserRole::Receptionist,
            ], true);
    }
}
