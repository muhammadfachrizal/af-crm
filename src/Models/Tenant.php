<?php

namespace VentureDrake\LaravelCrm\Models;

use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Master "Customer" (perusahaan pelanggan SaaS). Semua tabel CRM menyimpan tenant_id -> crm_tenants.id.
 * Bukan model Customer CRM (itu klien milik tiap perusahaan).
 */
class Tenant extends Model
{
    use SoftDeletes;

    protected $table = 'crm_tenants';

    protected $guarded = ['id'];

    protected $casts = [
        'starts_at' => 'date',
        'expires_at' => 'date',
        'attendance_enforce_radius' => 'boolean',
        'block_mock_location' => 'boolean',
        'bind_device' => 'boolean',
        'max_users' => 'integer',
    ];

    public function isUsable(): bool
    {
        if ($this->status !== 'active') {
            return false;
        }

        return ! $this->expires_at || $this->expires_at->endOfDay()->isFuture();
    }

    public function getLogoUrlAttribute(): ?string
    {
        return $this->logo_path ? asset('storage/'.$this->logo_path) : null;
    }
}
