<?php

namespace Tests\Feature;

use App\Enums\AuditAction;
use App\Models\Appointment;
use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\Invoice;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PatientAnonymizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_anonymize_patient_without_breaking_relations(): void
    {
        [$clinic, $owner, , , $patient, $appointment, $invoice] = $this->createAnonymizationScenario();

        Sanctum::actingAs($owner);

        $response = $this->postJson("/api/patients/{$patient->id}/anonymize");

        $response->assertOk()
            ->assertJsonPath('data.id', $patient->id)
            ->assertJsonPath('data.first_name', 'Anonymous')
            ->assertJsonPath('data.last_name', 'Patient')
            ->assertJsonPath('data.email', "anonymized_patient_{$clinic->id}_{$patient->id}@example.invalid")
            ->assertJsonPath('data.phone', null)
            ->assertJsonPath('data.date_of_birth', null)
            ->assertJsonPath('data.address', null)
            ->assertJsonPath('data.city', null)
            ->assertJsonPath('data.is_anonymized', true);

        $this->assertDatabaseHas('patients', [
            'id' => $patient->id,
            'clinic_id' => $clinic->id,
            'first_name' => 'Anonymous',
            'last_name' => 'Patient',
            'email' => "anonymized_patient_{$clinic->id}_{$patient->id}@example.invalid",
            'phone' => null,
            'date_of_birth' => null,
            'address' => null,
            'city' => null,
        ]);

        $this->assertDatabaseHas('appointments', [
            'id' => $appointment->id,
            'patient_id' => $patient->id,
        ]);

        $this->assertDatabaseHas('invoices', [
            'id' => $invoice->id,
            'patient_id' => $patient->id,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'clinic_id' => $clinic->id,
            'actor_user_id' => $owner->id,
            'action' => AuditAction::PatientAnonymized->value,
        ]);
    }

    public function test_receptionist_cannot_anonymize_patient(): void
    {
        [, , $receptionist, , $patient] = $this->createAnonymizationScenario();

        Sanctum::actingAs($receptionist);

        $this->postJson("/api/patients/{$patient->id}/anonymize")
            ->assertForbidden();

        $this->assertDatabaseHas('patients', [
            'id' => $patient->id,
            'first_name' => 'Lena',
            'last_name' => 'Schneider',
            'email' => 'lena.schneider@example.com',
        ]);
    }

    public function test_doctor_cannot_anonymize_patient(): void
    {
        [, , , $doctorUser, $patient] = $this->createAnonymizationScenario();

        Sanctum::actingAs($doctorUser);

        $this->postJson("/api/patients/{$patient->id}/anonymize")
            ->assertForbidden();

        $this->assertDatabaseHas('patients', [
            'id' => $patient->id,
            'email' => 'lena.schneider@example.com',
        ]);
    }

    public function test_owner_cannot_anonymize_patient_from_another_clinic(): void
    {
        [, $owner] = $this->createAnonymizationScenario();

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

        Sanctum::actingAs($owner);

        $this->postJson("/api/patients/{$otherPatient->id}/anonymize")
            ->assertForbidden();

        $this->assertDatabaseHas('patients', [
            'id' => $otherPatient->id,
            'email' => 'other.patient@example.com',
            'anonymized_at' => null,
        ]);
    }

    public function test_already_anonymized_patient_cannot_be_anonymized_again(): void
    {
        [, $owner, , , $patient] = $this->createAnonymizationScenario();

        $patient->forceFill([
            'first_name' => 'Anonymous',
            'last_name' => 'Patient',
            'email' => "anonymized_patient_{$patient->clinic_id}_{$patient->id}@example.invalid",
            'phone' => null,
            'date_of_birth' => null,
            'address' => null,
            'city' => null,
            'anonymized_at' => now(),
        ])->save();

        Sanctum::actingAs($owner);

        $this->postJson("/api/patients/{$patient->id}/anonymize")
            ->assertForbidden();
    }

    private function createAnonymizationScenario(): array
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

        $doctor = Doctor::factory()
            ->linkedToUser($doctorUser)
            ->create([
                'specialization' => 'General Medicine',
                'appointment_duration_minutes' => 30,
                'is_active' => true,
            ]);

        $patient = Patient::factory()
            ->forClinic($clinic)
            ->create([
                'first_name' => 'Lena',
                'last_name' => 'Schneider',
                'email' => 'lena.schneider@example.com',
                'phone' => '+49 30 123456',
                'date_of_birth' => '1991-07-20',
                'address' => 'Example Street 10',
                'city' => 'Berlin',
            ]);

        $appointment = Appointment::factory()
            ->forClinicDoctorAndPatient($clinic, $doctor, $patient)
            ->scheduledAt(now()->addDay()->setTime(9, 0)->format('Y-m-d H:i:s'))
            ->create([
                'reason' => 'Initial consultation',
            ]);

        $invoice = Invoice::create([
            'clinic_id' => $clinic->id,
            'patient_id' => $patient->id,
            'appointment_id' => $appointment->id,
            'status' => 'draft',
            'subtotal' => '100.00',
            'tax' => '19.00',
            'total' => '119.00',
            'due_date' => now()->addDays(14)->toDateString(),
        ]);

        return [
            $clinic,
            $owner,
            $receptionist,
            $doctorUser,
            $patient,
            $appointment,
            $invoice,
        ];
    }
}
