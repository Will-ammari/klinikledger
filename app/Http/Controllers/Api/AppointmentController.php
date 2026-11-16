<?php

namespace App\Http\Controllers\Api;

use App\Enums\AuditAction;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\CancelAppointmentRequest;
use App\Http\Requests\RescheduleAppointmentRequest;
use App\Http\Requests\StoreAppointmentRequest;
use App\Http\Requests\UpdateAppointmentRequest;
use App\Http\Resources\AppointmentResource;
use App\Models\Appointment;
use App\Services\Audit\AuditLogger;
use App\Services\Scheduling\AppointmentWorkflow;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** HTTP orchestration only: validation/authorization remain in Form Requests and Policies. */
class AppointmentController extends Controller
{
    public function __construct(
        private readonly AppointmentWorkflow $workflow,
        private readonly AuditLogger $auditLogger,
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

    public function store(StoreAppointmentRequest $request): JsonResponse
    {
        $appointment = $this->workflow->book($request->user(), $request->validated(), $request);

        return $this->respond($appointment, 201);
    }

    public function show(Request $request, Appointment $appointment): JsonResponse
    {
        $this->authorize('view', $appointment);

        $this->auditLogger->log(
            actor: $request->user(),
            action: AuditAction::AppointmentViewed,
            auditable: $appointment,
            metadata: ['appointment_id' => $appointment->id],
            request: $request,
        );

        return $this->respond($appointment);
    }

    public function update(UpdateAppointmentRequest $request, Appointment $appointment): JsonResponse
    {
        return $this->respond($this->workflow->update($request->user(), $appointment, $request->validated(), $request));
    }

    public function confirm(Request $request, Appointment $appointment): JsonResponse
    {
        $this->authorize('confirm', $appointment);

        return $this->respond($this->workflow->confirm($request->user(), $appointment, $request));
    }

    public function cancel(CancelAppointmentRequest $request, Appointment $appointment): JsonResponse
    {
        return $this->respond($this->workflow->cancel($request->user(), $appointment, $request->validated(), $request));
    }

    public function complete(Request $request, Appointment $appointment): JsonResponse
    {
        $this->authorize('complete', $appointment);

        return $this->respond($this->workflow->complete($request->user(), $appointment, $request));
    }

    public function markNoShow(Request $request, Appointment $appointment): JsonResponse
    {
        $this->authorize('markNoShow', $appointment);

        return $this->respond($this->workflow->markNoShow($request->user(), $appointment, $request));
    }

    public function reschedule(RescheduleAppointmentRequest $request, Appointment $appointment): JsonResponse
    {
        return $this->respond($this->workflow->reschedule($request->user(), $appointment, $request->validated(), $request));
    }

    private function respond(Appointment $appointment, int $status = 200): JsonResponse
    {
        return response()->json([
            'data' => new AppointmentResource($appointment->load(['doctor.user', 'patient'])),
        ], $status);
    }
}
