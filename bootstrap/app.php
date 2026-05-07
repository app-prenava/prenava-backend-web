<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->validateCsrfTokens(
            // Specify the routes to exclude from CSRF protection
            except: ['*']
            );
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $isApiRequest = static function ($request): bool {
            $routeMiddleware = $request->route()?->gatherMiddleware() ?? [];

            return $request->expectsJson()
                || $request->is('api/*')
                || in_array('api', $routeMiddleware, true)
                || in_array('auth:api', $routeMiddleware, true);
        };

        $exceptions->shouldRenderJsonWhen(function ($request, \Throwable $e) {
            return $isApiRequest($request);
        });

        $exceptions->render(function (\Throwable $e, $request) {
            if (!$isApiRequest($request)) {
                return null;
            }

            if ($e instanceof ValidationException) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data tidak valid.',
                    'errors'  => $e->errors(),
                ], $e->status);
            }

            if ($e instanceof AuthenticationException) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized',
                ], 401);
            }

            if ($e instanceof HttpExceptionInterface) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage() ?: 'Terjadi kesalahan pada permintaan.',
                ], $e->getStatusCode());
            }

            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan server.',
            ], 500);
        });
    })->create();
