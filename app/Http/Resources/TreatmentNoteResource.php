<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TreatmentNoteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'appointment_id' => $this->appointment_id,
            'doctor_id' => $this->doctor_id,
            'patient_id' => $this->patient_id,
            'subjective' => $this->subjective,
            'objective' => $this->objective,
            'assessment' => $this->assessment,
            'plan' => $this->plan,
            'visibility' => $this->visibility?->value,
            'appointment' => new AppointmentResource($this->whenLoaded('appointment')),
            'doctor' => new DoctorResource($this->whenLoaded('doctor')),
            'patient' => new PatientResource($this->whenLoaded('patient')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
