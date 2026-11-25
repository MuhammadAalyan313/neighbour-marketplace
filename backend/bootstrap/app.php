<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )

    /*
    |--------------------------------------------------------------------------
    | Broadcasting Authorization
    |--------------------------------------------------------------------------
    |
    | Use Sanctum authentication for private Reverb channels.
    | Frontend authorization endpoint:
    |
    | POST /api/broadcasting/auth
    |
    */

    ->withBroadcasting(
        __DIR__ . '/../routes/channels.php',
        [
            'prefix' => 'api',
            'middleware' => [
                'api',
                'auth:sanctum',
                'active.user',
            ],
        ]
    )

    /*
    |--------------------------------------------------------------------------
    | Middleware Aliases
    |--------------------------------------------------------------------------
    */

    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'admin' => \App\Http\Middleware\EnsureAdmin::class,
            'seller' => \App\Http\Middleware\EnsureSeller::class,
            'active.user' => \App\Http\Middleware\EnsureActiveUser::class,
            'email.verified' => \App\Http\Middleware\EnsureEmailVerified::class,
        ]);
    })

    /*
    |--------------------------------------------------------------------------
    | API Exception Responses
    |--------------------------------------------------------------------------
    */

    ->withExceptions(function (Exceptions $exceptions): void {

        $exceptions->render(function (
            \Illuminate\Validation\ValidationException $e,
            \Illuminate\Http\Request $request
        ) {
            if (! $request->is('api/*')) {
                return null;
            }

            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $e->errors(),
            ], 422);
        });

        $exceptions->render(function (
            \Illuminate\Auth\AuthenticationException $e,
            \Illuminate\Http\Request $request
        ) {
            if (! $request->is('api/*')) {
                return null;
            }

            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        });

        $exceptions->render(function (
            \Symfony\Component\HttpKernel\Exception\NotFoundHttpException $e,
            \Illuminate\Http\Request $request
        ) {
            if (! $request->is('api/*')) {
                return null;
            }

            return response()->json([
                'success' => false,
                'message' => 'Resource not found.',
            ], 404);
        });

        $exceptions->render(function (
            \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $e,
            \Illuminate\Http\Request $request
        ) {
            if (! $request->is('api/*')) {
                return null;
            }

            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
                    ?: 'Request could not be processed.',
            ], $e->getStatusCode());
        });
    })
    ->create();
