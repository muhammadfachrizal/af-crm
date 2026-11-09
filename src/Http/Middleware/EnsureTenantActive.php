<?php

namespace VentureDrake\LaravelCrm\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use VentureDrake\LaravelCrm\Models\Tenant;

/**
 * Tolak request API kalau perusahaan (tenant) user nonaktif / langganannya habis.
 * Super admin selalu lolos.
 */
class EnsureTenantActive
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        // Super admin boleh bekerja di tenant lain lewat header X-Tenant-Id (mis. untuk support).
        if ($user && ! empty($user->is_super_admin)) {
            if ($id = (int) $request->header('X-Tenant-Id')) {
                if (Tenant::find($id)) {
                    \VentureDrake\LaravelCrm\Support\CurrentTenant::actAs($id);
                }
            }

            return $next($request);
        }

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
