<?php

namespace Database\Factories;

use App\Models\Clinic;
use App\Models\Doctor;
use Illuminate\Database\Eloquent\Factories\Factory;

class DoctorWorkingHourFactory extends Factory
{
    public function definition(): array
    {
        return [
            'clinic_id' => Clinic::factory(),
            'doctor_id' => Doctor::factory(),
            'day_of_week' => fake()->numberBetween(1, 5),
            'starts_at' => '09:00',
            'ends_at' => '17:00',
            'is_active' => true,
        ];
    }

    public function forDoctor(Doctor $doctor): static
    {
        return $this->state(fn () => [
            'clinic_id' => $doctor->clinic_id,
            'doctor_id' => $doctor->id,
        ]);
    }

    public function mondayMorning(): static
    {
        return $this->state(fn () => [
            'day_of_week' => 1,
            'starts_at' => '09:00',
            'ends_at' => '12:00',
            'is_active' => true,
        ]);
    }

    public function mondayAfternoon(): static
    {
        return $this->state(fn () => [
            'day_of_week' => 1,
            'starts_at' => '13:00',
            'ends_at' => '17:00',
            'is_active' => true,
        ]);
    }
}
