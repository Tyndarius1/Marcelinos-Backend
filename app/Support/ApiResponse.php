<?php

namespace App\Support;

use Illuminate\Http\JsonResponse;

class ApiResponse
{
    /**
     * Return a success response with data.
     *
     * @param  array|object  $data
     * @param  string|null  $message
     * @param  int  $status
     */
    public static function success(mixed $data, ?string $message = null, int $status = 200): JsonResponse
    {
        $payload = ['success' => true];

        if ($message !== null) {
            $payload['message'] = $message;
        }

        $payload['data'] = $data;

        return response()->json($payload, $status);
    }

    /**
     * Return a success response with only a message (no data payload).
     *
     * @param  string  $message
     * @param  int  $status
     */
    public static function message(string $message, int $status = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
        ], $status);
    }

    /**
     * Return an error response.
     *
     * @param  string  $message
     * @param  int  $status
     * @param  array<string, array<int, string>>|null  $errors  Validation errors (field => messages)
     */
    public static function error(string $message, int $status = 400, ?array $errors = null): JsonResponse
    {
        $payload = [
            'success' => false,
            'message' => $message,
        ];

        if ($errors !== null) {
            $payload['errors'] = $errors;
        }

        return response()->json($payload, $status);
    }
}
