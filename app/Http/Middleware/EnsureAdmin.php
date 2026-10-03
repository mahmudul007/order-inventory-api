<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()?->isAdmin()) {
            return response()->json(['error' => [
                'code' => 'FORBIDDEN',
                'message' => 'Admin access required.',
                'details' => (object) [],
            ]], 403);
        }

        return $next($request);
    }
}
