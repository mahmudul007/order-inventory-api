<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class AddCorrelationId
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public const HEADER = 'X-Correlation-ID';

    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
        $id = $request->header(self::HEADER);

        if (! is_string($id) || ! preg_match('/^[A-Za-z0-9\-]{8,64}$/', $id)) {
            $id = (string) Str::uuid();
        }

        $request->headers->set(self::HEADER, $id);
        Log::withContext(['correlation_id' => $id, 'user_id' => $request->user()?->id]);

        $response = $next($request);
        $response->headers->set(self::HEADER, $id);

        return $response;
    }
}
