<?php

namespace App\Http\Controllers\Api;

use App\Enums\AuditAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDoctorRequest;
use App\Http\Requests\UpdateDoctorRequest;
use App\Http\Resources\DoctorResource;
use App\Models\Doctor;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\Request;

class DoctorController extends Controller
{
    public function __construct(
        private readonly AuditLogger $auditLogger
    ) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', Doctor::class);

        $doctors = Doctor::query()
            ->with('user')
            ->where('clinic_id', $request->user()->clinic_id)
            ->when($request->has('is_active'), function ($query) use ($request) {
                $query->where('is_active', $request->boolean('is_active'));
            })
            ->when($request->filled('specialization'), function ($query) use ($request) {
                $specialization = $request->string('specialization')->toString();

                $query->where('specialization', 'like', "%{$specialization}%");
            })
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search')->toString();

                $query->where(function ($query) use ($search) {
                    $query
                        ->where('specialization', 'like', "%{$search}%")
                        ->orWhereHas('user', function ($query) use ($search) {
                            $query
                                ->where('name', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%");
                        });
                });
            })
            ->latest()
            ->paginate($this->perPage($request));

        return DoctorResource::collection($doctors);
    }

    public function store(StoreDoctorRequest $request)
    {
        $validated = $request->validated();

        $doctor = Doctor::create([
            'clinic_id' => $request->user()->clinic_id,
            'user_id' => $validated['user_id'] ?? null,
            'specialization' => $validated['specialization'] ?? null,
            'appointment_duration_minutes' => $validated['appointment_duration_minutes'],
            'is_active' => $validated['is_active'] ?? true,
        ]);

        $this->auditLogger->log(
            actor: $request->user(),
            action: AuditAction::DoctorCreated,
            auditable: $doctor,
            metadata: [
                'doctor_id' => $doctor->id,
                'linked_user_id' => $doctor->user_id,
                'appointment_duration_minutes' => $doctor->appointment_duration_minutes,
                'is_active' => $doctor->is_active,
            ],
            request: $request
        );

        return response()->json([
            'data' => new DoctorResource($doctor->load('user')),
        ], 201);
    }

    public function show(Request $request, Doctor $doctor)
    {
        $this->authorize('view', $doctor);

        $this->auditLogger->log(
            actor: $request->user(),
            action: AuditAction::DoctorViewed,
            auditable: $doctor,
            metadata: [
                'doctor_id' => $doctor->id,
            ],
            request: $request
        );

        return response()->json([
            'data' => new DoctorResource($doctor->load('user')),
        ]);
    }

    public function update(UpdateDoctorRequest $request, Doctor $doctor)
    {
        $before = $doctor->only([
            'user_id',
            'specialization',
            'appointment_duration_minutes',
            'is_active',
        ]);

        $doctor->update($request->validated());

        $after = $doctor->fresh()->only([
            'user_id',
            'specialization',
            'appointment_duration_minutes',
            'is_active',
        ]);

        $changedFields = collect($before)
            ->filter(function ($oldValue, string $field) use ($after) {
                return (string) $oldValue !== (string) ($after[$field] ?? null);
            })
            ->keys()
            ->values()
            ->all();

        $this->auditLogger->log(
            actor: $request->user(),
            action: AuditAction::DoctorUpdated,
            auditable: $doctor,
            metadata: [
                'doctor_id' => $doctor->id,
                'changed_fields' => $changedFields,
            ],
            request: $request
        );

        return response()->json([
            'data' => new DoctorResource($doctor->fresh()->load('user')),
        ]);
    }

    public function destroy(Request $request, Doctor $doctor)
    {
        $this->authorize('delete', $doctor);

        $doctorId = $doctor->id;
        $linkedUserId = $doctor->user_id;

        $doctor->delete();

        $this->auditLogger->log(
            actor: $request->user(),
            action: AuditAction::DoctorDeleted,
            auditable: $doctor,
            metadata: [
                'doctor_id' => $doctorId,
                'linked_user_id' => $linkedUserId,
                'deletion_type' => 'hard_delete',
            ],
            request: $request
        );

        return response()->json([
            'message' => 'Doctor deleted successfully.',
        ]);
    }
}
