<?php

namespace App\Http\Controllers\Api;

use App\Enums\AuditAction;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpsertDoctorWorkingHoursRequest;
use App\Http\Resources\DoctorWorkingHourResource;
use App\Models\Doctor;
use App\Models\DoctorWorkingHour;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DoctorWorkingHourController extends Controller
{
    public function __construct(
        private readonly AuditLogger $auditLogger
    ) {
    }

    public function index(Request $request, Doctor $doctor)
    {
        abort_unless($this->canViewSchedule($request->user(), $doctor), 403);

        $workingHours = $doctor->workingHours()
            ->where('clinic_id', $request->user()->clinic_id)
            ->orderBy('day_of_week')
            ->orderBy('starts_at')
            ->get();

        return DoctorWorkingHourResource::collection($workingHours);
    }

    public function update(UpsertDoctorWorkingHoursRequest $request, Doctor $doctor)
    {
        $validated = $request->validated();

        DB::transaction(function () use ($request, $doctor, $validated) {
            DoctorWorkingHour::query()
                ->where('clinic_id', $request->user()->clinic_id)
                ->where('doctor_id', $doctor->id)
                ->delete();

            foreach ($validated['working_hours'] as $workingHour) {
                DoctorWorkingHour::create([
                    'clinic_id' => $request->user()->clinic_id,
                    'doctor_id' => $doctor->id,
                    'day_of_week' => $workingHour['day_of_week'],
                    'starts_at' => $workingHour['starts_at'],
                    'ends_at' => $workingHour['ends_at'],
                    'is_active' => $workingHour['is_active'] ?? true,
                ]);
            }
        });

        $this->auditLogger->log(
            actor: $request->user(),
            action: AuditAction::DoctorWorkingHoursUpdated,
            auditable: $doctor,
            metadata: [
                'doctor_id' => $doctor->id,
                'working_hours_count' => count($validated['working_hours']),
            ],
            request: $request
        );

        $workingHours = $doctor->workingHours()
            ->orderBy('day_of_week')
            ->orderBy('starts_at')
            ->get();

        return DoctorWorkingHourResource::collection($workingHours);
    }

    private function canViewSchedule(User $user, Doctor $doctor): bool
    {
        return $user->clinic_id === $doctor->clinic_id
            && in_array($user->role, [
                UserRole::OwnerClinic,
                UserRole::Receptionist,
                UserRole::Doctor,
            ], true);
    }
}
