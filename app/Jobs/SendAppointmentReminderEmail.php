<?php

namespace App\Jobs;

use App\Mail\AppointmentReminderMail;
use App\Models\Appointment;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;

class SendAppointmentReminderEmail implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public Appointment $appointment
    ) {}

    public function handle(): void
    {
        $appointment = $this->appointment->loadMissing([
            'clinic',
            'doctor.user',
            'patient',
        ]);

        if ($appointment->patient->email === null) {
            return;
        }

        Mail::to($appointment->patient->email)
            ->send(new AppointmentReminderMail($appointment));
    }
}
