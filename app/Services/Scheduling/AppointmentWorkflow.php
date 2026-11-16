<?php

namespace App\Services\Scheduling;

use App\Enums\AppointmentStatus;
use App\Enums\AuditAction;
use App\Jobs\SendAppointmentConfirmationEmail;
use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Serializes writes that affect a doctor's appointment state on the doctor row.
 * All participating writers must acquire the doctor lock before an appointment lock.
 */
final class AppointmentWorkflow
{
    public function __construct(
        private readonly AvailabilityService $availability,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function book(User $actor, array $data, Request $request): Appointment
    {
        return DB::transaction(function () use ($actor, $data, $request): Appointment {
            // A stable, existing row is required: locking overlapping appointment rows
            // cannot protect an empty time range against a concurrent insert.
            $doctor = Doctor::query()
                ->where('clinic_id', $actor->clinic_id)
                ->lockForUpdate()
                ->findOrFail($data['doctor_id']);

            // Recheck clinic ownership and soft-deletion after any lock wait.
            Patient::query()
                ->where('clinic_id', $actor->clinic_id)
                ->lockForUpdate()
                ->findOrFail($data['patient_id']);

            $startsAt = CarbonImmutable::parse($data['starts_at'], $this->timezone($actor))
                ->setTimezone($this->timezone($actor));
            $endsAt = $startsAt->addMinutes($doctor->appointment_duration_minutes);

            $this->ensureAvailable($doctor, $startsAt, $endsAt);

            $appointment = Appointment::create([
                'clinic_id' => $actor->clinic_id,
                'doctor_id' => $doctor->id,
                'patient_id' => $data['patient_id'],
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'status' => AppointmentStatus::Scheduled,
                'reason' => $data['reason'] ?? null,
            ]);

            $this->log($actor, AuditAction::AppointmentCreated, $appointment, [
                'appointment_id' => $appointment->id,
                'doctor_id' => $doctor->id,
                'patient_id' => $appointment->patient_id,
                'starts_at' => $appointment->starts_at?->toISOString(),
                'ends_at' => $appointment->ends_at?->toISOString(),
            ], $request);

            return $appointment;
        }, 3);
    }

    public function update(User $actor, Appointment $appointment, array $data, Request $request): Appointment
    {
        return $this->withLockedAppointment($actor, $appointment, 'update', function (Appointment $locked) use ($actor, $data, $request): Appointment {
            $this->ensureModifiable($locked);

            $before = $this->snapshot($locked);
            $locked->update($data);
            $after = $this->snapshot($locked->fresh());
            $changed = array_keys(array_filter(
                $before,
                static fn ($value, $field): bool => $value !== $after[$field],
                ARRAY_FILTER_USE_BOTH
            ));

            $this->log($actor, AuditAction::AppointmentUpdated, $locked, [
                'appointment_id' => $locked->id,
                'changed_fields' => $changed,
            ], $request);

            return $locked;
        });
    }

    public function confirm(User $actor, Appointment $appointment, Request $request): Appointment
    {
        return $this->withLockedAppointment($actor, $appointment, 'confirm', function (Appointment $locked) use ($actor, $request): Appointment {
            $this->ensureModifiable($locked);
            if ($locked->status === AppointmentStatus::Confirmed) {
                return $locked;
            }

            $locked->update(['status' => AppointmentStatus::Confirmed]);
            $this->log($actor, AuditAction::AppointmentConfirmed, $locked, [
                'appointment_id' => $locked->id,
            ], $request);

            // Do not deliver a confirmation for a transaction that might roll back.
            SendAppointmentConfirmationEmail::dispatch($locked)->afterCommit();

            return $locked;
        });
    }

    public function cancel(User $actor, Appointment $appointment, array $data, Request $request): Appointment
    {
        return $this->withLockedAppointment($actor, $appointment, 'cancel', function (Appointment $locked) use ($actor, $data, $request): Appointment {
            $this->ensureModifiable($locked);
            $locked->update([
                'status' => AppointmentStatus::Cancelled,
                'cancellation_reason' => $data['cancellation_reason'] ?? null,
                'cancelled_at' => now(),
            ]);
            $this->log($actor, AuditAction::AppointmentCancelled, $locked, [
                'appointment_id' => $locked->id,
                'reason_provided' => filled($data['cancellation_reason'] ?? null),
            ], $request);

            return $locked;
        });
    }

    public function complete(User $actor, Appointment $appointment, Request $request): Appointment
    {
        return $this->withLockedAppointment($actor, $appointment, 'complete', function (Appointment $locked) use ($actor, $request): Appointment {
            $this->ensureModifiable($locked);
            if ($locked->starts_at->isFuture()) {
                throw ValidationException::withMessages([
                    'appointment' => ['You cannot complete an appointment before it starts.'],
                ]);
            }

            $locked->update([
                'status' => AppointmentStatus::Completed,
                'completed_at' => now(),
            ]);
            $this->log($actor, AuditAction::AppointmentCompleted, $locked, [
                'appointment_id' => $locked->id,
            ], $request);

            return $locked;
        });
    }

    public function markNoShow(User $actor, Appointment $appointment, Request $request): Appointment
    {
        return $this->withLockedAppointment($actor, $appointment, 'markNoShow', function (Appointment $locked) use ($actor, $request): Appointment {
            $this->ensureModifiable($locked);
            if ($locked->starts_at->isFuture()) {
                throw ValidationException::withMessages([
                    'appointment' => ['You cannot mark a future appointment as no-show.'],
                ]);
            }

            $locked->update(['status' => AppointmentStatus::NoShow]);
            $this->log($actor, AuditAction::AppointmentNoShowMarked, $locked, [
                'appointment_id' => $locked->id,
            ], $request);

            return $locked;
        });
    }

    public function reschedule(User $actor, Appointment $appointment, array $data, Request $request): Appointment
    {
        return $this->withLockedAppointment($actor, $appointment, 'reschedule', function (Appointment $locked, Doctor $doctor) use ($actor, $data, $request): Appointment {
            $this->ensureModifiable($locked);

            $oldStartsAt = $locked->starts_at;
            $oldEndsAt = $locked->ends_at;
            $startsAt = CarbonImmutable::parse($data['starts_at'], $this->timezone($actor))
                ->setTimezone($this->timezone($actor));
            $endsAt = $startsAt->addMinutes($doctor->appointment_duration_minutes);

            $this->ensureAvailable($doctor, $startsAt, $endsAt, $locked->id);

            $locked->update([
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'status' => AppointmentStatus::Scheduled,
            ]);
            $fresh = $locked->fresh();
            $this->log($actor, AuditAction::AppointmentRescheduled, $fresh, [
                'appointment_id' => $locked->id,
                'old_starts_at' => $oldStartsAt?->toISOString(),
                'old_ends_at' => $oldEndsAt?->toISOString(),
                'new_starts_at' => $fresh->starts_at?->toISOString(),
                'new_ends_at' => $fresh->ends_at?->toISOString(),
            ], $request);

            return $fresh;
        });
    }

    /** @param Closure(Appointment, Doctor): Appointment $callback */
    private function withLockedAppointment(User $actor, Appointment $appointment, string $ability, Closure $callback): Appointment
    {
        return DB::transaction(function () use ($actor, $appointment, $ability, $callback): Appointment {
            $doctor = Doctor::query()
                ->where('clinic_id', $actor->clinic_id)
                ->lockForUpdate()
                ->findOrFail($appointment->doctor_id);

            // Never trust the possibly stale route-bound appointment after waiting for a lock.
            $fresh = Appointment::query()
                ->where('clinic_id', $actor->clinic_id)
                ->where('doctor_id', $doctor->id)
                ->lockForUpdate()
                ->findOrFail($appointment->id);

            Gate::forUser($actor)->authorize($ability, $fresh);

            return $callback($fresh, $doctor);
        }, 3);
    }

    private function ensureAvailable(Doctor $doctor, CarbonImmutable $startsAt, CarbonImmutable $endsAt, ?int $ignoreId = null): void
    {
        if (! $this->availability->isDoctorAvailable($doctor, $startsAt, $endsAt, $ignoreId)) {
            throw ValidationException::withMessages([
                'starts_at' => ['The selected doctor is not available at this time.'],
            ]);
        }
    }

    private function ensureModifiable(Appointment $appointment): void
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

    private function timezone(User $actor): string
    {
        return $actor->clinic?->timezone ?? config('app.timezone');
    }

    private function snapshot(Appointment $appointment): array
    {
        return [
            'reason' => $appointment->reason,
            'status' => $appointment->status?->value,
        ];
    }

    private function log(User $actor, AuditAction $action, Appointment $appointment, array $metadata, Request $request): void
    {
        $this->auditLogger->log(
            actor: $actor,
            action: $action,
            auditable: $appointment,
            metadata: $metadata,
            request: $request,
        );
    }
}
