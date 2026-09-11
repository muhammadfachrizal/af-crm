<?php

namespace VentureDrake\LaravelCrm\Models;

use App\User;
use Illuminate\Database\Eloquent\SoftDeletes;
use VentureDrake\LaravelCrm\Traits\BelongsToTeams;

class Attendance extends Model
{
    use SoftDeletes;
    use BelongsToTeams;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'crm_attendances';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'external_id',
        'team_id',
        'type',
        'recorded_at',
        'latitude',
        'longitude',
        'address',
        'photo_path',
        'notes',
        'user_id',
        'user_created_id',
        'user_updated_id',
        'user_deleted_id',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'recorded_at' => 'datetime',
        'latitude' => 'float',
        'longitude' => 'float',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function userCreated()
    {
        return $this->belongsTo(User::class, 'user_created_id');
    }

    public function userUpdated()
    {
        return $this->belongsTo(User::class, 'user_updated_id');
    }

    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeToday($query)
    {
        return $query->whereDate('recorded_at', now()->toDateString());
    }
}
