<?php

namespace App\Console\Commands;

use App\Enums\AppointmentStatus;
use App\Jobs\SendAppointmentReminderEmail;
use App\Models\Appointment;
use Illuminate\Console\Command;

class SendAppointmentRemindersCommand extends Command
{
    protected $signature = 'appointments:send-reminders';

    protected $description = 'Dispatch reminder email jobs for upcoming confirmed appointments.';

    public function handle(): int
    {
        $from = now();
        $to = now()->addDay();

        $appointments = Appointment::query()
            ->with(['clinic', 'doctor.user', 'patient'])
            ->where('status', AppointmentStatus::Confirmed)
            ->whereBetween('starts_at', [$from, $to])
            ->whereHas('patient', function ($query): void {
                $query->whereNotNull('email');
            })
            ->get();

        foreach ($appointments as $appointment) {
            SendAppointmentReminderEmail::dispatch($appointment);
        }

        $this->info("Dispatched reminder jobs for {$appointments->count()} appointment(s).");

        return self::SUCCESS;
    }
}
