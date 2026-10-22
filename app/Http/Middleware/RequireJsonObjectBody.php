<?php

namespace App\Http\Middleware;

use App\Support\ApiError;
use Closure;
use Illuminate\Http\Request;
use JsonException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Laravel silently treats an unparseable JSON body as an empty one, which would
 * surface as a confusing wall of "field is required" errors. Reject it up front.
 */
class RequireJsonObjectBody
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->isJson()) {
            return ApiError::response(415, 'UNSUPPORTED_MEDIA_TYPE', 'Send the body as JSON with Content-Type: application/json.');
        }

        try {
            $decoded = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return ApiError::response(400, 'MALFORMED_JSON', 'The request body is not valid JSON.');
        }

        if (! is_array($decoded) || (array_is_list($decoded) && $decoded !== [])) {
            return ApiError::response(400, 'MALFORMED_JSON', 'The request body must be a JSON object.');
        }

        return $next($request);
    }
}
