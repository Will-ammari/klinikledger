<?php

namespace App\Http\Resources;

use App\Models\Consent;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Consent
 */
class ConsentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'patient_id' => $this->patient_id,
            'type' => $this->type?->value,
            'status' => $this->status?->value,
            'granted_at' => $this->granted_at?->toISOString(),
            'withdrawn_at' => $this->withdrawn_at?->toISOString(),
            'notes' => $this->notes,
            'patient' => new PatientResource($this->whenLoaded('patient')),
            'granted_by' => new UserResource($this->whenLoaded('grantedBy')),
            'withdrawn_by' => new UserResource($this->whenLoaded('withdrawnBy')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
