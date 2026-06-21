<?php

namespace Metalinked\LaravelSettingsKit\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SettingsKitApiAuth {
    public function handle(Request $request, Closure $next): Response {
        if (!config('settings-kit.api.enabled', false)) {
            return response()->json(['error' => 'API not enabled'], 404);
        }

        if (
            config('settings-kit.api.disable_auth_in_development', false) &&
            app()->environment(['local', 'testing'])
        ) {
            return $next($request);
        }

        $authMode = config('settings-kit.api.auth_mode', 'token');

        return match ($authMode) {
            'token' => $this->handleTokenAuth($request, $next),
            'sanctum' => $this->handleSanctumAuth($request, $next),
            'passport' => $this->handlePassportAuth($request, $next),
            default => response()->json(['error' => 'Invalid auth mode configured'], 500),
        };
    }

    protected function handleTokenAuth(Request $request, Closure $next): Response {
        $expectedToken = config('settings-kit.api.token');

        if (empty($expectedToken)) {
            return response()->json(['error' => 'API token not configured'], 500);
        }

        $token = $request->bearerToken() ?? '';

        if (!hash_equals($expectedToken, $token)) {
            return response()->json(['error' => 'Invalid or missing token'], 401);
        }

        return $next($request);
    }

    protected function handleSanctumAuth(Request $request, Closure $next): Response {
        if (!auth('sanctum')->check()) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        return $next($request);
    }

    protected function handlePassportAuth(Request $request, Closure $next): Response {
        if (!auth('api')->check()) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        return $next($request);
    }
}
