<?php

namespace VentureDrake\LaravelCrm\Models;

use VentureDrake\LaravelCrm\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\SoftDeletes;
use VentureDrake\LaravelCrm\Traits\BelongsToTeams;

class Activity extends Model
{
    use BelongsToTenant;
    use BelongsToTeams;
    use SoftDeletes;

    protected $guarded = ['id'];

    public function getTable()
    {
        return config('laravel-crm.db_table_prefix').'activities';
    }

    public function causeable()
    {
        return $this->morphTo();
    }

    public function timelineable()
    {
        return $this->morphTo();
    }

    public function recordable()
    {
        return $this->morphTo();
    }
}
