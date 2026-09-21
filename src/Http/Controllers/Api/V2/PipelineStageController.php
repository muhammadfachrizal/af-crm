<?php

namespace VentureDrake\LaravelCrm\Http\Controllers\Api\V2;

use Illuminate\Http\Request;
use VentureDrake\LaravelCrm\Http\Resources\Api\V2\Related\PipelineStageBriefResource;
use VentureDrake\LaravelCrm\Models\Deal;
use VentureDrake\LaravelCrm\Models\Lead;
use VentureDrake\LaravelCrm\Models\Pipeline;

class PipelineStageController extends ApiController
{
    /**
     * Setiap model (Lead, Deal) punya SATU pipeline sendiri (lihat
     * Pipeline::where('model', ...)->first() di DealCreate/LeadCreate web),
     * dan stage-nya di-scope ke pipeline itu — makanya endpoint ini wajib
     * `?model=lead` atau `?model=deal`, bukan list semua stage sekaligus.
     */
    public function index(Request $request)
    {
        $modelParam = strtolower((string) $request->query('model', ''));

        $modelClass = match ($modelParam) {
            'lead' => Lead::class,
            'deal' => Deal::class,
            default => null,
        };

        if ($modelClass === null) {
            return PipelineStageBriefResource::collection(collect());
        }

        $pipeline = Pipeline::where('model', $modelClass)->first();

        if (! $pipeline) {
            return PipelineStageBriefResource::collection(collect());
        }

        $query = $pipeline->pipelineStages()->orderBy('order');

        if ($request->filled('search')) {
            $query->where('name', 'like', '%'.$request->input('search').'%');
        }

        return PipelineStageBriefResource::collection($query->get());
    }
}
