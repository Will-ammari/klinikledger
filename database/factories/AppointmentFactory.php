<?php

namespace Database\Factories;

use App\Enums\AppointmentStatus;
use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\Patient;
use Illuminate\Database\Eloquent\Factories\Factory;

class AppointmentFactory extends Factory
{
    public function definition(): array
    {
        $startsAt = now()->addDays(7)->setTime(9, 0);
        $endsAt = $startsAt->copy()->addMinutes(30);

        return [
            'clinic_id' => Clinic::factory(),
            'doctor_id' => Doctor::factory(),
            'patient_id' => Patient::factory(),
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'status' => AppointmentStatus::Scheduled,
            'reason' => fake()->sentence(4),
            'cancellation_reason' => null,
            'cancelled_at' => null,
            'completed_at' => null,
        ];
    }

    public function forClinicDoctorAndPatient(Clinic $clinic, Doctor $doctor, Patient $patient): static
    {
        return $this->state(fn () => [
            'clinic_id' => $clinic->id,
            'doctor_id' => $doctor->id,
            'patient_id' => $patient->id,
        ]);
    }

    public function scheduledAt(string $startsAt, int $durationMinutes = 30): static
    {
        return $this->state(fn () => [
            'starts_at' => $startsAt,
            'ends_at' => \Carbon\Carbon::parse($startsAt)->addMinutes($durationMinutes),
        ]);
    }

    public function confirmed(): static
    {
        return $this->state(fn () => [
            'status' => AppointmentStatus::Confirmed,
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn () => [
            'status' => AppointmentStatus::Completed,
            'completed_at' => now(),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn () => [
            'status' => AppointmentStatus::Cancelled,
            'cancellation_reason' => 'Patient requested cancellation',
            'cancelled_at' => now(),
        ]);
    }
}
