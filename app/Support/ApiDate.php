<?php

namespace App\Support;

use Carbon\CarbonInterface;
use DateTimeInterface;

class ApiDate
{
    public static function date(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof CarbonInterface) {
            return $value->toDateString();
        }

        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        if (is_string($value)) {
            return substr($value, 0, 10) ?: null;
        }

        return null;
    }

    public static function datetime(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof CarbonInterface) {
            return $value->toISOString();
        }

        if ($value instanceof DateTimeInterface) {
            return $value->format(DateTimeInterface::ATOM);
        }

        if (is_string($value)) {
            return $value;
        }

        return null;
    }
}
