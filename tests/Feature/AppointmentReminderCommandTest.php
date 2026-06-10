<?php

namespace Tests\Feature;

use App\Enums\AppointmentStatus;
use App\Enums\UserRole;
use App\Jobs\SendAppointmentReminderEmail;
use App\Models\Appointment;
use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class AppointmentReminderCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_dispatches_reminder_jobs_for_upcoming_confirmed_appointments(): void
    {
        Queue::fake();

        $clinic = Clinic::factory()->create();

        /** @var User $doctorUser */
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
            'status' => AppointmentStatus::Confirmed,
            'starts_at' => now()->addHours(3),
            'ends_at' => now()->addHours(3)->addMinutes(30),
        ]);

        $this->artisan('appointments:send-reminders')
            ->expectsOutput('Dispatched reminder jobs for 1 appointment(s).')
            ->assertSuccessful();

        Queue::assertPushed(SendAppointmentReminderEmail::class, function (
            SendAppointmentReminderEmail $job
        ) use ($appointment): bool {
            return $job->appointment->id === $appointment->id;
        });
    }

    public function test_command_does_not_dispatch_reminders_for_non_confirmed_appointments(): void
    {
        Queue::fake();

        $clinic = Clinic::factory()->create();

        /** @var User $doctorUser */
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

        Appointment::factory()->create([
            'clinic_id' => $clinic->id,
            'doctor_id' => $doctor->id,
            'patient_id' => $patient->id,
            'status' => AppointmentStatus::Scheduled,
            'starts_at' => now()->addHours(3),
            'ends_at' => now()->addHours(3)->addMinutes(30),
        ]);

        $this->artisan('appointments:send-reminders')
            ->expectsOutput('Dispatched reminder jobs for 0 appointment(s).')
            ->assertSuccessful();

        Queue::assertNotPushed(SendAppointmentReminderEmail::class);
    }
}
