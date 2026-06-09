<?php

namespace App\Http\Controllers\Api;

use App\Enums\AuditAction;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDoctorTimeOffRequest;
use App\Http\Resources\DoctorTimeOffResource;
use App\Models\Doctor;
use App\Models\DoctorTimeOff;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\Request;

class DoctorTimeOffController extends Controller
{
    public function __construct(
        private readonly AuditLogger $auditLogger
    ) {}

    public function index(Request $request, Doctor $doctor)
    {
        abort_unless($this->canViewSchedule($request->user(), $doctor), 403);

        $timeOffs = $doctor->timeOffs()
            ->where('clinic_id', $request->user()->clinic_id)
            ->latest('starts_at')
            ->paginate($this->perPage($request));

        return DoctorTimeOffResource::collection($timeOffs);
    }

    public function store(StoreDoctorTimeOffRequest $request, Doctor $doctor)
    {
        $validated = $request->validated();

        $timeOff = DoctorTimeOff::create([
            'clinic_id' => $request->user()->clinic_id,
            'doctor_id' => $doctor->id,
            'starts_at' => $validated['starts_at'],
            'ends_at' => $validated['ends_at'],
            'reason' => $validated['reason'] ?? null,
        ]);

        $this->auditLogger->log(
            actor: $request->user(),
            action: AuditAction::DoctorTimeOffCreated,
            auditable: $timeOff,
            metadata: [
                'doctor_id' => $doctor->id,
                'time_off_id' => $timeOff->id,
                'starts_at' => $timeOff->starts_at?->toISOString(),
                'ends_at' => $timeOff->ends_at?->toISOString(),
            ],
            request: $request
        );

        return response()->json([
            'data' => new DoctorTimeOffResource($timeOff),
        ], 201);
    }

    public function destroy(Request $request, Doctor $doctor, DoctorTimeOff $timeOff)
    {
        abort_unless($this->canManageSchedule($request->user(), $doctor), 403);

        abort_unless(
            $timeOff->clinic_id === $request->user()->clinic_id
            && $timeOff->doctor_id === $doctor->id,
            404
        );

        $timeOffId = $timeOff->id;

        $timeOff->delete();

        $this->auditLogger->log(
            actor: $request->user(),
            action: AuditAction::DoctorTimeOffDeleted,
            auditable: $doctor,
            metadata: [
                'doctor_id' => $doctor->id,
                'time_off_id' => $timeOffId,
            ],
            request: $request
        );

        return response()->json([
            'message' => 'Doctor time off deleted successfully.',
        ]);
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

    private function canManageSchedule(User $user, Doctor $doctor): bool
    {
        if ($user->clinic_id !== $doctor->clinic_id) {
            return false;
        }

        if ($user->role === UserRole::OwnerClinic) {
            return true;
        }

        return $user->role === UserRole::Doctor
            && $doctor->user_id === $user->id;
    }
}
