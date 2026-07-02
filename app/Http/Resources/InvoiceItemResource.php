<?php

namespace App\Http\Resources;

use App\Models\InvoiceItem;
use App\Support\ApiDate;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin InvoiceItem
 */
class InvoiceItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'description' => $this->description,
            'quantity' => $this->quantity,
            'unit_price' => $this->unit_price,
            'line_total' => $this->line_total,
            'created_at' => ApiDate::datetime($this->created_at),
            'updated_at' => ApiDate::datetime($this->updated_at),
        ];
    }
}
