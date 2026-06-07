<?php

namespace App\Services\Billing;

class InvoiceCalculator
{
    public function calculate(array $items, float $taxRate = 0): array
    {
        $subtotal = collect($items)->sum(function (array $item) {
            return round(
                ((float) $item['quantity']) * ((float) $item['unit_price']),
                2
            );
        });

        $tax = round($subtotal * ($taxRate / 100), 2);
        $total = round($subtotal + $tax, 2);

        return [
            'subtotal' => number_format($subtotal, 2, '.', ''),
            'tax' => number_format($tax, 2, '.', ''),
            'total' => number_format($total, 2, '.', ''),
        ];
    }

    public function lineTotal(array $item): string
    {
        $lineTotal = round(
            ((float) $item['quantity']) * ((float) $item['unit_price']),
            2
        );

        return number_format($lineTotal, 2, '.', '');
    }
}
