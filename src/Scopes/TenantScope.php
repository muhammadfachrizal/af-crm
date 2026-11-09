<?php

namespace VentureDrake\LaravelCrm\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use VentureDrake\LaravelCrm\Support\CurrentTenant;

/**
 * Global filter otomatis: WHERE <table>.tenant_id = <customer user yang login>.
 */
class TenantScope implements Scope
{
    public function apply(Builder $builder, Model $model)
    {
        $tenantId = CurrentTenant::id();

        if ($tenantId === null) {
            return; // console/job tanpa konteks tenant
        }

        $column = $model->getTable().'.tenant_id';

        $builder->where($column, $tenantId);
    }

    public function extend(Builder $builder)
    {
        // Model::allTenants()->... -> abaikan filter (khusus super admin / sistem)
        $builder->macro('allTenants', function (Builder $builder) {
            return $builder->withoutGlobalScope($this);
        });
    }
}
