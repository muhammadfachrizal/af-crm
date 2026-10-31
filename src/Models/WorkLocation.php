<?php

namespace VentureDrake\LaravelCrm\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use VentureDrake\LaravelCrm\Traits\BelongsToTenant;

class WorkLocation extends Model
{
    use BelongsToTenant;
    use SoftDeletes;

    protected $table = 'crm_work_locations';

    protected $guarded = ['id'];

    protected $casts = [
        'latitude' => 'float',
        'longitude' => 'float',
        'radius_meters' => 'integer',
        'is_active' => 'boolean',
    ];

    protected static function booted()
    {
        static::creating(function (self $m) {
            if (empty($m->external_id)) {
                $m->external_id = (string) Str::uuid();
            }
        });
    }

    public function scopeActive($q)
    {
        return $q->where('is_active', true);
    }

    /** Jarak (meter) ke titik lain, Haversine. */
    public function distanceTo(float $lat, float $lng): float
    {
        $r = 6371000;
        $dLat = deg2rad($lat - $this->latitude);
        $dLng = deg2rad($lng - $this->longitude);
        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($this->latitude)) * cos(deg2rad($lat)) * sin($dLng / 2) ** 2;

        return $r * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
