<?php

namespace Tests\Feature;

use App\Enums\AuditAction;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\Clinic;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuditLogAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_view_audit_logs(): void
    {
        [$clinic, $owner] = $this->createClinicWithUser(UserRole::OwnerClinic);

        AuditLog::factory()
            ->forClinicAndActor($clinic, $owner)
            ->action(AuditAction::UserCreated)
            ->create([
                'metadata' => [
                    'target_user_id' => $owner->id,
                ],
            ]);

        Sanctum::actingAs($owner);

        $this->getJson('/api/audit-logs')
            ->assertOk()
            ->assertJsonPath('data.0.action', AuditAction::UserCreated->value);
    }

    public function test_receptionist_cannot_view_audit_logs(): void
    {
        [, $receptionist] = $this->createClinicWithUser(UserRole::Receptionist);

        Sanctum::actingAs($receptionist);

        $this->getJson('/api/audit-logs')
            ->assertForbidden();
    }

    private function createClinicWithUser(UserRole $role): array
    {
        $clinic = Clinic::factory()->create([
            'slug' => 'berlin-family-praxis-' . $role->value,
            'timezone' => 'Europe/Berlin',
        ]);

        $userFactory = User::factory()->for($clinic);

        $userFactory = match ($role) {
            UserRole::OwnerClinic => $userFactory->owner(),
            UserRole::Doctor => $userFactory->doctor(),
            UserRole::Receptionist => $userFactory->receptionist(),
        };

        $user = $userFactory->create([
            'name' => 'Test User',
            'email' => $role->value . '@example.com',
        ]);

        return [$clinic, $user];
    }
}
