<?php

namespace VentureDrake\LaravelCrm\Http\Controllers\Api\V2;

use Illuminate\Http\Request;
use VentureDrake\LaravelCrm\Http\Resources\Api\V2\Related\IndustryBriefResource;
use VentureDrake\LaravelCrm\Models\Industry;

class IndustryController extends ApiController
{
    public function index(Request $request)
    {
        $query = Industry::query()->orderBy('name');

        if ($request->filled('search')) {
            $query->where('name', 'like', '%'.$request->input('search').'%');
        }

        return IndustryBriefResource::collection($query->get());
    }
}
