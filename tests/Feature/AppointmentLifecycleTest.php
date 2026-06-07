<?php

namespace Tests\Feature;

use App\Enums\AppointmentStatus;
use App\Enums\AuditAction;
use App\Models\Appointment;
use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\DoctorWorkingHour;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AppointmentLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_receptionist_can_confirm_scheduled_appointment(): void
    {
        [$clinic, , $receptionist, , , , $appointment] = $this->createLifecycleScenario();

        Sanctum::actingAs($receptionist);

        $this->postJson("/api/appointments/{$appointment->id}/confirm")
            ->assertOk()
            ->assertJsonPath('data.status', AppointmentStatus::Confirmed->value);

        $this->assertDatabaseHas('appointments', [
            'id' => $appointment->id,
            'status' => AppointmentStatus::Confirmed->value,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'clinic_id' => $clinic->id,
            'actor_user_id' => $receptionist->id,
            'action' => AuditAction::AppointmentConfirmed->value,
        ]);
    }

    public function test_receptionist_can_cancel_appointment_with_reason(): void
    {
        [$clinic, , $receptionist, , , , $appointment] = $this->createLifecycleScenario();

        Sanctum::actingAs($receptionist);

        $this->postJson("/api/appointments/{$appointment->id}/cancel", [
            'cancellation_reason' => 'Patient requested cancellation.',
        ])->assertOk()
            ->assertJsonPath('data.status', AppointmentStatus::Cancelled->value)
            ->assertJsonPath('data.cancellation_reason', 'Patient requested cancellation.');

        $this->assertDatabaseHas('appointments', [
            'id' => $appointment->id,
            'status' => AppointmentStatus::Cancelled->value,
            'cancellation_reason' => 'Patient requested cancellation.',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'clinic_id' => $clinic->id,
            'actor_user_id' => $receptionist->id,
            'action' => AuditAction::AppointmentCancelled->value,
        ]);
    }

    public function test_doctor_can_complete_own_past_appointment(): void
    {
        [$clinic, , , $doctorUser, , , $appointment] = $this->createLifecycleScenario([
            'starts_at' => now()->subDay()->setTime(9, 0),
            'ends_at' => now()->subDay()->setTime(9, 30),
            'status' => AppointmentStatus::Confirmed,
        ]);

        Sanctum::actingAs($doctorUser);

        $this->postJson("/api/appointments/{$appointment->id}/complete")
            ->assertOk()
            ->assertJsonPath('data.status', AppointmentStatus::Completed->value);

        $this->assertDatabaseHas('appointments', [
            'id' => $appointment->id,
            'status' => AppointmentStatus::Completed->value,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'clinic_id' => $clinic->id,
            'actor_user_id' => $doctorUser->id,
            'action' => AuditAction::AppointmentCompleted->value,
        ]);
    }

    public function test_future_appointment_cannot_be_completed(): void
    {
        [, , , $doctorUser, , , $appointment] = $this->createLifecycleScenario([
            'starts_at' => now()->addDay()->setTime(9, 0),
            'ends_at' => now()->addDay()->setTime(9, 30),
            'status' => AppointmentStatus::Confirmed,
        ]);

        Sanctum::actingAs($doctorUser);

        $this->postJson("/api/appointments/{$appointment->id}/complete")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['appointment']);
    }

    public function test_doctor_can_mark_own_past_appointment_as_no_show(): void
    {
        [$clinic, , , $doctorUser, , , $appointment] = $this->createLifecycleScenario([
            'starts_at' => now()->subDay()->setTime(9, 0),
            'ends_at' => now()->subDay()->setTime(9, 30),
            'status' => AppointmentStatus::Confirmed,
        ]);

        Sanctum::actingAs($doctorUser);

        $this->postJson("/api/appointments/{$appointment->id}/no-show")
            ->assertOk()
            ->assertJsonPath('data.status', AppointmentStatus::NoShow->value);

        $this->assertDatabaseHas('appointments', [
            'id' => $appointment->id,
            'status' => AppointmentStatus::NoShow->value,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'clinic_id' => $clinic->id,
            'actor_user_id' => $doctorUser->id,
            'action' => AuditAction::AppointmentNoShowMarked->value,
        ]);
    }

    public function test_future_appointment_cannot_be_marked_as_no_show(): void
    {
        [, , , $doctorUser, , , $appointment] = $this->createLifecycleScenario([
            'starts_at' => now()->addDay()->setTime(9, 0),
            'ends_at' => now()->addDay()->setTime(9, 30),
            'status' => AppointmentStatus::Confirmed,
        ]);

        Sanctum::actingAs($doctorUser);

        $this->postJson("/api/appointments/{$appointment->id}/no-show")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['appointment']);
    }

    public function test_receptionist_can_reschedule_appointment(): void
    {
        [$clinic, , $receptionist, , , , $appointment] = $this->createLifecycleScenario([
            'starts_at' => '2026-06-08 09:00:00',
            'ends_at' => '2026-06-08 09:30:00',
            'status' => AppointmentStatus::Confirmed,
        ]);

        Sanctum::actingAs($receptionist);

        $this->postJson("/api/appointments/{$appointment->id}/reschedule", [
            'starts_at' => '2026-06-08 10:00:00',
        ])->assertOk()
            ->assertJsonPath('data.status', AppointmentStatus::Scheduled->value);

        $this->assertDatabaseHas('appointments', [
            'id' => $appointment->id,
            'status' => AppointmentStatus::Scheduled->value,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'clinic_id' => $clinic->id,
            'actor_user_id' => $receptionist->id,
            'action' => AuditAction::AppointmentRescheduled->value,
        ]);
    }

    public function test_doctor_cannot_reschedule_appointment(): void
    {
        [, , , $doctorUser, , , $appointment] = $this->createLifecycleScenario();

        Sanctum::actingAs($doctorUser);

        $this->postJson("/api/appointments/{$appointment->id}/reschedule", [
            'starts_at' => '2026-06-08 10:00:00',
        ])->assertForbidden();
    }

    public function test_cancelled_appointment_cannot_be_rescheduled(): void
    {
        [, , $receptionist, , , , $appointment] = $this->createLifecycleScenario([
            'status' => AppointmentStatus::Cancelled,
            'cancelled_at' => now(),
            'cancellation_reason' => 'Patient cancelled.',
        ]);

        Sanctum::actingAs($receptionist);

        $this->postJson("/api/appointments/{$appointment->id}/reschedule", [
            'starts_at' => '2026-06-08 10:00:00',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['appointment']);
    }

    private function createLifecycleScenario(array $appointmentOverrides = []): array
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

        DoctorWorkingHour::factory()
            ->forDoctor($doctor)
            ->mondayMorning()
            ->create();

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
            ->create(array_merge([
                'starts_at' => '2026-06-08 09:00:00',
                'ends_at' => '2026-06-08 09:30:00',
                'status' => AppointmentStatus::Scheduled,
                'reason' => 'Initial consultation',
            ], $appointmentOverrides));

        return [
            $clinic,
            $owner,
            $receptionist,
            $doctorUser,
            $doctor,
            $patient,
            $appointment,
        ];
    }
}
