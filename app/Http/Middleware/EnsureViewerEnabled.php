<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards the /viewer pages. They show medical data, so they are hidden (404)
 * unless CHIS_VIEWER_ENABLED is on, and outside APP_ENV=local they also need
 * CHIS_VIEWER_PASSWORD via HTTP Basic auth.
 */
class EnsureViewerEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(config('chis.viewer_enabled'), 404);

        $password = (string) config('chis.viewer_password');

        if ($password === '') {
            abort_unless(app()->environment('local'), 404);

            return $next($request);
        }

        $given = $request->getPassword();

        if (! is_string($given) || ! hash_equals($password, $given)) {
            return response('Authentication required.', 401, ['WWW-Authenticate' => 'Basic realm="CHIS Simulator"']);
        }

        return $next($request);
    }
}
