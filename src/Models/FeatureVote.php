<?php

namespace VentureDrake\LaravelCrm\Models;

use VentureDrake\LaravelCrm\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Relations\Pivot;

class FeatureVote extends Pivot
{
    use BelongsToTenant;
    public $incrementing = true;

    protected $guarded = ['id'];

    public function getTable()
    {
        return config('laravel-crm.db_table_prefix').'feature_votes';
    }

    public function feature()
    {
        return $this->belongsTo(Feature::class, 'feature_id');
    }
}
