<?php

namespace VentureDrake\LaravelCrm\Support;

use Closure;

/**
 * Menentukan "customer (tenant)" yang sedang aktif.
 *
 * - User biasa  : customer_id milik user yang login  -> semua query difilter.
 * - Super admin : customer_id user = NULL            -> TIDAK difilter (lihat semua).
 * - Console/job : tidak ada user login               -> TIDAK difilter, kecuali
 *                 dibungkus CurrentCustomer::runAs($id, fn () => ...).
 */
class CurrentCustomer
{
    protected static bool $overridden = false;

    protected static ?int $overrideId = null;

    public static function id(): ?int
    {
        if (static::$overridden) {
            return static::$overrideId;
        }

        if (! config('laravel-crm.customer_tenancy', true)) {
            return null;
        }

        // hasUser() tidak memicu query user -> aman dari rekursi global scope pada model User.
        if (! auth()->hasUser()) {
            return null;
        }

        $id = auth()->user()->customer_id ?? null;

        return $id ? (int) $id : null;
    }

    public static function isSuperAdmin(): bool
    {
        return auth()->hasUser() && empty(auth()->user()->customer_id);
    }

    /**
     * Jalankan callback seolah-olah sebagai customer tertentu (null = semua customer).
     * Dipakai untuk queue job, scheduler, seeder, command.
     */
    public static function runAs(?int $customerId, Closure $callback)
    {
        $prevOverridden = static::$overridden;
        $prevId = static::$overrideId;

        static::$overridden = true;
        static::$overrideId = $customerId;

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
