<?php

namespace Tests\Feature;

use App\Enums\AuditAction;
use App\Enums\ConsentStatus;
use App\Enums\ConsentType;
use App\Models\Clinic;
use App\Models\Consent;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ConsentTest extends TestCase
{
    use RefreshDatabase;

    public function test_receptionist_can_create_patient_consent(): void
    {
        [$clinic, , $receptionist, , $patient] = $this->createConsentScenario();

        Sanctum::actingAs($receptionist);

        $response = $this->postJson("/api/patients/{$patient->id}/consents", [
            'type' => ConsentType::EmailReminders->value,
            'notes' => 'Patient agreed to receive appointment reminders by email.',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.patient_id', $patient->id)
            ->assertJsonPath('data.type', ConsentType::EmailReminders->value)
            ->assertJsonPath('data.status', ConsentStatus::Granted->value);

        $this->assertDatabaseHas('consents', [
            'clinic_id' => $clinic->id,
            'patient_id' => $patient->id,
            'granted_by_user_id' => $receptionist->id,
            'type' => ConsentType::EmailReminders->value,
            'status' => ConsentStatus::Granted->value,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'clinic_id' => $clinic->id,
            'actor_user_id' => $receptionist->id,
            'action' => AuditAction::ConsentCreated->value,
        ]);
    }

    public function test_receptionist_can_withdraw_patient_consent(): void
    {
        [, , $receptionist, , $patient] = $this->createConsentScenario();

        $consent = Consent::create([
            'clinic_id' => $patient->clinic_id,
            'patient_id' => $patient->id,
            'granted_by_user_id' => $receptionist->id,
            'type' => ConsentType::EmailReminders,
            'status' => ConsentStatus::Granted,
            'granted_at' => now(),
        ]);

        Sanctum::actingAs($receptionist);

        $this->postJson("/api/consents/{$consent->id}/withdraw")
            ->assertOk()
            ->assertJsonPath('data.status', ConsentStatus::Withdrawn->value);

        $this->assertDatabaseHas('consents', [
            'id' => $consent->id,
            'status' => ConsentStatus::Withdrawn->value,
            'withdrawn_by_user_id' => $receptionist->id,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'actor_user_id' => $receptionist->id,
            'action' => AuditAction::ConsentWithdrawn->value,
        ]);
    }

    public function test_doctor_cannot_create_or_view_patient_consents(): void
    {
        [, , , $doctorUser, $patient] = $this->createConsentScenario();

        Sanctum::actingAs($doctorUser);

        $this->getJson("/api/patients/{$patient->id}/consents")
            ->assertForbidden();

        $this->postJson("/api/patients/{$patient->id}/consents", [
            'type' => ConsentType::DataProcessing->value,
        ])->assertForbidden();

        $this->assertDatabaseCount('consents', 0);
    }

    public function test_consent_from_another_clinic_cannot_be_withdrawn(): void
    {
        [, , $receptionist] = $this->createConsentScenario();

        $otherClinic = Clinic::factory()->create([
            'name' => 'Munich Praxis',
            'slug' => 'munich-praxis',
            'timezone' => 'Europe/Berlin',
        ]);

        $otherPatient = Patient::factory()
            ->forClinic($otherClinic)
            ->create([
                'first_name' => 'Other',
                'last_name' => 'Patient',
                'email' => 'other.patient@example.com',
            ]);

        $otherConsent = Consent::create([
            'clinic_id' => $otherClinic->id,
            'patient_id' => $otherPatient->id,
            'type' => ConsentType::EmailReminders,
            'status' => ConsentStatus::Granted,
            'granted_at' => now(),
        ]);

        Sanctum::actingAs($receptionist);

        $this->postJson("/api/consents/{$otherConsent->id}/withdraw")
            ->assertForbidden();

        $this->assertDatabaseHas('consents', [
            'id' => $otherConsent->id,
            'status' => ConsentStatus::Granted->value,
        ]);
    }

    public function test_creating_new_granted_consent_with_same_type_withdraws_previous_one(): void
    {
        [, , $receptionist, , $patient] = $this->createConsentScenario();

        $oldConsent = Consent::create([
            'clinic_id' => $patient->clinic_id,
            'patient_id' => $patient->id,
            'granted_by_user_id' => $receptionist->id,
            'type' => ConsentType::EmailReminders,
            'status' => ConsentStatus::Granted,
            'granted_at' => now()->subDay(),
        ]);

        Sanctum::actingAs($receptionist);

        $this->postJson("/api/patients/{$patient->id}/consents", [
            'type' => ConsentType::EmailReminders->value,
            'notes' => 'Renewed email reminder consent.',
        ])->assertCreated()
            ->assertJsonPath('data.status', ConsentStatus::Granted->value);

        $this->assertDatabaseHas('consents', [
            'id' => $oldConsent->id,
            'status' => ConsentStatus::Withdrawn->value,
            'withdrawn_by_user_id' => $receptionist->id,
        ]);

        $this->assertDatabaseHas('consents', [
            'patient_id' => $patient->id,
            'type' => ConsentType::EmailReminders->value,
            'status' => ConsentStatus::Granted->value,
            'notes' => 'Renewed email reminder consent.',
        ]);
    }

    private function createConsentScenario(): array
    {
        $clinic = Clinic::factory()->create([
            'name' => 'Berlin Family Praxis',
            'slug' => 'berlin-family-praxis',
            'timezone' => 'Europe/Berlin',
        ]);

        $owner = User::factory()
            ->owner()
            ->for($clinic)
            ->create([
                'name' => 'Owner User',
                'email' => 'owner@example.com',
            ]);

        $receptionist = User::factory()
            ->receptionist()
            ->for($clinic)
            ->create([
                'name' => 'Receptionist One',
                'email' => 'receptionist@example.com',
            ]);

        $doctorUser = User::factory()
            ->doctor()
            ->for($clinic)
            ->create([
                'name' => 'Dr Anna Schmidt',
                'email' => 'doctor@example.com',
            ]);

        $patient = Patient::factory()
            ->forClinic($clinic)
            ->create([
                'first_name' => 'Lena',
                'last_name' => 'Schneider',
                'email' => 'lena.schneider@example.com',
                'date_of_birth' => '1991-07-20',
            ]);

        return [$clinic, $owner, $receptionist, $doctorUser, $patient];
    }
}
