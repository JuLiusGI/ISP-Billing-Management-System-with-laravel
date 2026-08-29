<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Turns an account status change into an immediate loss of access.
 *
 * Checking only at login would leave a suspended user working normally until
 * their session expired, so the status is re-checked on every request.
 */
class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ! $user->status->canAuthenticate()) {
            $status = strtolower($user->status->label());

            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors([
                'email' => "This account is {$status} and can no longer sign in.",
            ]);
        }

        return $next($request);
    }
}
