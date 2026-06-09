<?php

namespace App\Http\Controllers\Api;

use App\Enums\AuditAction;
use App\Enums\TreatmentNoteVisibility;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTreatmentNoteRequest;
use App\Http\Requests\UpdateTreatmentNoteRequest;
use App\Http\Resources\TreatmentNoteResource;
use App\Models\Appointment;
use App\Models\TreatmentNote;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\Request;

class TreatmentNoteController extends Controller
{
    public function __construct(
        private readonly AuditLogger $auditLogger
    ) {}

    public function index(Request $request, Appointment $appointment)
    {
        $this->authorize('viewAnyForAppointment', [TreatmentNote::class, $appointment]);

        $notes = TreatmentNote::query()
            ->with(['doctor.user', 'patient'])
            ->where('clinic_id', $request->user()->clinic_id)
            ->where('appointment_id', $appointment->id)
            ->latest()
            ->paginate($this->perPage($request));

        return TreatmentNoteResource::collection($notes);
    }

    public function store(StoreTreatmentNoteRequest $request, Appointment $appointment)
    {
        $validated = $request->validated();

        $note = TreatmentNote::create([
            'clinic_id' => $appointment->clinic_id,
            'appointment_id' => $appointment->id,
            'doctor_id' => $appointment->doctor_id,
            'patient_id' => $appointment->patient_id,
            'subjective' => $validated['subjective'] ?? null,
            'objective' => $validated['objective'] ?? null,
            'assessment' => $validated['assessment'] ?? null,
            'plan' => $validated['plan'] ?? null,
            'visibility' => TreatmentNoteVisibility::from(
                $validated['visibility'] ?? TreatmentNoteVisibility::DoctorOnly->value
            ),
        ]);

        $this->auditLogger->log(
            actor: $request->user(),
            action: AuditAction::TreatmentNoteCreated,
            auditable: $note,
            metadata: [
                'treatment_note_id' => $note->id,
                'appointment_id' => $appointment->id,
                'doctor_id' => $appointment->doctor_id,
                'patient_id' => $appointment->patient_id,
                'visibility' => $note->visibility?->value,
            ],
            request: $request
        );

        return response()->json([
            'data' => new TreatmentNoteResource(
                $note->load(['appointment', 'doctor.user', 'patient'])
            ),
        ], 201);
    }

    public function show(Request $request, TreatmentNote $treatmentNote)
    {
        $this->authorize('view', $treatmentNote);

        $this->auditLogger->log(
            actor: $request->user(),
            action: AuditAction::TreatmentNoteViewed,
            auditable: $treatmentNote,
            metadata: [
                'treatment_note_id' => $treatmentNote->id,
                'appointment_id' => $treatmentNote->appointment_id,
            ],
            request: $request
        );

        return response()->json([
            'data' => new TreatmentNoteResource(
                $treatmentNote->load(['appointment', 'doctor.user', 'patient'])
            ),
        ]);
    }

    public function update(UpdateTreatmentNoteRequest $request, TreatmentNote $treatmentNote)
    {
        $before = $this->normalizeNoteSnapshot($treatmentNote);

        $treatmentNote->update($request->validated());

        $treatmentNote->refresh();

        $after = $this->normalizeNoteSnapshot($treatmentNote);

        $changedFields = collect($before)
            ->filter(function ($oldValue, string $field) use ($after) {
                return $oldValue !== ($after[$field] ?? null);
            })
            ->keys()
            ->values()
            ->all();

        $this->auditLogger->log(
            actor: $request->user(),
            action: AuditAction::TreatmentNoteUpdated,
            auditable: $treatmentNote,
            metadata: [
                'treatment_note_id' => $treatmentNote->id,
                'changed_fields' => $changedFields,
            ],
            request: $request
        );

        return response()->json([
            'data' => new TreatmentNoteResource(
                $treatmentNote->load(['appointment', 'doctor.user', 'patient'])
            ),
        ]);
    }

    public function destroy(Request $request, TreatmentNote $treatmentNote)
    {
        $this->authorize('delete', $treatmentNote);

        $noteId = $treatmentNote->id;
        $appointmentId = $treatmentNote->appointment_id;

        $treatmentNote->delete();

        $this->auditLogger->log(
            actor: $request->user(),
            action: AuditAction::TreatmentNoteDeleted,
            auditable: $treatmentNote,
            metadata: [
                'treatment_note_id' => $noteId,
                'appointment_id' => $appointmentId,
            ],
            request: $request
        );

        return response()->json([
            'message' => 'Treatment note deleted successfully.',
        ]);
    }

    private function normalizeNoteSnapshot(TreatmentNote $treatmentNote): array
    {
        return [
            'subjective' => $treatmentNote->subjective,
            'objective' => $treatmentNote->objective,
            'assessment' => $treatmentNote->assessment,
            'plan' => $treatmentNote->plan,
            'visibility' => $treatmentNote->visibility?->value,
        ];
    }
}
