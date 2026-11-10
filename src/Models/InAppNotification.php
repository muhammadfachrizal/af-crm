<?php

namespace VentureDrake\LaravelCrm\Models;

use VentureDrake\LaravelCrm\Traits\BelongsToTenant;

/** Notifikasi di dalam aplikasi (web + mobile). Dipisah dari Illuminate Notification milik Laravel. */
class InAppNotification extends Model
{
    use BelongsToTenant;

    protected $table = 'crm_notifications';

    protected $guarded = ['id'];

    protected $casts = ['data' => 'array', 'read_at' => 'datetime'];
}
