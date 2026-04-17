<?php

namespace App\Http;

use Illuminate\Foundation\Http\Kernel as HttpKernel;

class Kernel extends HttpKernel
{
    /**
     * The application's global HTTP middleware stack.
     *
     * These middleware are run during every request to your application.
     */
    protected $middleware = [
        // \App\Http\Middleware\TrustProxies::class, // Moved below for proper proxy handling
        \App\Http\Middleware\SecurityHeaders::class, // Add security headers
        // \App\Http\Middleware\SanitizeInput::class, // Optional: uncomment to enable input sanitization
        \Illuminate\Http\Middleware\SetCacheHeaders::class,
        \Illuminate\Foundation\Http\Middleware\ValidatePostSize::class,
        \Illuminate\Routing\Middleware\SubstituteBindings::class,
        \Illuminate\Http\Middleware\HandleCors::class,
        \App\Http\Middleware\TrustProxies::class, // TrustProxies should be after HandleCors
    ];

    /**
     * The application's route middleware groups.
     */
    protected $middlewareGroups = [
        'web' => [
            \App\Http\Middleware\EncryptCookies::class,
            \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
            \Illuminate\Session\Middleware\StartSession::class,
            \Illuminate\View\Middleware\ShareErrorsFromSession::class,
            \App\Http\Middleware\VerifyCsrfToken::class,
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
        ],

        'api' => [
            \Fruitcake\Cors\HandleCors::class, // CORS middleware
            \App\Http\Middleware\SanitizeInput::class, // Sanitize API inputs
            'throttle:api',
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
        ],
    ];

    /**
     * The application's middleware aliases.
     *
     * Aliases may be used to assign middleware to routes and groups.
     */
    protected $middlewareAliases = [
        'auth' => \App\Http\Middleware\Authenticate::class,
        'jwt.auth'    => \Tymon\JWTAuth\Http\Middleware\Authenticate::class,
        'jwt.refresh' => \Tymon\JWTAuth\Http\Middleware\RefreshToken::class,
        'role'        => \App\Http\Middleware\RoleMiddleware::class,
        'security'    => \App\Http\Middleware\SecurityHeaders::class,
        'sanitize'    => \App\Http\Middleware\SanitizeInput::class,
    ];
}
