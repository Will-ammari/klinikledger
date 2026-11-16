<?php

namespace App\Services\Scheduling;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\DoctorTimeOff;
use Carbon\CarbonImmutable;

/**
 * Read-only availability projection. A displayed slot is advisory; AppointmentWorkflow
 * rechecks availability under a doctor-row lock at booking time.
 */
final class AvailableSlotsQuery
{
    /** @return array<int, array{starts_at: string, ends_at: string}> */
    public function forDate(Doctor $doctor, CarbonImmutable $date, int $durationMinutes): array
    {
        if (! $doctor->is_active || $durationMinutes < 1) {
            return [];
        }

        $startOfDay = $date->startOfDay()->format('Y-m-d H:i:s');
        $endOfDay = $date->addDay()->startOfDay()->format('Y-m-d H:i:s');

        $workingHours = $doctor->workingHours()
            ->where('clinic_id', $doctor->clinic_id)
            ->where('day_of_week', $date->dayOfWeek)
            ->where('is_active', true)
            ->orderBy('starts_at')
            ->get(['starts_at', 'ends_at']);

        if ($workingHours->isEmpty()) {
            return [];
        }

        // The legacy schema stores timezone-less local DATETIME fields. Compare
        // those values in the same clinic-local wall-clock representation.
        $timeOffs = DoctorTimeOff::query()
            ->where('clinic_id', $doctor->clinic_id)
            ->where('doctor_id', $doctor->id)
            ->where('starts_at', '<', $endOfDay)
            ->where('ends_at', '>', $startOfDay)
            ->get(['starts_at', 'ends_at']);

        $appointments = Appointment::query()
            ->where('clinic_id', $doctor->clinic_id)
            ->where('doctor_id', $doctor->id)
            ->whereNotIn('status', [AppointmentStatus::Cancelled->value, AppointmentStatus::NoShow->value])
            ->where('starts_at', '<', $endOfDay)
            ->where('ends_at', '>', $startOfDay)
            ->get(['starts_at', 'ends_at']);

        $blocked = [];
        foreach ($timeOffs->concat($appointments) as $item) {
            $blocked[] = [
                (string) $item->getRawOriginal('starts_at'),
                (string) $item->getRawOriginal('ends_at'),
            ];
        }
        usort($blocked, static fn (array $a, array $b): int => strcmp($a[0], $b[0]));

        $slots = [];
        $dateString = $date->format('Y-m-d');
        $timezone = $date->timezone;

        foreach ($workingHours as $hours) {
            $cursor = CarbonImmutable::parse($dateString.' '.substr((string) $hours->starts_at, 0, 5), $timezone);
            $workingEnd = CarbonImmutable::parse($dateString.' '.substr((string) $hours->ends_at, 0, 5), $timezone);

            while ($cursor->addMinutes($durationMinutes)->lessThanOrEqualTo($workingEnd)) {
                $slotEnd = $cursor->addMinutes($durationMinutes);
                $startKey = $cursor->format('Y-m-d H:i:s');
                $endKey = $slotEnd->format('Y-m-d H:i:s');

                if (! $this->intersectsBlockedInterval($startKey, $endKey, $blocked)) {
                    $slots[$startKey] = [
                        'starts_at' => $cursor->toIso8601String(),
                        'ends_at' => $slotEnd->toIso8601String(),
                    ];
                }

                $cursor = $slotEnd;
            }
        }

        ksort($slots, SORT_STRING);

        return array_values($slots);
    }

    /** @param array<int, array{0: string, 1: string}> $blocked */
    private function intersectsBlockedInterval(string $start, string $end, array $blocked): bool
    {
        foreach ($blocked as [$blockedStart, $blockedEnd]) {
            if ($blockedStart >= $end) {
                break;
            }
            if ($start < $blockedEnd) {
                return true;
            }
        }

        return false;
    }
}
