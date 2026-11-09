<?php

namespace VentureDrake\LaravelCrm\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use VentureDrake\LaravelCrm\Traits\BelongsToTenant;

class Leave extends Model
{
    use BelongsToTenant;
    use SoftDeletes;

    protected $table = 'crm_leaves';

    protected $guarded = ['id'];

    protected $casts = ['start_date' => 'date', 'end_date' => 'date'];

    public const TYPES = ['cuti' => 'Cuti', 'izin' => 'Izin', 'sakit' => 'Sakit', 'dinas' => 'Dinas luar'];

    protected static function booted()
    {
        static::creating(fn (self $m) => $m->external_id = $m->external_id ?: (string) Str::uuid());
    }

    public function user()
    {
        return $this->belongsTo(config('auth.providers.users.model'), 'user_id');
    }
}
