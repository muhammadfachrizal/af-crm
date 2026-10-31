<?php

namespace VentureDrake\LaravelCrm\Traits;

use Illuminate\Database\Eloquent\Model;
use VentureDrake\LaravelCrm\Models\Tenant;
use VentureDrake\LaravelCrm\Scopes\CustomerScope;
use VentureDrake\LaravelCrm\Support\CurrentCustomer;

/**
 * Multi tenant per customer (setara HasTenant di POS / BaseCustomerEntity di HRIS).
 *
 * @mixin Model
 */
trait BelongsToCustomer
{
    public static function bootBelongsToCustomer()
    {
        static::addGlobalScope(new CustomerScope);

        static::creating(function (Model $model) {
            if (empty($model->customer_id) && ($customerId = CurrentCustomer::id())) {
                $model->customer_id = $customerId;
            }
        });
    }

    // Sengaja bernama tenant(), bukan customer(): model Customer CRM (klien) sudah ada di package ini.
    public function tenant()
    {
        return $this->belongsTo(Tenant::class, 'customer_id');
    }
}
