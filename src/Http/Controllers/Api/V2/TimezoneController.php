<?php

namespace VentureDrake\LaravelCrm\Http\Controllers\Api\V2;

use Illuminate\Http\Request;
use VentureDrake\LaravelCrm\Http\Resources\Api\V2\Related\TimezoneBriefResource;
use VentureDrake\LaravelCrm\Models\Timezone;

class TimezoneController extends ApiController
{
    /**
     * Timezones are a global (non team-scoped) reference list.
     */
    public function index(Request $request)
    {
        $query = Timezone::query()->orderBy('name');

        if ($request->filled('search')) {
            $query->where('name', 'like', '%'.$request->input('search').'%');
        }

        return TimezoneBriefResource::collection($query->get());
    }
}
