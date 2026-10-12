<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveTenant
{
    public function handle(Request $request, Closure $next): Response {
        $tenantSlug = $request->header('X-Tenant');

        if (! is_string($tenantSlug) || ! preg_match('/\A[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\z/i', $tenantSlug)) {
            return response()->json([
                'message' => 'هدر X-Tenant معتبر نیست.',
            ], 400);
        }

        $tenant = Tenant::query()
            ->where('subdomain', strtolower($tenantSlug))
            ->first();

        if (! $tenant) {
            return response()->json([
                'message' => 'Tenant موردنظر پیدا نشد.',
            ], 404);
        }

        $request->attributes->set('tenant', $tenant);

        return $next($request);
    }
}