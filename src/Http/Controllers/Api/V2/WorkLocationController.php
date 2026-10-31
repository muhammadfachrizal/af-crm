<?php

namespace VentureDrake\LaravelCrm\Http\Controllers\Api\V2;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use VentureDrake\LaravelCrm\Models\WorkLocation;

/**
 * Master Lokasi Kerja per tenant. tenant_id terisi & terfilter otomatis oleh BelongsToTenant.
 * - GET  : semua user (mobile memakainya untuk validasi/penanda radius)
 * - CUD  : hanya user dengan permission 'edit crm attendances'
 */
class WorkLocationController extends ApiController
{
    protected function format(WorkLocation $l): array
    {
        return [
            'id' => $l->external_id,
            'code' => $l->code,
            'name' => $l->name,
            'address' => $l->address,
            'latitude' => $l->latitude,
            'longitude' => $l->longitude,
            'radius_meters' => $l->radius_meters,
            'is_active' => $l->is_active,
        ];
    }

    protected function canManage(Request $request): bool
    {
        return $request->user()->hasPermissionTo('edit crm attendances');
    }

    public function index(Request $request): JsonResponse
    {
        $q = WorkLocation::query()->orderBy('name');

        if (! $this->canManage($request) || ! $request->boolean('all')) {
            $q->active();
        }

        return response()->json(['data' => $q->get()->map(fn ($l) => $this->format($l))->values()]);
    }

    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'radius_meters' => ['required', 'integer', 'min:10', 'max:100000'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function store(Request $request): JsonResponse
    {
        abort_unless($this->canManage($request), 403);

        $location = WorkLocation::create($request->validate($this->rules()));

        return response()->json(['data' => $this->format($location)], 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        abort_unless($this->canManage($request), 403);

        $location = WorkLocation::where('external_id', $id)->firstOrFail();
        $location->update($request->validate($this->rules()));

        return response()->json(['data' => $this->format($location)]);
    }

    public function destroy(Request $request, string $id)
    {
        abort_unless($this->canManage($request), 403);

        WorkLocation::where('external_id', $id)->firstOrFail()->delete();

        return response()->noContent();
    }
}
