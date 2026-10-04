<?php

use App\Exceptions\ApiException;
use App\Http\Middleware\AddCorrelationId;
use App\Http\Middleware\EnsureAdmin;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

$error = fn (string $code, string $message, int $status, array $details = []): JsonResponse => response()->json([
    'error' => ['code' => $code, 'message' => $message, 'details' => (object) $details],
], $status);

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->api(prepend: [AddCorrelationId::class]);
        $middleware->alias(['admin' => EnsureAdmin::class]);
    })
    ->withExceptions(function (Exceptions $exceptions) use ($error): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->render(fn (ApiException $e) => $error($e->errorCode(), $e->getMessage(), $e->status(), $e->details()));

        $exceptions->render(fn (ValidationException $e) => $error('VALIDATION_FAILED', $e->getMessage(), 422, $e->errors()));

        $exceptions->render(fn (AuthenticationException $e) => $error('UNAUTHENTICATED', 'Unauthenticated.', 401));

        $exceptions->render(fn (AuthorizationException|AccessDeniedHttpException $e) => $error('FORBIDDEN', 'This action is unauthorized.', 403));

        $exceptions->render(fn (ModelNotFoundException|NotFoundHttpException $e) => $error('NOT_FOUND', 'Resource not found.', 404));

        $exceptions->render(fn (ThrottleRequestsException $e) => $error('TOO_MANY_REQUESTS', 'Too many requests.', 429)
            ->withHeaders($e->getHeaders()));

        $exceptions->render(function (HttpExceptionInterface $e, Request $request) use ($error) {
            if ($request->is('api/*')) {
                return $error('HTTP_ERROR', $e->getMessage() ?: 'HTTP error.', $e->getStatusCode());
            }

            return null;
        });
    })->create();
