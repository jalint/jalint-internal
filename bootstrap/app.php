<?php

use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Spatie\Permission\Exceptions\UnauthorizedException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
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
            'role' => Spatie\Permission\Middleware\RoleMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->renderable(function (AuthenticationException $e) {
            $req = request(); // ambil request dari helper global

            if ($req->expectsJson() || $req->is('api/*')) {
                return response()->json(['message' => 'Unauthenticated.'], 401);
            }

            return redirect('/');
        });

        $exceptions->render(function (HttpExceptionInterface $e, $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                // Tentukan level log
                $status = $e->getStatusCode();

                if ($status >= 500) {
                    Log::error('HTTP Exception', [
                        'status' => $status,
                        'message' => $e->getMessage(),
                        'url' => $request->fullUrl(),
                        'method' => $request->method(),
                        'ip' => $request->ip(),
                    ]);
                } else {
                    Log::info('HTTP Exception', [
                        'status' => $status,
                        'message' => $e->getMessage(),
                        'url' => $request->fullUrl(),
                    ]);
                }

                return response()->json([
                    'message' => $e->getMessage() ?: 'Bad Request',
                ], $e->getStatusCode());
            }
        });

        $exceptions->renderable(function (UnauthorizedException $e, $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'message' => 'You do not have permission to access this resource.',
                    'error' => 'FORBIDDEN',
                ], 403);
            }
        });

        // Handle ModelNotFoundException
        $exceptions->render(function (NotFoundHttpException $e, $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                // Extract model name

                // Extract ID dari message
                preg_match('/\[.*\]\s+(.+)/', $e->getMessage(), $matches);
                $id = $matches[1] ?? 'unknown';

                return response()->json([
                    'message' => 'Not Found',
                ], 404);
            }
        });

        $exceptions->render(function (QueryException $e, $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                // MySQL foreign key constraint violation

                Log::error('Database error', [
                    'code' => $e->getCode(),
                    'sql' => $e->getSql(),
                    'bindings' => $e->getBindings(),
                    'url' => $request->fullUrl(),
                ]);

                if (
                    $e->getCode() === '23000'
                    && str_contains($e->getMessage(), 'Cannot delete or update a parent row')
                ) {
                    return response()->json([
                        'message' => 'Data tidak dapat dihapus karena masih digunakan oleh data lain.',
                    ], 409); // 409 Conflict
                }
            }
        });
    })->create();
