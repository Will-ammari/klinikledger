<?php

namespace Tests\Feature;

use App\Enums\AppointmentStatus;
use App\Enums\UserRole;
use App\Jobs\SendAppointmentConfirmationEmail;
use App\Models\Appointment;
use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class AppointmentEmailQueueTest extends TestCase
{
    use RefreshDatabase;

    public function test_confirming_an_appointment_dispatches_confirmation_email_job(): void
    {
        Queue::fake();

        $clinic = Clinic::factory()->create();

        $receptionist = User::factory()->create([
            'clinic_id' => $clinic->id,
            'role' => UserRole::Receptionist,
        ]);

        $doctorUser = User::factory()->create([
            'clinic_id' => $clinic->id,
            'role' => UserRole::Doctor,
        ]);

        $doctor = Doctor::factory()->create([
            'clinic_id' => $clinic->id,
            'user_id' => $doctorUser->id,
        ]);

        $patient = Patient::factory()->create([
            'clinic_id' => $clinic->id,
            'email' => 'patient@example.test',
        ]);

        $appointment = Appointment::factory()->create([
            'clinic_id' => $clinic->id,
            'doctor_id' => $doctor->id,
            'patient_id' => $patient->id,
            'status' => AppointmentStatus::Scheduled,
            'starts_at' => now()->addDay()->setTime(10, 0),
            'ends_at' => now()->addDay()->setTime(10, 30),
        ]);

        $response = $this
            ->actingAs($receptionist)
            ->postJson("/api/appointments/{$appointment->id}/confirm");

        $response->assertOk();

        Queue::assertPushed(SendAppointmentConfirmationEmail::class, function (
            SendAppointmentConfirmationEmail $job
        ) use ($appointment): bool {
            return $job->appointment->id === $appointment->id;
        });
    }
}
