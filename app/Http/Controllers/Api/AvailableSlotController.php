<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Doctor;
use App\Models\User;
use App\Services\Scheduling\AvailableSlotsQuery;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

class AvailableSlotController extends Controller
{
    public function __construct(private readonly AvailableSlotsQuery $availableSlots) {}

    public function __invoke(Request $request, Doctor $doctor)
    {
        abort_unless($this->canViewAvailability($request->user(), $doctor), 403);

        $validated = $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
            'duration_minutes' => ['sometimes', 'integer', 'min:5', 'max:240'],
        ]);

        $timezone = $request->user()->clinic?->timezone ?? config('app.timezone');
        $date = CarbonImmutable::parse($validated['date'], $timezone)->startOfDay();
        $durationMinutes = (int) ($validated['duration_minutes'] ?? $doctor->appointment_duration_minutes);

        return response()->json([
            'data' => [
                'doctor_id' => $doctor->id,
                'date' => $validated['date'],
                'timezone' => $timezone,
                'duration_minutes' => $durationMinutes,
                'slots' => $this->availableSlots->forDate($doctor, $date, $durationMinutes),
            ],
        ]);
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
