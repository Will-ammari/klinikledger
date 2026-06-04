<?php

namespace Database\Factories;

use App\Enums\PatientStatus;
use App\Models\Clinic;
use Illuminate\Database\Eloquent\Factories\Factory;

class PatientFactory extends Factory
{
    public function definition(): array
    {
        return [
            'clinic_id' => Clinic::factory(),
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->phoneNumber(),
            'date_of_birth' => fake()->dateTimeBetween('-85 years', '-18 years')->format('Y-m-d'),
            'status' => PatientStatus::Active,
            'address' => fake()->streetAddress(),
            'city' => 'Berlin',
            'anonymized_at' => null,
        ];
    }

    public function forClinic(Clinic $clinic): static
    {
        return $this->state(fn () => [
            'clinic_id' => $clinic->id,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => [
            'status' => PatientStatus::Inactive,
        ]);
    }

    public function anonymized(): static
    {
        return $this->state(fn () => [
            'first_name' => 'Anonymous',
            'last_name' => 'Patient',
            'email' => fake()->unique()->numerify('anonymized_patient_####@example.invalid'),
            'phone' => null,
            'date_of_birth' => null,
            'address' => null,
            'city' => null,
            'anonymized_at' => now(),
        ]);
    }
}
