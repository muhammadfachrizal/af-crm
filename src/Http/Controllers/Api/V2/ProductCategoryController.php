<?php

namespace VentureDrake\LaravelCrm\Http\Controllers\Api\V2;

use Illuminate\Http\Request;
use VentureDrake\LaravelCrm\Http\Resources\Api\V2\Related\ProductCategoryBriefResource;
use VentureDrake\LaravelCrm\Models\ProductCategory;

class ProductCategoryController extends ApiController
{
    public function index(Request $request)
    {
        $query = ProductCategory::query()->orderBy('name');

        if ($request->filled('search')) {
            $query->where('name', 'like', '%'.$request->input('search').'%');
        }

        return ProductCategoryBriefResource::collection($query->get());
    }
}
