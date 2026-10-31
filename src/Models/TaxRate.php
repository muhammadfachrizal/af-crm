<?php

namespace VentureDrake\LaravelCrm\Models;

use VentureDrake\LaravelCrm\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\SoftDeletes;
use VentureDrake\LaravelCrm\Traits\BelongsToTeams;

class TaxRate extends Model
{
    use BelongsToTenant;
    use BelongsToTeams;
    use SoftDeletes;

    protected $guarded = ['id'];

    protected $casts = [
        'default' => 'boolean',
    ];

    public function getTable()
    {
        return config('laravel-crm.db_table_prefix').'tax_rates';
    }

    public function products()
    {
        return $this->hasMany(Product::class);
    }
}
