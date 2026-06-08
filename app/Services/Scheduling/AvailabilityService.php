<?php

namespace App\Services\Scheduling;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\DoctorTimeOff;
use Carbon\CarbonImmutable;

class AvailabilityService
{
    public function isDoctorAvailable(
        Doctor $doctor,
        CarbonImmutable $startsAt,
        CarbonImmutable $endsAt,
        ?int $ignoreAppointmentId = null
    ): bool {
        if (! $doctor->is_active) {
            return false;
        }

        if (! $this->isInsideWorkingHours($doctor, $startsAt, $endsAt)) {
            return false;
        }

        if ($this->overlapsTimeOff($doctor, $startsAt, $endsAt)) {
            return false;
        }

        if ($this->overlapsExistingAppointment($doctor, $startsAt, $endsAt, $ignoreAppointmentId)) {
            return false;
        }

        return true;
    }

    private function isInsideWorkingHours(
        Doctor $doctor,
        CarbonImmutable $startsAt,
        CarbonImmutable $endsAt
    ): bool {
        $workingHours = $doctor->workingHours()
            ->where('day_of_week', $startsAt->dayOfWeek)
            ->where('is_active', true)
            ->get();

        foreach ($workingHours as $workingHour) {
            $date = $startsAt->format('Y-m-d');

            $workingStart = CarbonImmutable::parse(
                $date.' '.substr((string) $workingHour->starts_at, 0, 5),
                $startsAt->timezone
            );

            $workingEnd = CarbonImmutable::parse(
                $date.' '.substr((string) $workingHour->ends_at, 0, 5),
                $startsAt->timezone
            );

            if (
                $startsAt->greaterThanOrEqualTo($workingStart)
                && $endsAt->lessThanOrEqualTo($workingEnd)
            ) {
                return true;
            }
        }

        return false;
    }

    private function overlapsTimeOff(
        Doctor $doctor,
        CarbonImmutable $startsAt,
        CarbonImmutable $endsAt
    ): bool {
        return DoctorTimeOff::query()
            ->where('clinic_id', $doctor->clinic_id)
            ->where('doctor_id', $doctor->id)
            ->where('starts_at', '<', $endsAt->toDateTimeString())
            ->where('ends_at', '>', $startsAt->toDateTimeString())
            ->exists();
    }

    private function overlapsExistingAppointment(
        Doctor $doctor,
        CarbonImmutable $startsAt,
        CarbonImmutable $endsAt,
        ?int $ignoreAppointmentId = null
    ): bool {
        return Appointment::query()
            ->where('clinic_id', $doctor->clinic_id)
            ->where('doctor_id', $doctor->id)
            ->whereNotIn('status', [
                AppointmentStatus::Cancelled->value,
                AppointmentStatus::NoShow->value,
            ])
            ->when($ignoreAppointmentId, function ($query) use ($ignoreAppointmentId) {
                $query->where('id', '!=', $ignoreAppointmentId);
            })
            ->where('starts_at', '<', $endsAt->toDateTimeString())
            ->where('ends_at', '>', $startsAt->toDateTimeString())
            ->exists();
    }
}
