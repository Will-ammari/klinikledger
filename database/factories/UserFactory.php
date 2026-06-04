<?php

namespace Database\Factories;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Clinic;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'clinic_id' => Clinic::factory(),
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'role' => UserRole::Receptionist,
            'status' => UserStatus::Active,
            'remember_token' => Str::random(10),
        ];
    }

    public function owner(): static
    {
        return $this->state(fn () => [
            'role' => UserRole::OwnerClinic,
        ]);
    }

    public function doctor(): static
    {
        return $this->state(fn () => [
            'role' => UserRole::Doctor,
        ]);
    }

    public function receptionist(): static
    {
        return $this->state(fn () => [
            'role' => UserRole::Receptionist,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => [
            'status' => UserStatus::Inactive,
        ]);
    }
}
