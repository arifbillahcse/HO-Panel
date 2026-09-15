<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class ImpersonateMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request):Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (session()->has('impersonating')) {
            // "admin/*" alone does not match the bare "/admin" path (the
            // dashboard itself has nothing after the slash) — same gap as
            // BasePolicy::adminPermission() had, and with the same effect
            // here: landing on the dashboard exactly would skip clearing
            // impersonation and instead re-apply it, silently switching the
            // request to the impersonated customer, who then gets a 403
            // from the panel's own access check.
            if ($request->is('admin') || $request->is('admin/*')) {
                // Unset session
                session()->forget('impersonating');
            } else {
                // Validate if admin user still has permission to impersonate
                $adminUser = Auth::user();
                if (!$adminUser || !$adminUser->hasPermission('admin.users.impersonate')) {
                    session()->forget('impersonating');

                    return $next($request);
                }

                $targetUser = User::find(session('impersonating'));
                if (!$targetUser) {
                    session()->forget('impersonating');

                    return $next($request);
                }

                Auth::onceUsingId($targetUser->id);
            }
        }

        return $next($request);
    }
}
