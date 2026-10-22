<?php

use App\Support\ApiError;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        apiPrefix: 'api/v1',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Every API failure leaves in the contract's error shape (§3), and an
        // unexpected one never leaks its message: it is still reported to the
        // log as usual, but the client only sees a generic 500.
        $exceptions->render(function (Throwable $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            if ($e instanceof ValidationException) {
                return ApiError::response(422, 'VALIDATION_FAILED', 'The given data was invalid.', ['errors' => $e->errors()]);
            }

            if ($e instanceof ThrottleRequestsException) {
                return ApiError::response(429, 'RATE_LIMITED', 'Too many requests. Retry after the number of seconds in the Retry-After header.', headers: $e->getHeaders());
            }

            if ($e instanceof HttpExceptionInterface) {
                [$code, $message] = match ($e->getStatusCode()) {
                    404 => ['NOT_FOUND', 'No such endpoint.'],
                    405 => ['METHOD_NOT_ALLOWED', 'That HTTP method is not supported on this endpoint.'],
                    default => ['SERVER_ERROR', 'The request could not be processed.'],
                };

                return ApiError::response($e->getStatusCode(), $code, $message, headers: $e->getHeaders());
            }

            return ApiError::response(500, 'SERVER_ERROR', 'An unexpected error occurred.');
        });
    })->create();
