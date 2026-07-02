<?php

namespace App\Http\Controllers\Api;

use App\Enums\AuditAction;
use App\Enums\ConsentStatus;
use App\Enums\ConsentType;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreConsentRequest;
use App\Http\Resources\ConsentResource;
use App\Models\Consent;
use App\Models\Patient;
use App\Services\Audit\AuditLogger;
use App\Support\ApiEnum;
use Illuminate\Http\Request;

class ConsentController extends Controller
{
    public function __construct(
        private readonly AuditLogger $auditLogger
    ) {}

    public function index(Request $request, Patient $patient)
    {
        $this->authorize('viewAnyForPatient', [Consent::class, $patient]);

        $consents = Consent::query()
            ->with(['patient', 'grantedBy', 'withdrawnBy'])
            ->where('clinic_id', $request->user()->clinic_id)
            ->where('patient_id', $patient->id)
            ->latest()
            ->paginate($this->perPage($request));

        $this->auditLogger->log(
            actor: $request->user(),
            action: AuditAction::ConsentViewed,
            auditable: $patient,
            metadata: [
                'patient_id' => $patient->id,
            ],
            request: $request
        );

        return ConsentResource::collection($consents);
    }

    public function store(StoreConsentRequest $request, Patient $patient)
    {
        $validated = $request->validated();

        Consent::query()
            ->where('clinic_id', $request->user()->clinic_id)
            ->where('patient_id', $patient->id)
            ->where('type', $validated['type'])
            ->where('status', ConsentStatus::Granted->value)
            ->update([
                'status' => ConsentStatus::Withdrawn,
                'withdrawn_by_user_id' => $request->user()->id,
                'withdrawn_at' => now(),
            ]);

        $consent = Consent::create([
            'clinic_id' => $patient->clinic_id,
            'patient_id' => $patient->id,
            'granted_by_user_id' => $request->user()->id,
            'type' => ConsentType::from($validated['type']),
            'status' => ConsentStatus::Granted,
            'granted_at' => now(),
            'notes' => $validated['notes'] ?? null,
        ]);

        $this->auditLogger->log(
            actor: $request->user(),
            action: AuditAction::ConsentCreated,
            auditable: $consent,
            metadata: [
                'consent_id' => $consent->id,
                'patient_id' => $patient->id,
                'type' => ApiEnum::value($consent->type),
                'status' => ApiEnum::value($consent->status),
            ],
            request: $request
        );

        return response()->json([
            'data' => new ConsentResource(
                $consent->load(['patient', 'grantedBy', 'withdrawnBy'])
            ),
        ], 201);
    }

    public function withdraw(Request $request, Consent $consent)
    {
        $this->authorize('withdraw', $consent);

        $consent->update([
            'status' => ConsentStatus::Withdrawn,
            'withdrawn_by_user_id' => $request->user()->id,
            'withdrawn_at' => now(),
        ]);

        $this->auditLogger->log(
            actor: $request->user(),
            action: AuditAction::ConsentWithdrawn,
            auditable: $consent,
            metadata: [
                'consent_id' => $consent->id,
                'patient_id' => $consent->patient_id,
                'type' => ApiEnum::value($consent->type),
            ],
            request: $request
        );

        return response()->json([
            'data' => new ConsentResource(
                $consent->fresh()->load(['patient', 'grantedBy', 'withdrawnBy'])
            ),
        ]);
    }
}
