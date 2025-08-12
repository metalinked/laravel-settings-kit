<?php

namespace Metalinked\LaravelSettingsKit\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SettingsKitApiAuth {
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response {
        // Check if API is enabled
        if (!config('settings-kit.api.enabled', false)) {
            return response()->json(['error' => 'API not enabled'], 404);
        }

        $authMode = config('settings-kit.api.auth_mode', 'token');

        switch ($authMode) {
            case 'token':
                return $this->handleTokenAuth($request, $next);
            case 'sanctum':
                return $this->handleSanctumAuth($request, $next);
            case 'passport':
                return $this->handlePassportAuth($request, $next);
            default:
                return response()->json(['error' => 'Invalid auth mode'], 500);
        }
    }

    /**
     * Handle token-based authentication.
     */
    protected function handleTokenAuth(Request $request, Closure $next): Response {
        $token = $request->bearerToken();
        $expectedToken = config('settings-kit.api.token');

        if (empty($expectedToken)) {
            return response()->json(['error' => 'API token not configured'], 500);
        }

        if ($token !== $expectedToken) {
            return response()->json(['error' => 'Invalid or missing token'], 401);
        }

        return $next($request);
    }

    /**
     * Handle Sanctum-based authentication.
     */
    protected function handleSanctumAuth(Request $request, Closure $next): Response {
        if (!auth('sanctum')->check()) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        return $next($request);
    }

    /**
     * Handle Passport-based authentication.
     */
    protected function handlePassportAuth(Request $request, Closure $next): Response {
        if (!auth('api')->check()) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        return $next($request);
    }
}
