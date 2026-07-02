<?php

namespace App\Http\Resources;

use App\Models\Invoice;
use App\Support\ApiDate;
use App\Support\ApiEnum;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Invoice
 */
class InvoiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'patient_id' => $this->patient_id,
            'appointment_id' => $this->appointment_id,
            'status' => ApiEnum::value($this->status),
            'subtotal' => $this->subtotal,
            'tax' => $this->tax,
            'total' => $this->total,
            'due_date' => ApiDate::date($this->due_date),
            'issued_at' => ApiDate::datetime($this->issued_at),
            'paid_at' => ApiDate::datetime($this->paid_at),
            'cancelled_at' => ApiDate::datetime($this->cancelled_at),
            'patient' => new PatientResource($this->whenLoaded('patient')),
            'appointment' => new AppointmentResource($this->whenLoaded('appointment')),
            'items' => InvoiceItemResource::collection($this->whenLoaded('items')),
            'created_at' => ApiDate::datetime($this->created_at),
            'updated_at' => ApiDate::datetime($this->updated_at),
        ];
    }
}
