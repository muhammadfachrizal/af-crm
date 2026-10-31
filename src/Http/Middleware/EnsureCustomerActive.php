<?php

namespace VentureDrake\LaravelCrm\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use VentureDrake\LaravelCrm\Models\Tenant;

/**
 * Tolak request API kalau perusahaan (customer) user nonaktif / langganannya habis.
 * Super admin (customer_id NULL) selalu lolos.
 */
class EnsureCustomerActive
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if ($user && ! empty($user->customer_id)) {
            $tenant = Tenant::find($user->customer_id);

            if (! $tenant || ! $tenant->isUsable()) {
                return response()->json([
                    'message' => 'Akun perusahaan Anda nonaktif atau masa langganan telah berakhir. Hubungi administrator.',
                    'code' => 'customer_inactive',
                ], 403);
            }
        }

        return $next($request);
    }
}
