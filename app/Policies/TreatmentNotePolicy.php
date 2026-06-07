<?php

namespace App\Policies;

use App\Enums\TreatmentNoteVisibility;
use App\Enums\UserRole;
use App\Models\Appointment;
use App\Models\TreatmentNote;
use App\Models\User;

class TreatmentNotePolicy
{
    public function viewAnyForAppointment(User $user, Appointment $appointment): bool
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

    public function createForAppointment(User $user, Appointment $appointment): bool
    {
        if ($user->clinic_id !== $appointment->clinic_id) {
            return false;
        }

        return $user->role === UserRole::Doctor
            && $appointment->doctor?->user_id === $user->id;
    }

    public function view(User $user, TreatmentNote $treatmentNote): bool
    {
        if ($user->clinic_id !== $treatmentNote->clinic_id) {
            return false;
        }

        if (
            $user->role === UserRole::OwnerClinic
            && $treatmentNote->visibility === TreatmentNoteVisibility::ClinicOwner
        ) {
            return true;
        }

        return $user->role === UserRole::Doctor
            && $treatmentNote->doctor?->user_id === $user->id;
    }

    public function update(User $user, TreatmentNote $treatmentNote): bool
    {
        return $user->clinic_id === $treatmentNote->clinic_id
            && $user->role === UserRole::Doctor
            && $treatmentNote->doctor?->user_id === $user->id;
    }

    public function delete(User $user, TreatmentNote $treatmentNote): bool
    {
        return $this->update($user, $treatmentNote);
    }
}
