<?php

namespace App\Http\Resources;

use App\Models\Appointment;
use App\Support\ApiDate;
use App\Support\ApiEnum;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Appointment
 */
class AppointmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'doctor_id' => $this->doctor_id,
            'patient_id' => $this->patient_id,
            'starts_at' => ApiDate::datetime($this->starts_at),
            'ends_at' => ApiDate::datetime($this->ends_at),
            'status' => ApiEnum::value($this->status),
            'reason' => $this->reason,
            'cancellation_reason' => $this->cancellation_reason,
            'cancelled_at' => ApiDate::datetime($this->cancelled_at),
            'completed_at' => ApiDate::datetime($this->completed_at),
            'doctor' => new DoctorResource($this->whenLoaded('doctor')),
            'patient' => new PatientResource($this->whenLoaded('patient')),
            'created_at' => ApiDate::datetime($this->created_at),
            'updated_at' => ApiDate::datetime($this->updated_at),
        ];
    }
}
