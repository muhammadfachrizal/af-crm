<?php

namespace VentureDrake\LaravelCrm\Models;

use Illuminate\Support\Str;
use VentureDrake\LaravelCrm\Traits\BelongsToTenant;

class Holiday extends Model
{
    use BelongsToTenant;

    protected $table = 'crm_holidays';

    protected $guarded = ['id'];

    protected $casts = ['date' => 'date', 'is_joint_leave' => 'boolean'];

    protected static function booted()
    {
        static::creating(fn (self $m) => $m->external_id = $m->external_id ?: (string) Str::uuid());
    }
}
