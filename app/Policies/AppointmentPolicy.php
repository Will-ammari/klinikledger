<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Appointment;
use App\Models\User;

class AppointmentPolicy
{
    public function viewAny(User $user): bool
    {
        return in_array($user->role, [
            UserRole::OwnerClinic,
            UserRole::Receptionist,
            UserRole::Doctor,
        ], true);
    }

    public function view(User $user, Appointment $appointment): bool
    {
        if ($user->clinic_id !== $appointment->clinic_id) {
            return false;
        }

        if (in_array($user->role, [
            UserRole::OwnerClinic,
            UserRole::Receptionist,
        ], true)) {
            return true;
        }

        return $user->role === UserRole::Doctor
            && $appointment->doctor?->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return in_array($user->role, [
            UserRole::OwnerClinic,
            UserRole::Receptionist,
        ], true);
    }

    public function update(User $user, Appointment $appointment): bool
    {
        return $user->clinic_id === $appointment->clinic_id
            && in_array($user->role, [
                UserRole::OwnerClinic,
                UserRole::Receptionist,
            ], true);
    }

    public function delete(User $user, Appointment $appointment): bool
    {
        return false;
    }

    public function confirm(User $user, Appointment $appointment): bool
    {
        return $this->update($user, $appointment);
    }

    public function cancel(User $user, Appointment $appointment): bool
    {
        if ($user->clinic_id !== $appointment->clinic_id) {
            return false;
        }

        if (in_array($user->role, [
            UserRole::OwnerClinic,
            UserRole::Receptionist,
        ], true)) {
            return true;
        }

        return $user->role === UserRole::Doctor
            && $appointment->doctor?->user_id === $user->id;
    }

    public function complete(User $user, Appointment $appointment): bool
    {
        if ($user->clinic_id !== $appointment->clinic_id) {
            return false;
        }

        if ($user->role === UserRole::OwnerClinic) {
            return true;
        }

        return $user->role === UserRole::Doctor
            && $appointment->doctor?->user_id === $user->id;
    }

    public function markNoShow(User $user, Appointment $appointment): bool
    {
        return $this->complete($user, $appointment);
    }

    public function reschedule(User $user, Appointment $appointment): bool
    {
        return $this->update($user, $appointment);
    }
}
