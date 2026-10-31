<?php

namespace VentureDrake\LaravelCrm\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use VentureDrake\LaravelCrm\Support\CurrentTenant;

/** Hanya super admin (users.tenant_id NULL) yang boleh membuka halaman manajemen tenant. */
class EnsureSuperAdmin
{
    public function handle(Request $request, Closure $next)
    {
        abort_unless(CurrentTenant::isSuperAdmin(), 403, 'Hanya Super Admin.');

        return $next($request);
    }
}
