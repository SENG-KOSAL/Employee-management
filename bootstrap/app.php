<?php

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => \App\Http\Middleware\RoleMiddleware::class,
            'permission' => \App\Http\Middleware\PermissionMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(function (Request $request, \Throwable $e) {
            return $request->is('api/*') || $request->expectsJson();
        });

        $make = function (Request $request, \Throwable $e, int $status, string $message, array $extra = []) {
            $payload = array_merge([
                'ok' => false,
                'message' => $message,
                'status' => $status,
            ], $extra);

            if (config('app.debug')) {
                $payload['debug'] = array_filter([
                    'exception' => get_class($e),
                    'exception_message' => $e->getMessage(),
                ]);

                if ($e instanceof QueryException) {
                    $payload['debug']['sql'] = $e->getSql();
                }
            }

            return response()->json($payload, $status);
        };

        $exceptions->render(function (ValidationException $e, Request $request) use ($make) {
            return $make($request, $e, 422, 'Validation error', [
                'errors' => $e->errors(),
                'hint' => 'Check required fields and data types.',
            ]);
        });

        $exceptions->render(function (AuthenticationException $e, Request $request) use ($make) {
            return $make($request, $e, 401, $e->getMessage() ?: 'Unauthenticated', [
                'hint' => 'Send Bearer token in Authorization header.',
            ]);
        });

        $exceptions->render(function (AuthorizationException $e, Request $request) use ($make) {
            return $make($request, $e, 403, $e->getMessage() ?: 'Forbidden');
        });

        $exceptions->render(function (ModelNotFoundException|NotFoundHttpException $e, Request $request) use ($make) {
            return $make($request, $e, 404, 'Not found', [
                'hint' => 'Check the ID in the URL.',
            ]);
        });

        $exceptions->render(function (MethodNotAllowedHttpException $e, Request $request) use ($make) {
            $allowHeader = $e->getHeaders()['Allow'] ?? null;
            $allowed = $allowHeader
                ? array_values(array_filter(array_map('trim', explode(',', (string) $allowHeader))))
                : null;

            return $make($request, $e, 405, 'Method not allowed', [
                'allowed_methods' => $allowed,
                'hint' => 'Check HTTP method (GET/POST/PATCH) and endpoint path.',
            ]);
        });

        $exceptions->render(function (QueryException $e, Request $request) use ($make) {
            return $make($request, $e, 500, 'Database error', [
                'hint' => 'Run migrations and verify columns / foreign keys exist.',
            ]);
        });

        $exceptions->render(function (HttpExceptionInterface $e, Request $request) use ($make) {
            $status = $e->getStatusCode();
            $message = $e->getMessage() ?: 'Request error';

            return $make($request, $e, $status, $message);
        });

        $exceptions->render(function (\Throwable $e, Request $request) use ($make) {
            $message = config('app.debug') ? ($e->getMessage() ?: 'Server error') : 'Server error';

            return $make($request, $e, 500, $message, [
                'hint' => 'Check server logs for details.',
            ]);
        });
    })->create();
