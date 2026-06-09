<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ListEndpointFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_users_can_be_filtered_by_role(): void
    {
        [$clinic, $owner] = $this->createOwnerScenario();

        $doctorUser = User::factory()
            ->doctor()
            ->for($clinic)
            ->create([
                'name' => 'Dr Filter Role',
                'email' => 'doctor.filter.role@example.com',
            ]);

        User::factory()
            ->receptionist()
            ->for($clinic)
            ->create([
                'name' => 'Reception Filter Role',
                'email' => 'reception.filter.role@example.com',
            ]);

        Sanctum::actingAs($owner);

        $response = $this->getJson('/api/users?role='.UserRole::Doctor->value);

        $response->assertOk();

        $userIds = collect($response->json('data'))->pluck('id')->all();

        $this->assertSame([$doctorUser->id], $userIds);
    }

    public function test_users_can_be_filtered_by_status(): void
    {
        [$clinic, $owner] = $this->createOwnerScenario();

        $inactiveUser = User::factory()
            ->receptionist()
            ->inactive()
            ->for($clinic)
            ->create([
                'name' => 'Inactive User',
                'email' => 'inactive.user@example.com',
            ]);

        User::factory()
            ->receptionist()
            ->for($clinic)
            ->create([
                'name' => 'Active User',
                'email' => 'active.user@example.com',
            ]);

        Sanctum::actingAs($owner);

        $response = $this->getJson('/api/users?status='.UserStatus::Inactive->value);

        $response->assertOk();

        $userIds = collect($response->json('data'))->pluck('id')->all();

        $this->assertSame([$inactiveUser->id], $userIds);
    }

    public function test_users_can_be_searched_by_name_or_email(): void
    {
        [$clinic, $owner] = $this->createOwnerScenario();

        $matchingUser = User::factory()
            ->receptionist()
            ->for($clinic)
            ->create([
                'name' => 'Maya Searchable',
                'email' => 'maya.searchable@example.com',
            ]);

        User::factory()
            ->receptionist()
            ->for($clinic)
            ->create([
                'name' => 'Other User',
                'email' => 'other.user@example.com',
            ]);

        Sanctum::actingAs($owner);

        $response = $this->getJson('/api/users?search=searchable');

        $response->assertOk();

        $userIds = collect($response->json('data'))->pluck('id')->all();

        $this->assertSame([$matchingUser->id], $userIds);
    }

    public function test_per_page_is_capped_at_one_hundred(): void
    {
        [$clinic, $owner] = $this->createOwnerScenario();

        User::factory()
            ->count(120)
            ->receptionist()
            ->for($clinic)
            ->create();

        Sanctum::actingAs($owner);

        $response = $this->getJson('/api/users?per_page=500');

        $response->assertOk()
            ->assertJsonPath('meta.per_page', 100);

        $this->assertCount(100, $response->json('data'));
    }

    public function test_doctors_can_be_filtered_by_specialization(): void
    {
        [$clinic, $owner] = $this->createOwnerScenario();

        $cardiologyDoctor = Doctor::factory()
            ->forClinic($clinic)
            ->create([
                'specialization' => 'Cardiology',
                'is_active' => true,
            ]);

        Doctor::factory()
            ->forClinic($clinic)
            ->create([
                'specialization' => 'Dermatology',
                'is_active' => true,
            ]);

        Sanctum::actingAs($owner);

        $response = $this->getJson('/api/doctors?specialization=cardio');

        $response->assertOk();

        $doctorIds = collect($response->json('data'))->pluck('id')->all();

        $this->assertSame([$cardiologyDoctor->id], $doctorIds);
    }

    public function test_doctors_can_be_filtered_by_active_status(): void
    {
        [$clinic, $owner] = $this->createOwnerScenario();

        $inactiveDoctor = Doctor::factory()
            ->inactive()
            ->forClinic($clinic)
            ->create([
                'specialization' => 'Neurology',
            ]);

        Doctor::factory()
            ->forClinic($clinic)
            ->create([
                'specialization' => 'Pediatrics',
                'is_active' => true,
            ]);

        Sanctum::actingAs($owner);

        $response = $this->getJson('/api/doctors?is_active=0');

        $response->assertOk();

        $doctorIds = collect($response->json('data'))->pluck('id')->all();

        $this->assertSame([$inactiveDoctor->id], $doctorIds);
    }

    public function test_doctors_can_be_searched_by_specialization_or_linked_user(): void
    {
        [$clinic, $owner] = $this->createOwnerScenario();

        $doctorUser = User::factory()
            ->doctor()
            ->for($clinic)
            ->create([
                'name' => 'Dr Lina Search',
                'email' => 'lina.search@example.com',
            ]);

        $matchingDoctor = Doctor::factory()
            ->linkedToUser($doctorUser)
            ->create([
                'specialization' => 'Family Medicine',
                'is_active' => true,
            ]);

        Doctor::factory()
            ->forClinic($clinic)
            ->create([
                'specialization' => 'Orthopedics',
                'is_active' => true,
            ]);

        Sanctum::actingAs($owner);

        $response = $this->getJson('/api/doctors?search=lina');

        $response->assertOk();

        $doctorIds = collect($response->json('data'))->pluck('id')->all();

        $this->assertSame([$matchingDoctor->id], $doctorIds);
    }

    private function createOwnerScenario(): array
    {
        $clinic = Clinic::factory()->create([
            'name' => 'Filter Test Clinic',
            'slug' => 'filter-test-clinic-'.fake()->unique()->numberBetween(1000, 9999),
            'timezone' => 'Europe/Berlin',
        ]);

        $owner = User::factory()
            ->owner()
            ->for($clinic)
            ->create([
                'name' => 'Owner Filter',
                'email' => fake()->unique()->safeEmail(),
            ]);

        return [$clinic, $owner];
    }
}
