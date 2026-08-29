<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Server-side ability check for a route.
 *
 * Hiding a sidebar link is presentation, not protection, so every guarded
 * route carries its required ability here as well. The full gate and policy
 * layer builds on top of this.
 */
class EnsurePermission
{
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $user = $request->user();

        if (! $user) {
            abort(403);
        }

        foreach ($permissions as $permission) {
            if ($user->hasPermission($permission)) {
                return $next($request);
            }
        }

        throw new AccessDeniedHttpException(
            'You do not have permission to perform this action.'
        );
    }
}
