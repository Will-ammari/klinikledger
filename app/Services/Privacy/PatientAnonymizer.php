<?php

namespace App\Services\Privacy;

use App\Models\Patient;

class PatientAnonymizer
{
    public function anonymize(Patient $patient): Patient
    {
        if ($patient->isAnonymized()) {
            return $patient;
        }

        $patient->forceFill([
            'first_name' => 'Anonymous',
            'last_name' => 'Patient',
            'email' => $this->anonymousEmail($patient),
            'phone' => null,
            'date_of_birth' => null,
            'address' => null,
            'city' => null,
            'anonymized_at' => now(),
        ])->save();

        return $patient->fresh();
    }

    private function anonymousEmail(Patient $patient): string
    {
        return sprintf(
            'anonymized_patient_%d_%d@example.invalid',
            $patient->clinic_id,
            $patient->id
        );
    }
}
