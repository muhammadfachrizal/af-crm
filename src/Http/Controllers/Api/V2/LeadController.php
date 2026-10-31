<?php

namespace VentureDrake\LaravelCrm\Http\Controllers\Api\V2;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;
use VentureDrake\LaravelCrm\Http\Requests\Api\V2\StoreLeadRequest;
use VentureDrake\LaravelCrm\Http\Requests\Api\V2\UpdateLeadRequest;
use VentureDrake\LaravelCrm\Http\Resources\Api\V2\LeadResource;
use VentureDrake\LaravelCrm\Models\Label;
use VentureDrake\LaravelCrm\Models\Lead;
use VentureDrake\LaravelCrm\Models\LeadSource;
use VentureDrake\LaravelCrm\Models\Organization;
use VentureDrake\LaravelCrm\Models\Person;
use VentureDrake\LaravelCrm\Models\PipelineStage;
use VentureDrake\LaravelCrm\Services\LeadService;
use Carbon\Carbon;

class LeadController extends ApiController
{
    public function __construct(private LeadService $leadService) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', Lead::class);

        $query = Lead::query()->with(['ownerUser', 'person', 'organization', 'labels', 'pipelineStage', 'leadSource']);

        $query = $this->applySearch(
            $query,
            $request,
            [
                'lead_id',
                'title',
                'person.first_name',
                'person.middle_name',
                'person.last_name',
                'person.maiden_name',
                'organization.name',
            ]
        );

        if ($request->filled('user_owner_id')) {
            $query->where('user_owner_id', $request->input('user_owner_id'));
        }

        $query = $this->applySort(
            $query,
            $request,
            ['created_at', 'updated_at', 'title', 'amount'],
            '-created_at'
        );

        $leads = $query->paginate($this->perPage($request))->withQueryString();

        return LeadResource::collection($leads);
    }

    public function show(Lead $lead)
    {
        $this->authorize('view', $lead);

        $lead->load(['ownerUser', 'person', 'organization', 'labels', 'pipelineStage', 'leadSource']);

        return new LeadResource($lead);
    }

    public function store(StoreLeadRequest $request)
    {
        $this->authorize('create', Lead::class);

        [$payload, $person, $organization] = $this->buildPayload($request);

        $lead = $this->leadService->create($payload, $person, $organization);

        $this->applyDirectFields($lead, $request);

        $lead->refresh()->load(['ownerUser', 'person', 'organization', 'labels', 'pipelineStage', 'leadSource']);

        return (new LeadResource($lead))->response()->setStatusCode(201);
    }

    public function update(UpdateLeadRequest $request, Lead $lead)
    {
        $this->authorize('update', $lead);

        [$payload, $person, $organization] = $this->buildPayload($request, $lead);

        $this->leadService->update($payload, $lead, $person, $organization);

        $this->applyDirectFields($lead, $request);

        $lead->refresh()->load(['ownerUser', 'person', 'organization', 'labels', 'pipelineStage', 'leadSource']);

        return new LeadResource($lead);
    }

    public function destroy(Lead $lead)
    {
        $this->authorize('delete', $lead);

        $lead->delete();

        return response()->noContent();
    }

    private function buildPayload(FormRequest $request, ?Lead $existing = null): array
    {
        if ($request->filled('person_id')) {
            $person = Person::where('external_id', $request->input('person_id'))->first();
        } elseif ($request->has('person_id')) {
            $person = null;
        } else {
            $person = $existing?->person;
        }

        if ($request->filled('organization_id')) {
            $organization = Organization::where('external_id', $request->input('organization_id'))->first();
        } elseif ($request->has('organization_id')) {
            $organization = null;
        } else {
            $organization = $existing?->organization;
        }

        $leadSource = $request->filled('lead_source_id')
            ? LeadSource::where('external_id', $request->input('lead_source_id'))->first()
            : null;

        $pipelineStage = $request->filled('pipeline_stage_id')
            ? PipelineStage::where('external_id', $request->input('pipeline_stage_id'))->first()
            : null;

        $labelIds = collect($request->input('labels', []))
            ->map(fn ($uuid) => Label::where('external_id', $uuid)->value('id'))
            ->filter()
            ->values()
            ->all();

        $payload = (object) [
            'title' => $request->input('title', $existing?->title),
            'description' => $request->input('description', $existing?->description),
            'amount' => $request->input('amount', $existing ? ($existing->amount !== null ? $existing->amount / 100 : null) : null),
            'currency' => $request->input('currency', $existing?->currency) ?: 'USD',
            'lead_source_id' => $leadSource?->id ?? $existing?->lead_source_id,
            'user_owner_id' => $request->input('user_owner_id', $existing?->user_owner_id),
            'pipeline_stage_id' => $pipelineStage?->id ?? $existing?->pipeline_stage_id,
            'labels' => $labelIds,
        ];

        return [$payload, $person, $organization];
    }

    private function applyDirectFields(Lead $lead, FormRequest $request): void
    {
        if ($request->has('expected_close')) {
            $value = $request->input('expected_close');

            $lead->expected_close = $value
                ? Carbon::parse($value)
                : null;

            $lead->saveQuietly();
        }
    }

    /**
     * Konversi lead jadi deal — sama persis dengan alur DealCreate.php pas
     * dibuka dengan fromModelType=lead di web: bikin Deal baru terisi dari
     * data lead (person/organization/title/description/amount/currency),
     * lalu tandai lead-nya converted_at.
     */
    public function convert(Lead $lead)
    {
        $this->authorize('update', $lead);

        if ($lead->converted_at) {
            return response()->json([
                'message' => 'Lead ini sudah pernah dikonversi jadi deal sebelumnya.',
            ], 422);
        }

        $pipeline = \VentureDrake\LaravelCrm\Models\Pipeline::where('model', \VentureDrake\LaravelCrm\Models\Deal::class)->first();
        $pipelineStageId = $pipeline?->pipelineStages()->orderBy('order')->first()->id;

        $dealRequest = (object) [
            'lead_id' => $lead->id,
            'title' => $lead->title,
            'description' => $lead->description,
            'amount' => $lead->amount !== null ? $lead->amount / 100 : null,
            'currency' => $lead->currency,
            'expected_close' => $lead->expected_close,
            'user_owner_id' => $lead->user_owner_id ?? auth()->id(),
            'pipeline_stage_id' => $pipelineStageId,
            'labels' => [],
        ];

        $deal = app(\VentureDrake\LaravelCrm\Services\DealService::class)->create(
            $dealRequest,
            $lead->person,
            $lead->organization
        );

        $lead->update(['converted_at' => Carbon::now()]);

        $deal->refresh()->load(['ownerUser', 'person', 'organization', 'lead', 'labels', 'dealProducts.product', 'pipelineStage']);

        return (new \VentureDrake\LaravelCrm\Http\Resources\Api\V2\DealResource($deal))
            ->response()
            ->setStatusCode(201);
    }
}
