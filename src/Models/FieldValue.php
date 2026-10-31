<?php

namespace VentureDrake\LaravelCrm\Models;

use VentureDrake\LaravelCrm\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\SoftDeletes;
use VentureDrake\LaravelCrm\Traits\BelongsToTeams;

class FieldValue extends Model
{
    use BelongsToTenant;
    use BelongsToTeams;
    use SoftDeletes;

    protected $guarded = ['id'];

    public function getTable()
    {
        return config('laravel-crm.db_table_prefix').'field_values';
    }

    public function field()
    {
        return $this->belongsTo(Field::class);
    }

    /**
     * Get all of the owning field value models.
     */
    public function fieldValueable()
    {
        return $this->morphTo();
    }
}
