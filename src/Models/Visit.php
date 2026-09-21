<?php

namespace VentureDrake\LaravelCrm\Models;

use App\User;
use Illuminate\Database\Eloquent\SoftDeletes;
use VentureDrake\LaravelCrm\Traits\BelongsToTeams;
use Illuminate\Support\Str;

class Visit extends Model
{
    use SoftDeletes;
    use BelongsToTeams;

    protected $table = 'crm_visits';

    protected $fillable = [
        'external_id',
        'team_id',
        'title',
        'target_latitude',
        'target_longitude',
        'target_radius_meters',
        'target_address',
        'scheduled_at',
        'checked_in_at',
        'checkin_latitude',
        'checkin_longitude',
        'distance_meters',
        'photo_path',
        'notes',
        'status',
        'user_id',
        'assigned_by_user_id',
        'user_created_id',
        'user_updated_id',
        'user_deleted_id',
    ];

    protected $casts = [
        'target_latitude' => 'float',
        'target_longitude' => 'float',
        'target_radius_meters' => 'integer',
        'scheduled_at' => 'datetime',
        'checked_in_at' => 'datetime',
        'checkin_latitude' => 'float',
        'checkin_longitude' => 'float',
        'distance_meters' => 'float',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function assignedBy()
    {
        return $this->belongsTo(User::class, 'assigned_by_user_id');
    }

    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Hitung jarak (dalam meter) antara 2 titik koordinat pakai formula Haversine.
     */
    public static function distanceInMeters(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadius = 6371000; // meter

        $lat1Rad = deg2rad($lat1);
        $lat2Rad = deg2rad($lat2);
        $deltaLat = deg2rad($lat2 - $lat1);
        $deltaLon = deg2rad($lon2 - $lon1);

        $a = sin($deltaLat / 2) ** 2
            + cos($lat1Rad) * cos($lat2Rad) * sin($deltaLon / 2) ** 2;
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }

    /**
     * Cek apakah titik (lat, lon) yang diberikan masih dalam radius target visit ini.
     */
    public function isWithinRadius(float $lat, float $lon): bool
    {
        return $this->distanceTo($lat, $lon) <= $this->target_radius_meters;
    }

    public function distanceTo(float $lat, float $lon): float
    {
        return self::distanceInMeters($this->target_latitude, $this->target_longitude, $lat, $lon);
    }

    protected static function booted()
    {
        static::creating(function (Visit $visit) {
            if (empty($visit->external_id)) {
                $visit->external_id = (string) Str::uuid();
            }
        });
    }
}
