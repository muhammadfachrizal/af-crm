<?php

namespace VentureDrake\LaravelCrm\Http\Controllers\Api\V2;

use Illuminate\Http\Request;
use VentureDrake\LaravelCrm\Http\Resources\Api\V2\Related\LeadSourceBriefResource;
use VentureDrake\LaravelCrm\Models\LeadSource;

class LeadSourceController extends ApiController
{
    public function index(Request $request)
    {
        $query = LeadSource::query()->orderBy('name');

        if ($request->filled('search')) {
            $query->where('name', 'like', '%'.$request->input('search').'%');
        }

        return LeadSourceBriefResource::collection($query->get());
    }
}
