<?php

namespace VentureDrake\LaravelCrm\Support;

use Closure;

/**
 * Menentukan "tenant" yang sedang aktif.
 *
 * - User biasa  : tenant_id milik user yang login  -> semua query difilter.
 * - Super admin : tenant_id user = NULL            -> TIDAK difilter (lihat semua).
 * - Console/job : tidak ada user login               -> TIDAK difilter, kecuali
 *                 dibungkus CurrentTenant::runAs($id, fn () => ...).
 */
class CurrentTenant
{
    protected static bool $overridden = false;

    protected static ?int $overrideId = null;

    public static function id(): ?int
    {
        if (static::$overridden) {
            return static::$overrideId;
        }

        if (! config('laravel-crm.tenant_tenancy', true)) {
            return null;
        }

        // hasUser() tidak memicu query user -> aman dari rekursi global scope pada model User.
        if (! auth()->hasUser()) {
            return null;
        }

        $id = auth()->user()->tenant_id ?? null;

        return $id ? (int) $id : null;
    }

    public static function isSuperAdmin(): bool
    {
        return auth()->hasUser() && empty(auth()->user()->tenant_id);
    }

    /**
     * Jalankan callback seolah-olah sebagai tenant tertentu (null = semua tenant).
     * Dipakai untuk queue job, scheduler, seeder, command.
     */
    public static function runAs(?int $tenantId, Closure $callback)
    {
        $prevOverridden = static::$overridden;
        $prevId = static::$overrideId;

        static::$overridden = true;
        static::$overrideId = $tenantId;

        try {
            return $callback();
        } finally {
            static::$overridden = $prevOverridden;
            static::$overrideId = $prevId;
        }
    }

    public static function withoutTenancy(Closure $callback)
    {
        return static::runAs(null, $callback);
    }
}
