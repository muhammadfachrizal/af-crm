<?php

namespace VentureDrake\LaravelCrm\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use VentureDrake\LaravelCrm\Support\CurrentCustomer;

/**
 * Global filter otomatis: WHERE <table>.customer_id = <customer user yang login>.
 */
class CustomerScope implements Scope
{
    public function apply(Builder $builder, Model $model)
    {
        $customerId = CurrentCustomer::id();

        if ($customerId === null) {
            return; // super admin / console
        }

        $column = $model->getTable().'.customer_id';

        // Role boleh punya "template global" (customer_id NULL) yang dipakai semua customer.
        if ($model instanceof \Spatie\Permission\Models\Role) {
            $builder->where(function ($q) use ($column, $customerId) {
                $q->where($column, $customerId)->orWhereNull($column);
            });

            return;
        }

        $builder->where($column, $customerId);
    }

    public function extend(Builder $builder)
    {
        // Model::allCustomers()->... -> abaikan filter (khusus super admin / sistem)
        $builder->macro('allCustomers', function (Builder $builder) {
            return $builder->withoutGlobalScope($this);
        });
    }
}
