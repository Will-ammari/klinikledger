<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Consent;
use App\Models\Patient;
use App\Models\User;

class ConsentPolicy
{
    public function viewAnyForPatient(User $user, Patient $patient): bool
    {
        return $user->clinic_id === $patient->clinic_id
            && in_array($user->role, [
                UserRole::OwnerClinic,
                UserRole::Receptionist,
            ], true);
    }

    public function createForPatient(User $user, Patient $patient): bool
    {
        return $user->clinic_id === $patient->clinic_id
            && in_array($user->role, [
                UserRole::OwnerClinic,
                UserRole::Receptionist,
            ], true);
    }

    public function withdraw(User $user, Consent $consent): bool
    {
        return $user->clinic_id === $consent->clinic_id
            && $consent->isGranted()
            && in_array($user->role, [
                UserRole::OwnerClinic,
                UserRole::Receptionist,
            ], true);
    }
}
