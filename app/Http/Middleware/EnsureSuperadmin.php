<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSuperadmin
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()?->is_superadmin) {
            return response()->json([
                'success' => false,
                'message' => 'Forbidden: Akses khusus Superadmin.',
            ], 403);
        }

        return $next($request);
    }
}
