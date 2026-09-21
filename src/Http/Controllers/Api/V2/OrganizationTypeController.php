<?php

namespace VentureDrake\LaravelCrm\Http\Controllers\Api\V2;

use Illuminate\Http\Request;
use VentureDrake\LaravelCrm\Http\Resources\Api\V2\Related\OrganizationTypeBriefResource;
use VentureDrake\LaravelCrm\Models\OrganizationType;

class OrganizationTypeController extends ApiController
{
    public function index(Request $request)
    {
        $query = OrganizationType::query()->orderBy('name');

        if ($request->filled('search')) {
            $query->where('name', 'like', '%'.$request->input('search').'%');
        }

        return OrganizationTypeBriefResource::collection($query->get());
    }
}
