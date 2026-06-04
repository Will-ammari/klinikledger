<?php

namespace Database\Factories;

use App\Enums\AuditAction;
use App\Models\Clinic;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class AuditLogFactory extends Factory
{
    public function definition(): array
    {
        return [
            'clinic_id' => Clinic::factory(),
            'actor_user_id' => User::factory(),
            'action' => AuditAction::PatientViewed,
            'auditable_type' => null,
            'auditable_id' => null,
            'metadata' => [],
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PraxisFlow Test Agent',
        ];
    }

    public function forClinicAndActor(Clinic $clinic, User $actor): static
    {
        return $this->state(fn () => [
            'clinic_id' => $clinic->id,
            'actor_user_id' => $actor->id,
        ]);
    }

    public function action(AuditAction $action): static
    {
        return $this->state(fn () => [
            'action' => $action,
        ]);
    }
}
