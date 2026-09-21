<?php

namespace VentureDrake\LaravelCrm\Http\Controllers\Api\V2;

use Illuminate\Http\Request;
use VentureDrake\LaravelCrm\Http\Resources\Api\V2\Related\LabelBriefResource;
use VentureDrake\LaravelCrm\Models\Label;

class LabelController extends ApiController
{
    /**
     * Simple read-only listing so mobile/other API clients can populate a
     * label picker. Labels are a short, team-scoped list, so no pagination
     * is applied — matches how the web form loads them (Label::get()).
     */
    public function index(Request $request)
    {
        $query = Label::query()->orderBy('name');

        if ($request->filled('search')) {
            $query->where('name', 'like', '%'.$request->input('search').'%');
        }

        return LabelBriefResource::collection($query->get());
    }
}
