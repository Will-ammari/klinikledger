<?php

namespace Database\Factories;

use App\Models\Clinic;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class DoctorFactory extends Factory
{
    public function definition(): array
    {
        return [
            'clinic_id' => Clinic::factory(),
            'user_id' => null,
            'specialization' => fake()->randomElement([
                'General Medicine',
                'Internal Medicine',
                'Cardiology',
                'Dermatology',
                'Pediatrics',
            ]),
            'appointment_duration_minutes' => fake()->randomElement([15, 20, 30, 45, 60]),
            'is_active' => true,
        ];
    }

    public function forClinic(Clinic $clinic): static
    {
        return $this->state(fn () => [
            'clinic_id' => $clinic->id,
        ]);
    }

    public function linkedToUser(User $user): static
    {
        return $this->state(fn () => [
            'clinic_id' => $user->clinic_id,
            'user_id' => $user->id,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => [
            'is_active' => false,
        ]);
    }
}
