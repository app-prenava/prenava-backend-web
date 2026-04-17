<?php

namespace App\Http\Middleware;

use Illuminate\Http\Middleware\TrustProxies as Middleware;
use Illuminate\Http\Request;

class TrustProxies extends Middleware
{
    /**
     * The trusted proxies for this application.
     *
     * SECURITY: Set specific proxy IPs or load from environment.
     * Using '*' trusts ALL proxies which is a security risk.
     *
     * Options:
     * - null / false: No proxies trusted
     * - '*': Trust all proxies (NOT RECOMMENDED for production)
     * - Array of IPs: ['192.168.1.1', '10.0.0.1']
     * - Load from env: explode(',', env('TRUSTED_PROXIES', '*'))
     *
     * @var array|string|null
     */
    protected $proxies = null; // Default: no proxies trusted. Configure via env('TRUSTED_PROXIES') if using load balancers like Cloudflare/Vercel

    /**
     * The headers that should be used to detect proxies.
     *
     * @var int
     */
    protected $headers =
        Request::HEADER_X_FORWARDED_FOR |
        Request::HEADER_X_FORWARDED_HOST |
        Request::HEADER_X_FORWARDED_PORT |
        Request::HEADER_X_FORWARDED_PROTO |
        Request::HEADER_X_FORWARDED_AWS_ELB; // Add AWS ELB support if needed

    /**
     * Get the trusted proxies.
     *
     * @return array|string|null
     */
    protected function getTrustedProxies()
    {
        // Allow configuration via environment variable
        $envProxies = env('TRUSTED_PROXIES');

        if ($envProxies) {
            if ($envProxies === '*') {
                return '*';
            }

            return array_map('trim', explode(',', $envProxies));
        }

        return $this->proxies;
    }
}
