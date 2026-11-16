<?php

namespace App\Services\Scheduling;

use App\Enums\AppointmentStatus;
use App\Enums\AuditAction;
use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\DoctorTimeOff;
use App\Models\DoctorWorkingHour;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Schedule mutations use the same doctor-row serialization point as booking.
 */
final class DoctorScheduleWorkflow
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function replaceWorkingHours(User $actor, Doctor $doctor, array $hours, Request $request): void
    {
        DB::transaction(function () use ($actor, $doctor, $hours, $request): void {
            $locked = $this->lockDoctor($actor, $doctor);

            DoctorWorkingHour::query()
                ->where('clinic_id', $locked->clinic_id)
                ->where('doctor_id', $locked->id)
                ->delete();

            foreach ($hours as $hour) {
                DoctorWorkingHour::create([
                    'clinic_id' => $locked->clinic_id,
                    'doctor_id' => $locked->id,
                    'day_of_week' => $hour['day_of_week'],
                    'starts_at' => $hour['starts_at'],
                    'ends_at' => $hour['ends_at'],
                    'is_active' => $hour['is_active'] ?? true,
                ]);
            }

            $this->auditLogger->log(
                actor: $actor,
                action: AuditAction::DoctorWorkingHoursUpdated,
                auditable: $locked,
                metadata: [
                    'doctor_id' => $locked->id,
                    'working_hours_count' => count($hours),
                ],
                request: $request,
            );
        }, 3);
    }

    public function addTimeOff(User $actor, Doctor $doctor, array $data, Request $request): DoctorTimeOff
    {
        $timezone = $actor->clinic?->timezone ?? config('app.timezone');
        $startsAt = CarbonImmutable::parse($data['starts_at'], $timezone)->setTimezone($timezone);
        $endsAt = CarbonImmutable::parse($data['ends_at'], $timezone)->setTimezone($timezone);

        return DB::transaction(function () use ($actor, $doctor, $data, $request, $startsAt, $endsAt): DoctorTimeOff {
            $locked = $this->lockDoctor($actor, $doctor);

            $conflicts = Appointment::query()
                ->where('clinic_id', $locked->clinic_id)
                ->where('doctor_id', $locked->id)
                ->whereIn('status', [AppointmentStatus::Scheduled->value, AppointmentStatus::Confirmed->value])
                ->where('starts_at', '<', $endsAt->toDateTimeString())
                ->where('ends_at', '>', $startsAt->toDateTimeString())
                ->exists();

            if ($conflicts) {
                throw ValidationException::withMessages([
                    'starts_at' => ['This time off overlaps an existing appointment; reschedule or cancel it first.'],
                ]);
            }

            $timeOff = DoctorTimeOff::create([
                'clinic_id' => $locked->clinic_id,
                'doctor_id' => $locked->id,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'reason' => $data['reason'] ?? null,
            ]);

            $this->auditLogger->log(
                actor: $actor,
                action: AuditAction::DoctorTimeOffCreated,
                auditable: $timeOff,
                metadata: [
                    'doctor_id' => $locked->id,
                    'time_off_id' => $timeOff->id,
                    'starts_at' => $timeOff->starts_at?->toISOString(),
                    'ends_at' => $timeOff->ends_at?->toISOString(),
                ],
                request: $request,
            );

            return $timeOff;
        }, 3);
    }

    public function removeTimeOff(User $actor, Doctor $doctor, DoctorTimeOff $timeOff, Request $request): void
    {
        DB::transaction(function () use ($actor, $doctor, $timeOff, $request): void {
            $locked = $this->lockDoctor($actor, $doctor);

            $record = DoctorTimeOff::query()
                ->where('clinic_id', $locked->clinic_id)
                ->where('doctor_id', $locked->id)
                ->lockForUpdate()
                ->findOrFail($timeOff->id);
            $record->delete();

            $this->auditLogger->log(
                actor: $actor,
                action: AuditAction::DoctorTimeOffDeleted,
                auditable: $locked,
                metadata: [
                    'doctor_id' => $locked->id,
                    'time_off_id' => $timeOff->id,
                ],
                request: $request,
            );
        }, 3);
    }

    private function lockDoctor(User $actor, Doctor $doctor): Doctor
    {
        return Doctor::query()
            ->where('clinic_id', $actor->clinic_id)
            ->lockForUpdate()
            ->findOrFail($doctor->id);
    }
}
