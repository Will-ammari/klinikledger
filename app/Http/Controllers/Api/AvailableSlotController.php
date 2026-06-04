<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Doctor;
use App\Models\DoctorTimeOff;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

class AvailableSlotController extends Controller
{
    public function __invoke(Request $request, Doctor $doctor)
    {
        abort_unless($this->canViewAvailability($request->user(), $doctor), 403);

        $validated = $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
            'duration_minutes' => ['sometimes', 'integer', 'min:5', 'max:240'],
        ]);

        $timezone = $request->user()->clinic?->timezone ?? config('app.timezone');
        $date = CarbonImmutable::parse($validated['date'], $timezone)->startOfDay();
        $dayStart = $date->startOfDay();
        $dayEnd = $date->endOfDay();

        $durationMinutes = (int) ($validated['duration_minutes'] ?? $doctor->appointment_duration_minutes);

        $workingHours = $doctor->workingHours()
            ->where('clinic_id', $request->user()->clinic_id)
            ->where('day_of_week', $date->dayOfWeek)
            ->where('is_active', true)
            ->orderBy('starts_at')
            ->get();

        $timeOffs = DoctorTimeOff::query()
            ->where('clinic_id', $request->user()->clinic_id)
            ->where('doctor_id', $doctor->id)
            ->where('starts_at', '<', $dayEnd->toDateTimeString())
            ->where('ends_at', '>', $dayStart->toDateTimeString())
            ->get();

        $slots = [];

        foreach ($workingHours as $workingHour) {
            $cursor = CarbonImmutable::parse(
                $validated['date'] . ' ' . substr((string) $workingHour->starts_at, 0, 5),
                $timezone
            );

            $workingHourEnd = CarbonImmutable::parse(
                $validated['date'] . ' ' . substr((string) $workingHour->ends_at, 0, 5),
                $timezone
            );

            while ($cursor->addMinutes($durationMinutes)->lessThanOrEqualTo($workingHourEnd)) {
                $slotStart = $cursor;
                $slotEnd = $cursor->addMinutes($durationMinutes);

                if (! $this->overlapsAnyTimeOff($slotStart, $slotEnd, $timeOffs, $timezone)) {
                    $slots[] = [
                        'starts_at' => $slotStart->toIso8601String(),
                        'ends_at' => $slotEnd->toIso8601String(),
                    ];
                }

                $cursor = $slotEnd;
            }
        }

        return response()->json([
            'data' => [
                'doctor_id' => $doctor->id,
                'date' => $validated['date'],
                'timezone' => $timezone,
                'duration_minutes' => $durationMinutes,
                'slots' => $slots,
            ],
        ]);
    }

    private function overlapsAnyTimeOff(
        CarbonImmutable $slotStart,
        CarbonImmutable $slotEnd,
        $timeOffs,
        string $timezone
    ): bool {
        foreach ($timeOffs as $timeOff) {
            $timeOffStart = CarbonImmutable::parse($timeOff->starts_at, $timezone);
            $timeOffEnd = CarbonImmutable::parse($timeOff->ends_at, $timezone);

            if ($slotStart->lessThan($timeOffEnd) && $slotEnd->greaterThan($timeOffStart)) {
                return true;
            }
        }

        return false;
    }

    private function canViewAvailability(User $user, Doctor $doctor): bool
    {
        return $user->clinic_id === $doctor->clinic_id
            && in_array($user->role, [
                UserRole::OwnerClinic,
                UserRole::Receptionist,
                UserRole::Doctor,
            ], true);
    }
}
