<?php

namespace VentureDrake\LaravelCrm\Models;

use VentureDrake\LaravelCrm\Traits\BelongsToTenant;

class DeviceToken extends Model
{
    use BelongsToTenant;

    protected $table = 'crm_device_tokens';

    protected $guarded = ['id'];

    protected $casts = ['last_seen_at' => 'datetime'];
}
