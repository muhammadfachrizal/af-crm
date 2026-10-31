<?php

namespace VentureDrake\LaravelCrm\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use VentureDrake\LaravelCrm\Models\Tenant;

/**
 * Tolak request API kalau perusahaan (tenant) user nonaktif / langganannya habis.
 * Super admin (tenant_id NULL) selalu lolos.
 */
class EnsureTenantActive
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if ($user && ! empty($user->tenant_id)) {
            $tenant = Tenant::find($user->tenant_id);

            if (! $tenant || ! $tenant->isUsable()) {
                return response()->json([
                    'message' => 'Akun perusahaan Anda nonaktif atau masa langganan telah berakhir. Hubungi administrator.',
                    'code' => 'tenant_inactive',
                ], 403);
            }
        }

        return $next($request);
    }
}
