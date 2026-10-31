<?php

namespace VentureDrake\LaravelCrm\Models;

use VentureDrake\LaravelCrm\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\SoftDeletes;
use VentureDrake\LaravelCrm\Traits\BelongsToTeams;

class PipelineStageProbability extends Model
{
    use BelongsToTenant;
    use BelongsToTeams;
    use SoftDeletes;

    protected $guarded = ['id'];

    public function getTable()
    {
        return config('laravel-crm.db_table_prefix').'pipeline_stage_probabilities';
    }

    public function pipelineStage()
    {
        return $this->hasMany(PipelineStage::class);
    }
}
