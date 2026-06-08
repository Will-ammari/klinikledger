<?php

namespace App\Http\Controllers\Api;

use App\Enums\AuditAction;
use App\Http\Controllers\Controller;
use App\Http\Resources\PatientExportResource;
use App\Models\Patient;
use App\Models\PatientExport;
use App\Services\Audit\AuditLogger;
use App\Services\Privacy\PatientExportBuilder;
use Illuminate\Http\Request;

class PatientExportController extends Controller
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly PatientExportBuilder $exportBuilder
    ) {}

    public function store(Request $request, Patient $patient)
    {
        $this->authorize('export', $patient);

        $payload = $this->exportBuilder->build($patient);

        $patientExport = PatientExport::create([
            'clinic_id' => $patient->clinic_id,
            'patient_id' => $patient->id,
            'requested_by_user_id' => $request->user()->id,
            'payload' => $payload,
            'generated_at' => now(),
        ]);

        $this->auditLogger->log(
            actor: $request->user(),
            action: AuditAction::PatientExportRequested,
            auditable: $patientExport,
            metadata: [
                'patient_export_id' => $patientExport->id,
                'patient_id' => $patient->id,
            ],
            request: $request
        );

        return response()->json([
            'data' => new PatientExportResource(
                $patientExport->load(['patient', 'requestedBy'])
            ),
        ], 201);
    }

    public function show(Request $request, PatientExport $patientExport)
    {
        if ($request->user()->clinic_id !== $patientExport->clinic_id) {
            abort(403);
        }

        $this->authorize('export', $patientExport->patient);

        $this->auditLogger->log(
            actor: $request->user(),
            action: AuditAction::PatientExportViewed,
            auditable: $patientExport,
            metadata: [
                'patient_export_id' => $patientExport->id,
                'patient_id' => $patientExport->patient_id,
            ],
            request: $request
        );

        return response()->json([
            'data' => new PatientExportResource(
                $patientExport->load(['patient', 'requestedBy'])
            ),
        ]);
    }
}
