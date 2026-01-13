<?php

use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Spatie\Permission\Exceptions\UnauthorizedException;
use Symfony\Component\HttpKernel\Exception\HttpException;
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
        /*
        |--------------------------------------------------------------------------
        | Authentication
        |--------------------------------------------------------------------------
        */
        $exceptions->renderable(function (AuthenticationException $e, $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'message' => 'Unauthenticated.',
                ], 401);
            }
        });

        /*
        |--------------------------------------------------------------------------
        | Authorization (Spatie)
        |--------------------------------------------------------------------------
        */
        $exceptions->renderable(function (UnauthorizedException $e, $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'message' => 'You do not have permission to access this resource.',
                ], 403);
            }
        });

        /*
        |--------------------------------------------------------------------------
        | Model not found (findOrFail)
        |--------------------------------------------------------------------------
        */
        $exceptions->render(function (ModelNotFoundException $e, $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'message' => 'Resource not found',
                ], 404);
            }
        });

        $exceptions->render(function (NotFoundHttpException $e, $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'message' => 'Resource not found',
                ], 404);
            }
        });

        /*
        |--------------------------------------------------------------------------
        | Route not found
        |--------------------------------------------------------------------------
        */
        $exceptions->render(function (NotFoundHttpException $e, $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'message' => 'Endpoint not found',
                ], 404);
            }
        });

        /*
        |--------------------------------------------------------------------------
        | Database errors
        |--------------------------------------------------------------------------
        */
        $exceptions->render(function (QueryException $e, $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
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
                    ], 409);
                }

                return response()->json([
                    'message' => 'Database error',
                ], 500);
            }
        });

        /*
        |--------------------------------------------------------------------------
        | Other HTTP exceptions (exclude 404)
        |--------------------------------------------------------------------------
        */
        $exceptions->render(function (HttpException $e, $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                if ($e->getStatusCode() === 404) {
                    return null; // biarkan handler 404 di atas
                }

                Log::warning('HTTP exception', [
                    'status' => $e->getStatusCode(),
                    'message' => $e->getMessage(),
                    'url' => $request->fullUrl(),
                ]);

                return response()->json([
                    'message' => $e->getMessage() ?: 'Request error',
                ], $e->getStatusCode());
            }
        });
    })
    ->create();
