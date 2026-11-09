<?php

namespace VentureDrake\LaravelCrm\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use VentureDrake\LaravelCrm\Traits\BelongsToTenant;

class Shift extends Model
{
    use BelongsToTenant;
    use SoftDeletes;

    protected $table = 'crm_shifts';

    protected $guarded = ['id'];

    protected $casts = [
        'working_days' => 'array',
        'is_default' => 'boolean',
        'is_active' => 'boolean',
        'late_tolerance_minutes' => 'integer',
        'early_leave_tolerance_minutes' => 'integer',
        'overtime_min_minutes' => 'integer',
    ];

    protected static function booted()
    {
        static::creating(function (self $m) {
            if (empty($m->external_id)) {
                $m->external_id = (string) Str::uuid();
            }
        });

        // Hanya satu shift default per tenant.
        static::saved(function (self $m) {
            if ($m->is_default) {
                static::where('id', '!=', $m->id)->where('is_default', true)->update(['is_default' => false]);
            }
        });
    }

    public function worksOn(int $isoDayOfWeek): bool
    {
        $days = $this->working_days ?: [1, 2, 3, 4, 5];

        return in_array($isoDayOfWeek, $days, true) || in_array((string) $isoDayOfWeek, $days, true);
    }

    /** Shift milik user; kalau belum ditugaskan -> shift default tenant. */
    public static function forUser($user): ?self
    {
        if (! empty($user->shift_id) && ($s = static::find($user->shift_id))) {
            return $s;
        }

        return static::where('is_default', true)->where('is_active', true)->first();
    }
}
