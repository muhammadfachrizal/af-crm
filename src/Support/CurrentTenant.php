<?php

namespace VentureDrake\LaravelCrm\Support;

use Closure;
use Illuminate\Database\Eloquent\Model;

/**
 * Menentukan tenant yang sedang aktif. Urutan prioritas:
 *  1. runAs()/withoutTenancy()        -> override eksplisit (queue job, seeder, command)
 *  2. user login                       -> users.tenant_id
 *     - super admin (users.is_super_admin) boleh "masuk sebagai" tenant lain: session
 *       acting_tenant_id (web) atau header X-Tenant-Id (API). Default = tenant miliknya sendiri.
 *  3. request publik (tanpa login)     -> tenant dari model pertama yang terbaca (widget chat,
 *                                         quote/invoice portal, tracking email, dst.)
 *  4. console tanpa konteks            -> null = tidak difilter
 *
 * Super admin TIDAK lagi punya tenant_id NULL: data yang dibuatnya selalu milik tenant aktif.
 */
class CurrentTenant
{
    protected static bool $overridden = false;

    protected static ?int $overrideId = null;

    protected static ?int $actingId = null;

    protected static ?int $publicId = null;

    public static function id(): ?int
    {
        if (static::$overridden) {
            return static::$overrideId;
        }

        if (! config('laravel-crm.tenancy', true)) {
            return null;
        }

        // hasUser() tidak memicu query user -> aman dari rekursi global scope pada model User.
        if (! auth()->hasUser()) {
            return static::$publicId;
        }

        $user = auth()->user();

        if (! empty($user->is_super_admin)) {
            $acting = static::$actingId ?: static::sessionActing();

            if ($acting) {
                return (int) $acting;
            }
        }

        return ! empty($user->tenant_id) ? (int) $user->tenant_id : null;
    }

    public static function isSuperAdmin(): bool
    {
        return auth()->hasUser() && ! empty(auth()->user()->is_super_admin);
    }

    /** Apakah super admin sedang bekerja di tenant yang bukan miliknya? */
    public static function isActingAsOther(): bool
    {
        return static::isSuperAdmin() && static::id() !== null && static::id() !== (int) auth()->user()->tenant_id;
    }

    public static function actAs(?int $tenantId): void
    {
        static::$actingId = $tenantId;
    }

    protected static function sessionActing(): ?int
    {
        try {
            if (app()->bound('request') && request()->hasSession()) {
                $id = request()->session()->get('acting_tenant_id');

                return $id ? (int) $id : null;
            }
        } catch (\Throwable $e) {
            // tanpa session (API stateless)
        }

        return null;
    }

    /** Dipanggil trait saat model terbaca: request publik mengambil tenant dari data yang diakses. */
    public static function observeRetrieved(Model $model): void
    {
        if (static::$overridden || static::$publicId !== null || app()->runningInConsole() || auth()->hasUser()) {
            return;
        }

        if (! empty($model->tenant_id)) {
            static::$publicId = (int) $model->tenant_id;
        }
    }

    public static function clearContext(): void
    {
        static::$publicId = null;
        static::$actingId = null;
    }

    /** Jalankan callback sebagai tenant tertentu (null = semua tenant). Untuk job/scheduler/seeder. */
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

    /** Dipakai worker queue: pasang/lepas konteks tenant selama satu job. */
    public static function setJobContext(?int $tenantId): void
    {
        static::$overridden = true;
        static::$overrideId = $tenantId;
    }

    public static function clearJobContext(): void
    {
        static::$overridden = false;
        static::$overrideId = null;
    }
}
