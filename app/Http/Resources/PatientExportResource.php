<?php

namespace App\Http\Resources;

use App\Models\PatientExport;
use App\Support\ApiDate;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PatientExport
 */
class PatientExportResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'patient_id' => $this->patient_id,
            'requested_by_user_id' => $this->requested_by_user_id,
            'generated_at' => ApiDate::datetime($this->generated_at),
            'payload' => $this->payload,
            'patient' => new PatientResource($this->whenLoaded('patient')),
            'requested_by' => new UserResource($this->whenLoaded('requestedBy')),
            'created_at' => ApiDate::datetime($this->created_at),
        ];
    }
}
