<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ClinicFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->company() . ' Praxis';

        return [
            'name' => $name,
            'slug' => Str::slug($name) . '-' . fake()->unique()->lexify('????'),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->phoneNumber(),
            'address' => fake()->streetAddress(),
            'city' => 'Berlin',
            'country' => 'Germany',
            'timezone' => 'Europe/Berlin',
        ];
    }
}
