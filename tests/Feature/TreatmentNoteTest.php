<?php

namespace Tests\Feature;

use App\Enums\AuditAction;
use App\Enums\TreatmentNoteVisibility;
use App\Models\Appointment;
use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\TreatmentNote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TreatmentNoteTest extends TestCase
{
    use RefreshDatabase;

    public function test_doctor_can_create_treatment_note_for_own_appointment(): void
    {
        [$clinic, , $doctorUser, $doctor, $patient, $appointment] = $this->createTreatmentNoteScenario();

        Sanctum::actingAs($doctorUser);

        $response = $this->postJson("/api/appointments/{$appointment->id}/notes", [
            'subjective' => 'Patient reports mild headache.',
            'objective' => 'Blood pressure is stable.',
            'assessment' => 'Likely tension headache.',
            'plan' => 'Hydration and rest.',
            'visibility' => TreatmentNoteVisibility::DoctorOnly->value,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.appointment_id', $appointment->id)
            ->assertJsonPath('data.doctor_id', $doctor->id)
            ->assertJsonPath('data.patient_id', $patient->id)
            ->assertJsonPath('data.visibility', TreatmentNoteVisibility::DoctorOnly->value);

        $this->assertDatabaseHas('treatment_notes', [
            'clinic_id' => $clinic->id,
            'appointment_id' => $appointment->id,
            'doctor_id' => $doctor->id,
            'patient_id' => $patient->id,
            'assessment' => 'Likely tension headache.',
            'visibility' => TreatmentNoteVisibility::DoctorOnly->value,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'clinic_id' => $clinic->id,
            'actor_user_id' => $doctorUser->id,
            'action' => AuditAction::TreatmentNoteCreated->value,
        ]);
    }

    public function test_receptionist_cannot_create_treatment_note(): void
    {
        [, , , , , $appointment, $receptionist] = $this->createTreatmentNoteScenario();

        Sanctum::actingAs($receptionist);

        $this->postJson("/api/appointments/{$appointment->id}/notes", [
            'assessment' => 'Receptionist should not create clinical notes.',
        ])->assertForbidden();

        $this->assertDatabaseCount('treatment_notes', 0);
    }

    public function test_doctor_can_view_own_treatment_note_and_audit_log_is_written(): void
    {
        [$clinic, , $doctorUser, , , $appointment] = $this->createTreatmentNoteScenario();

        $note = TreatmentNote::create([
            'clinic_id' => $appointment->clinic_id,
            'appointment_id' => $appointment->id,
            'doctor_id' => $appointment->doctor_id,
            'patient_id' => $appointment->patient_id,
            'subjective' => 'Patient reports fatigue.',
            'objective' => null,
            'assessment' => 'Observation required.',
            'plan' => 'Follow-up next week.',
            'visibility' => TreatmentNoteVisibility::DoctorOnly,
        ]);

        Sanctum::actingAs($doctorUser);

        $this->getJson("/api/treatment-notes/{$note->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $note->id)
            ->assertJsonPath('data.assessment', 'Observation required.');

        $this->assertDatabaseHas('audit_logs', [
            'clinic_id' => $clinic->id,
            'actor_user_id' => $doctorUser->id,
            'action' => AuditAction::TreatmentNoteViewed->value,
        ]);
    }

    public function test_owner_can_view_note_only_when_visibility_allows_clinic_owner(): void
    {
        [, $owner, , , , $appointment] = $this->createTreatmentNoteScenario();

        $doctorOnlyNote = TreatmentNote::create([
            'clinic_id' => $appointment->clinic_id,
            'appointment_id' => $appointment->id,
            'doctor_id' => $appointment->doctor_id,
            'patient_id' => $appointment->patient_id,
            'assessment' => 'Private doctor-only note.',
            'visibility' => TreatmentNoteVisibility::DoctorOnly,
        ]);

        Sanctum::actingAs($owner);

        $this->getJson("/api/treatment-notes/{$doctorOnlyNote->id}")
            ->assertForbidden();

        $doctorOnlyNote->update([
            'visibility' => TreatmentNoteVisibility::ClinicOwner,
        ]);

        $this->getJson("/api/treatment-notes/{$doctorOnlyNote->id}")
            ->assertOk()
            ->assertJsonPath('data.visibility', TreatmentNoteVisibility::ClinicOwner->value);
    }

    public function test_doctor_can_update_own_treatment_note(): void
    {
        [, , $doctorUser, , , $appointment] = $this->createTreatmentNoteScenario();

        $note = TreatmentNote::create([
            'clinic_id' => $appointment->clinic_id,
            'appointment_id' => $appointment->id,
            'doctor_id' => $appointment->doctor_id,
            'patient_id' => $appointment->patient_id,
            'assessment' => 'Initial assessment.',
            'plan' => 'Initial plan.',
            'visibility' => TreatmentNoteVisibility::DoctorOnly,
        ]);

        Sanctum::actingAs($doctorUser);

        $this->patchJson("/api/treatment-notes/{$note->id}", [
            'assessment' => 'Updated assessment.',
            'plan' => 'Updated plan.',
        ])->assertOk()
            ->assertJsonPath('data.assessment', 'Updated assessment.')
            ->assertJsonPath('data.plan', 'Updated plan.');

        $this->assertDatabaseHas('treatment_notes', [
            'id' => $note->id,
            'assessment' => 'Updated assessment.',
            'plan' => 'Updated plan.',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'actor_user_id' => $doctorUser->id,
            'action' => AuditAction::TreatmentNoteUpdated->value,
        ]);
    }

    public function test_receptionist_cannot_view_treatment_note(): void
    {
        [, , , , , $appointment, $receptionist] = $this->createTreatmentNoteScenario();

        $note = TreatmentNote::create([
            'clinic_id' => $appointment->clinic_id,
            'appointment_id' => $appointment->id,
            'doctor_id' => $appointment->doctor_id,
            'patient_id' => $appointment->patient_id,
            'assessment' => 'Sensitive clinical assessment.',
            'visibility' => TreatmentNoteVisibility::ClinicOwner,
        ]);

        Sanctum::actingAs($receptionist);

        $this->getJson("/api/treatment-notes/{$note->id}")
            ->assertForbidden();
    }

    private function createTreatmentNoteScenario(): array
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

        $doctorUser = User::factory()
            ->doctor()
            ->for($clinic)
            ->create([
                'name' => 'Dr Anna Schmidt',
                'email' => 'doctor@example.com',
            ]);

        $receptionist = User::factory()
            ->receptionist()
            ->for($clinic)
            ->create([
                'name' => 'Receptionist One',
                'email' => 'receptionist@example.com',
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
                'date_of_birth' => '1991-07-20',
            ]);

        $appointment = Appointment::factory()
            ->forClinicDoctorAndPatient($clinic, $doctor, $patient)
            ->scheduledAt('2026-06-08 09:00:00')
            ->create([
                'reason' => 'Initial consultation',
            ]);

        return [$clinic, $owner, $doctorUser, $doctor, $patient, $appointment, $receptionist];
    }
}
