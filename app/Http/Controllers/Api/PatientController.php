<?php

namespace App\Http\Controllers\Api;

use App\Enums\AuditAction;
use App\Enums\PatientStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\StorePatientRequest;
use App\Http\Requests\UpdatePatientRequest;
use App\Http\Resources\PatientResource;
use App\Models\Patient;
use App\Services\Audit\AuditLogger;
use App\Services\Privacy\PatientAnonymizer;
use Illuminate\Http\Request;

class PatientController extends Controller
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly PatientAnonymizer $patientAnonymizer
    ) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', Patient::class);

        $patients = Patient::query()
            ->where('clinic_id', $request->user()->clinic_id)
            ->when($request->user()->role === UserRole::Doctor, function ($query) use ($request) {
                $query->whereHas('appointments.doctor', function ($query) use ($request) {
                    $query->where('user_id', $request->user()->id);
                });
            })
            ->when($request->filled('status'), function ($query) use ($request) {
                $query->where('status', $request->string('status')->toString());
            })
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search')->toString();

                $query->where(function ($query) use ($search) {
                    $query
                        ->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%");
                });
            })
            ->latest()
            ->paginate($this->perPage($request));

        return PatientResource::collection($patients);
    }

    public function store(StorePatientRequest $request)
    {
        $validated = $request->validated();

        $patient = Patient::create([
            'clinic_id' => $request->user()->clinic_id,
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'email' => $validated['email'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'date_of_birth' => $validated['date_of_birth'] ?? null,
            'status' => PatientStatus::from($validated['status'] ?? PatientStatus::Active->value),
            'address' => $validated['address'] ?? null,
            'city' => $validated['city'] ?? null,
        ]);

        $this->auditLogger->log(
            actor: $request->user(),
            action: AuditAction::PatientCreated,
            auditable: $patient,
            metadata: [
                'patient_id' => $patient->id,
            ],
            request: $request
        );

        return response()->json([
            'data' => new PatientResource($patient),
        ], 201);
    }

    public function show(Request $request, Patient $patient)
    {
        $this->authorize('view', $patient);

        $this->auditLogger->log(
            actor: $request->user(),
            action: AuditAction::PatientViewed,
            auditable: $patient,
            metadata: [
                'patient_id' => $patient->id,
            ],
            request: $request
        );

        return response()->json([
            'data' => new PatientResource($patient),
        ]);
    }

    public function update(UpdatePatientRequest $request, Patient $patient)
    {
        $before = $patient->only([
            'email',
            'phone',
            'date_of_birth',
            'status',
            'address',
            'city',
        ]);

        $patient->update($request->validated());

        $after = $patient->fresh()->only([
            'email',
            'phone',
            'date_of_birth',
            'status',
            'address',
            'city',
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
            action: AuditAction::PatientUpdated,
            auditable: $patient,
            metadata: [
                'patient_id' => $patient->id,
                'changed_fields' => $changedFields,
            ],
            request: $request
        );

        return response()->json([
            'data' => new PatientResource($patient->fresh()),
        ]);
    }

    public function destroy(Request $request, Patient $patient)
    {
        $this->authorize('delete', $patient);

        $patientId = $patient->id;

        $patient->delete();

        $this->auditLogger->log(
            actor: $request->user(),
            action: AuditAction::PatientDeleted,
            auditable: $patient,
            metadata: [
                'patient_id' => $patientId,
                'deletion_type' => 'soft_delete',
            ],
            request: $request
        );

        return response()->json([
            'message' => 'Patient deleted successfully.',
        ]);
    }

    public function anonymize(Request $request, Patient $patient)
    {
        $this->authorize('anonymize', $patient);

        $originalPatientId = $patient->id;

        $patient = $this->patientAnonymizer->anonymize($patient);

        $this->auditLogger->log(
            actor: $request->user(),
            action: AuditAction::PatientAnonymized,
            auditable: $patient,
            metadata: [
                'patient_id' => $originalPatientId,
                'anonymized_at' => $patient->anonymized_at?->toISOString(),
            ],
            request: $request
        );

        return response()->json([
            'data' => new PatientResource($patient),
        ]);
    }
}
