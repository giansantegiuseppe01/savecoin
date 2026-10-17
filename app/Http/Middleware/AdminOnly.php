<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminOnly
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()?->isAdmin()) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Acceso no autorizado. Solo administradores pueden acceder.',
                ], 403);
            }

            abort(403, 'Acceso no autorizado. Solo administradores pueden acceder.');
        }

        return $next($request);
    }
}
