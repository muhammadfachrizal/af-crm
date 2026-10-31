<?php

namespace VentureDrake\LaravelCrm\Models;

use VentureDrake\LaravelCrm\Traits\BelongsToTenant;
use Spatie\Permission\Models\Role as SpatieRole;

class Role extends SpatieRole
{
    use BelongsToTenant;
    public function scopeCrm($query)
    {
        return $query->where('crm_role', 1);
    }

    public function scopeCrmNotOwner($query)
    {
        return $query->where('crm_role', 1)->where('name', '<>', 'Owner');
    }
}
