<?php

namespace App\Support;

use Illuminate\Http\JsonResponse;

/**
 * The one error shape the contract allows (docs/API_CONTRACT.md §3):
 * { "message": ..., "code": ... }, plus "errors" for validation failures.
 */
class ApiError
{
    public static function response(int $status, string $code, string $message, array $extra = [], array $headers = []): JsonResponse
    {
        return response()->json(['message' => $message, 'code' => $code] + $extra, $status, $headers);
    }
}
