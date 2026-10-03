<?php

namespace App\Http\Middleware;

use App\Exceptions\IdempotencyConflictException;
use App\Models\IdempotencyKey;
use Closure;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * Same key + same payload  -> replay stored response.
 * Same key + diff payload  -> 422.
 * Same key still in flight -> 409 (Cache::lock).
 */
class IdempotencyMiddleware
{
    private const TTL_HOURS = 24;

    private const LOCK_SECONDS = 30;

    public function handle(Request $request, Closure $next): Response
    {
        $key = $request->header('Idempotency-Key');

        if (! is_string($key) || $key === '' || strlen($key) > 64) {
            throw IdempotencyConflictException::missing();
        }

        $userId = (int) $request->user()->id;
        $hash = hash('sha256', $request->method().'|'.$request->path().'|'.json_encode($request->all()));

        $lock = Cache::lock("idempotency:{$userId}:{$key}", self::LOCK_SECONDS);

        if (! $lock->get()) {
            throw IdempotencyConflictException::inFlight();
        }

        try {
            $record = IdempotencyKey::query()->where('user_id', $userId)->where('key', $key)->first();

            if ($record && $record->expires_at->isPast()) {
                $record->delete();
                $record = null;
            }

            if ($record) {
                if (! hash_equals($record->request_hash, $hash)) {
                    throw IdempotencyConflictException::payloadMismatch();
                }

                if ($record->status_code !== null) {
                    return response($record->response_body, $record->status_code)
                        ->header('Content-Type', 'application/json')
                        ->header('Idempotent-Replayed', 'true');
                }
            }

            try {
                $record ??= IdempotencyKey::create([
                    'user_id' => $userId,
                    'key' => $key,
                    'request_hash' => $hash,
                    'locked_at' => now(),
                    'expires_at' => now()->addHours(self::TTL_HOURS),
                ]);
            } catch (UniqueConstraintViolationException) {
                throw IdempotencyConflictException::inFlight();
            }

            $response = $next($request);

            /** Only persist definitive outcomes; 5xx may be retried with the same key. */
            if ($response->getStatusCode() < 500) {
                $record->update([
                    'status_code' => $response->getStatusCode(),
                    'response_body' => $response->getContent(),
                    'locked_at' => null,
                ]);
            } else {
                $record->delete();
            }

            return $response;
        } finally {
            $lock->release();
        }
    }
}
