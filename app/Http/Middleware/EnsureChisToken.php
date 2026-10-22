<?php

namespace App\Http\Middleware;

use App\Support\ApiError;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class EnsureChisToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = (string) config('chis.api_token');

        // Fail closed: an unset token must never mean "no authentication".
        if ($expected === '') {
            Log::error('CHIS_API_TOKEN is not set; refusing every authenticated request.');

            return ApiError::response(500, 'SERVER_ERROR', 'CHIS is not configured.');
        }

        $given = $request->bearerToken();

        if (! is_string($given) || ! hash_equals($expected, $given)) {
            return ApiError::response(401, 'UNAUTHENTICATED', 'Missing or invalid bearer token.', headers: ['WWW-Authenticate' => 'Bearer']);
        }

        return $next($request);
    }
}
