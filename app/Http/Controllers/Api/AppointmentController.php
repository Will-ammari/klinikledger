<?php

namespace App\Http\Controllers\Api;

use App\Enums\AppointmentStatus;
use App\Enums\AuditAction;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\CancelAppointmentRequest;
use App\Http\Requests\RescheduleAppointmentRequest;
use App\Http\Requests\StoreAppointmentRequest;
use App\Http\Requests\UpdateAppointmentRequest;
use App\Http\Resources\AppointmentResource;
use App\Jobs\SendAppointmentConfirmationEmail;
use App\Models\Appointment;
use App\Models\Doctor;
use App\Services\Audit\AuditLogger;
use App\Services\Scheduling\AvailabilityService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AppointmentController extends Controller
{
    public function __construct(
        private readonly AvailabilityService $availabilityService,
        private readonly AuditLogger $auditLogger
    ) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', Appointment::class);

        $appointments = Appointment::query()
            ->with(['doctor.user', 'patient'])
            ->where('clinic_id', $request->user()->clinic_id)
            ->when($request->user()->role === UserRole::Doctor, function ($query) use ($request) {
                $query->whereHas('doctor', function ($query) use ($request) {
                    $query->where('user_id', $request->user()->id);
                });
            })
            ->when($request->filled('doctor_id'), function ($query) use ($request) {
                $query->where('doctor_id', $request->integer('doctor_id'));
            })
            ->when($request->filled('patient_id'), function ($query) use ($request) {
                $query->where('patient_id', $request->integer('patient_id'));
            })
            ->when($request->filled('status'), function ($query) use ($request) {
                $query->where('status', $request->string('status')->toString());
            })
            ->when($request->filled('date'), function ($query) use ($request) {
                $query->whereDate('starts_at', $request->date('date'));
            })
            ->orderBy('starts_at')
            ->paginate($this->perPage($request));

        return AppointmentResource::collection($appointments);
    }

    public function store(StoreAppointmentRequest $request)
    {
        $validated = $request->validated();

        $doctor = Doctor::query()
            ->where('clinic_id', $request->user()->clinic_id)
            ->findOrFail($validated['doctor_id']);

        $timezone = $request->user()->clinic?->timezone ?? config('app.timezone');

        $startsAt = CarbonImmutable::parse($validated['starts_at'], $timezone);
        $endsAt = $startsAt->addMinutes($doctor->appointment_duration_minutes);

        if (! $this->availabilityService->isDoctorAvailable($doctor, $startsAt, $endsAt)) {
            throw ValidationException::withMessages([
                'starts_at' => ['The selected doctor is not available at this time.'],
            ]);
        }

        $appointment = Appointment::create([
            'clinic_id' => $request->user()->clinic_id,
            'doctor_id' => $doctor->id,
            'patient_id' => $validated['patient_id'],
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'status' => AppointmentStatus::Scheduled,
            'reason' => $validated['reason'] ?? null,
        ]);

        $this->auditLogger->log(
            actor: $request->user(),
            action: AuditAction::AppointmentCreated,
            auditable: $appointment,
            metadata: [
                'appointment_id' => $appointment->id,
                'doctor_id' => $appointment->doctor_id,
                'patient_id' => $appointment->patient_id,
                'starts_at' => $appointment->starts_at?->toISOString(),
                'ends_at' => $appointment->ends_at?->toISOString(),
            ],
            request: $request
        );

        return response()->json([
            'data' => new AppointmentResource($appointment->load(['doctor.user', 'patient'])),
        ], 201);
    }

    public function show(Request $request, Appointment $appointment)
    {
        $this->authorize('view', $appointment);

        $this->auditLogger->log(
            actor: $request->user(),
            action: AuditAction::AppointmentViewed,
            auditable: $appointment,
            metadata: [
                'appointment_id' => $appointment->id,
            ],
            request: $request
        );

        return response()->json([
            'data' => new AppointmentResource($appointment->load(['doctor.user', 'patient'])),
        ]);
    }

    public function update(UpdateAppointmentRequest $request, Appointment $appointment)
    {
        $this->ensureAppointmentCanBeModified($appointment);

        $before = $this->normalizeAppointmentSnapshot($appointment);

        $appointment->update($request->validated());

        $appointment->refresh();

        $after = $this->normalizeAppointmentSnapshot($appointment);

        $changedFields = collect($before)
            ->filter(function ($oldValue, string $field) use ($after) {
                return $oldValue !== ($after[$field] ?? null);
            })
            ->keys()
            ->values()
            ->all();

        $this->auditLogger->log(
            actor: $request->user(),
            action: AuditAction::AppointmentUpdated,
            auditable: $appointment,
            metadata: [
                'appointment_id' => $appointment->id,
                'changed_fields' => $changedFields,
            ],
            request: $request
        );

        return response()->json([
            'data' => new AppointmentResource($appointment->load(['doctor.user', 'patient'])),
        ]);
    }

    public function confirm(Request $request, Appointment $appointment)
    {
        $this->authorize('confirm', $appointment);
        $this->ensureAppointmentCanBeModified($appointment);

        $appointment->update([
            'status' => AppointmentStatus::Confirmed,
        ]);

        SendAppointmentConfirmationEmail::dispatch($appointment->fresh());

        $this->auditLogger->log(
            actor: $request->user(),
            action: AuditAction::AppointmentConfirmed,
            auditable: $appointment,
            metadata: [
                'appointment_id' => $appointment->id,
            ],
            request: $request
        );

        return response()->json([
            'data' => new AppointmentResource($appointment->fresh()->load(['doctor.user', 'patient'])),
        ]);
    }

    public function cancel(CancelAppointmentRequest $request, Appointment $appointment)
    {
        $this->ensureAppointmentCanBeModified($appointment);

        $appointment->update([
            'status' => AppointmentStatus::Cancelled,
            'cancellation_reason' => $request->validated()['cancellation_reason'] ?? null,
            'cancelled_at' => now(),
        ]);

        $this->auditLogger->log(
            actor: $request->user(),
            action: AuditAction::AppointmentCancelled,
            auditable: $appointment,
            metadata: [
                'appointment_id' => $appointment->id,
                'reason_provided' => $request->filled('cancellation_reason'),
            ],
            request: $request
        );

        return response()->json([
            'data' => new AppointmentResource($appointment->fresh()->load(['doctor.user', 'patient'])),
        ]);
    }

    public function complete(Request $request, Appointment $appointment)
    {
        $this->authorize('complete', $appointment);
        $this->ensureAppointmentCanBeModified($appointment);

        if ($appointment->starts_at->isFuture()) {
            throw ValidationException::withMessages([
                'appointment' => ['You cannot complete an appointment before it starts.'],
            ]);
        }

        $appointment->update([
            'status' => AppointmentStatus::Completed,
            'completed_at' => now(),
        ]);

        $this->auditLogger->log(
            actor: $request->user(),
            action: AuditAction::AppointmentCompleted,
            auditable: $appointment,
            metadata: [
                'appointment_id' => $appointment->id,
            ],
            request: $request
        );

        return response()->json([
            'data' => new AppointmentResource($appointment->fresh()->load(['doctor.user', 'patient'])),
        ]);
    }

    public function markNoShow(Request $request, Appointment $appointment)
    {
        $this->authorize('markNoShow', $appointment);
        $this->ensureAppointmentCanBeModified($appointment);

        if ($appointment->starts_at->isFuture()) {
            throw ValidationException::withMessages([
                'appointment' => ['You cannot mark a future appointment as no-show.'],
            ]);
        }

        $appointment->update([
            'status' => AppointmentStatus::NoShow,
        ]);

        $this->auditLogger->log(
            actor: $request->user(),
            action: AuditAction::AppointmentNoShowMarked,
            auditable: $appointment,
            metadata: [
                'appointment_id' => $appointment->id,
            ],
            request: $request
        );

        return response()->json([
            'data' => new AppointmentResource($appointment->fresh()->load(['doctor.user', 'patient'])),
        ]);
    }

    public function reschedule(RescheduleAppointmentRequest $request, Appointment $appointment)
    {
        $this->ensureAppointmentCanBeModified($appointment);

        $timezone = $request->user()->clinic?->timezone ?? config('app.timezone');

        $oldStartsAt = $appointment->starts_at;
        $oldEndsAt = $appointment->ends_at;

        $startsAt = CarbonImmutable::parse($request->validated()['starts_at'], $timezone);
        $endsAt = $startsAt->addMinutes($appointment->doctor->appointment_duration_minutes);

        if (! $this->availabilityService->isDoctorAvailable(
            doctor: $appointment->doctor,
            startsAt: $startsAt,
            endsAt: $endsAt,
            ignoreAppointmentId: $appointment->id
        )) {
            throw ValidationException::withMessages([
                'starts_at' => ['The selected doctor is not available at this time.'],
            ]);
        }

        $appointment->update([
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'status' => AppointmentStatus::Scheduled,
        ]);

        $this->auditLogger->log(
            actor: $request->user(),
            action: AuditAction::AppointmentRescheduled,
            auditable: $appointment,
            metadata: [
                'appointment_id' => $appointment->id,
                'old_starts_at' => $oldStartsAt?->toISOString(),
                'old_ends_at' => $oldEndsAt?->toISOString(),
                'new_starts_at' => $appointment->fresh()->starts_at?->toISOString(),
                'new_ends_at' => $appointment->fresh()->ends_at?->toISOString(),
            ],
            request: $request
        );

        return response()->json([
            'data' => new AppointmentResource($appointment->fresh()->load(['doctor.user', 'patient'])),
        ]);
    }

    private function ensureAppointmentCanBeModified(Appointment $appointment): void
    {
        if (in_array($appointment->status, [
            AppointmentStatus::Cancelled,
            AppointmentStatus::Completed,
            AppointmentStatus::NoShow,
        ], true)) {
            throw ValidationException::withMessages([
                'appointment' => ['This appointment can no longer be modified.'],
            ]);
        }
    }

    private function normalizeAppointmentSnapshot(Appointment $appointment): array
    {
        return [
            'reason' => $appointment->reason,
            'status' => $appointment->status?->value,
        ];
    }
}
