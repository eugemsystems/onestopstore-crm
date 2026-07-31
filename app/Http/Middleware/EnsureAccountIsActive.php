<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAccountIsActive
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        // If no user, let 'auth' handle it; if non-member, do nothing.
        if (!$user || !method_exists($user, 'hasRole') ) {
            return $next($request);
        }

        // Use your column; change 'account_status' if different.
        $status = $user->account_status ?? null;

        if ($status !== 'active') {
            // Avoid infinite loop by not redirecting when already on the notice route.
            if (!$request->routeIs('activation.notice')) {
                return redirect()->route('activation.notice');
            }
        }

        return $next($request);
    }
}
