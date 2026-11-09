<?php

namespace VentureDrake\LaravelCrm\Support;

use Illuminate\Support\Facades\DB;

/** Catatan aktivitas super admin (masuk sebagai tenant, buat/ubah/nonaktifkan tenant). */
class TenantAuditLog
{
    public static function record(string $action, ?int $tenantId = null, array $meta = []): void
    {
        try {
            DB::table('crm_tenant_audits')->insert([
                'user_id' => auth()->id(),
                'tenant_id' => $tenantId,
                'action' => $action,
                'meta' => $meta ? json_encode($meta) : null,
                'ip' => request()->ip(),
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            // log tidak boleh menggagalkan aksi utama
        }
    }
}
