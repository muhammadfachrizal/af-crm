<?php

namespace VentureDrake\LaravelCrm\Models;

use VentureDrake\LaravelCrm\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\SoftDeletes;
use VentureDrake\LaravelCrm\Traits\BelongsToTeams;

class Field extends Model
{
    use BelongsToTenant;
    use BelongsToTeams;
    use SoftDeletes;

    protected $guarded = ['id'];

    public function getTable()
    {
        return config('laravel-crm.db_table_prefix').'fields';
    }

    public function fieldGroup()
    {
        return $this->belongsTo(FieldGroup::class);
    }

    public function fieldOptions()
    {
        return $this->hasMany(FieldOption::class);
    }

    public function fieldModels()
    {
        return $this->hasMany(FieldModel::class);
    }
}
