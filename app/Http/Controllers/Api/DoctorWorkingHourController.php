<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpsertDoctorWorkingHoursRequest;
use App\Http\Resources\DoctorWorkingHourResource;
use App\Models\Doctor;
use App\Models\User;
use App\Services\Scheduling\DoctorScheduleWorkflow;
use Illuminate\Http\Request;

class DoctorWorkingHourController extends Controller
{
    public function __construct(
        private readonly DoctorScheduleWorkflow $workflow
    ) {}

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

        $this->workflow->replaceWorkingHours($request->user(), $doctor, $validated['working_hours'], $request);

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
