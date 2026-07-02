<?php

namespace App\Support;

use Illuminate\Http\JsonResponse;

class ApiErrorResponse
{
    /**
     * @param  array<string, mixed>  $details
     * @param  array<string, string>  $headers
     */
    public static function make(
        string $code,
        string $message,
        int $status,
        array $details = [],
        ?string $requestId = null,
        array $headers = []
    ): JsonResponse {
        $payload = [
            'error' => [
                'code' => $code,
                'message' => $message,
                'details' => (object) $details,
            ],
        ];

        if ($requestId !== null && $requestId !== '') {
            $payload['error']['request_id'] = $requestId;
            $headers['X-Request-Id'] = $requestId;
        }

        return response()->json($payload, $status, $headers);
    }

    /**
     * @param  array<string, array<int, string>>  $errors
     */
    public static function validation(array $errors, ?string $requestId = null): JsonResponse
    {
        $headers = [];

        $payload = [
            'error' => [
                'code' => 'validation_failed',
                'message' => 'The given data was invalid.',
                'details' => [
                    'errors' => $errors,
                ],
            ],
            'errors' => $errors,
        ];

        if ($requestId !== null && $requestId !== '') {
            $payload['error']['request_id'] = $requestId;
            $headers['X-Request-Id'] = $requestId;
        }

        return response()->json($payload, 422, $headers);
    }
}
