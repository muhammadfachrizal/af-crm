<?php

namespace VentureDrake\LaravelCrm\Traits;

use Illuminate\Database\Eloquent\Model;
use VentureDrake\LaravelCrm\Models\Tenant;
use VentureDrake\LaravelCrm\Scopes\TenantScope;
use VentureDrake\LaravelCrm\Support\CurrentTenant;

/**
 * Multi tenant (setara HasTenant di POS / BaseCustomerEntity di HRIS).
 *
 * @mixin Model
 */
trait BelongsToTenant
{
    public static function bootBelongsToTenant()
    {
        static::addGlobalScope(new TenantScope);

        static::creating(function (Model $model) {
            if (empty($model->tenant_id) && ($tenantId = CurrentTenant::id())) {
                $model->tenant_id = $tenantId;
            }
        });
    }

    // Sengaja bernama tenant(), bukan customer(): model Customer CRM (klien) sudah ada di package ini.
    public function tenant()
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }
}
