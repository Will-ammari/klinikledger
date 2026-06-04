<?php

namespace Database\Factories;

use App\Models\Clinic;
use App\Models\Doctor;
use Illuminate\Database\Eloquent\Factories\Factory;

class DoctorTimeOffFactory extends Factory
{
    public function definition(): array
    {
        return [
            'clinic_id' => Clinic::factory(),
            'doctor_id' => Doctor::factory(),
            'starts_at' => now()->addDays(3)->setTime(10, 0),
            'ends_at' => now()->addDays(3)->setTime(11, 0),
            'reason' => 'Private appointment',
        ];
    }

    public function forDoctor(Doctor $doctor): static
    {
        return $this->state(fn () => [
            'clinic_id' => $doctor->clinic_id,
            'doctor_id' => $doctor->id,
        ]);
    }

    public function onMondayMorning(): static
    {
        return $this->state(fn () => [
            'starts_at' => '2026-06-08 10:00:00',
            'ends_at' => '2026-06-08 11:00:00',
            'reason' => 'Private appointment',
        ]);
    }
}
