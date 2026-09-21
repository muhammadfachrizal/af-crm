<?php

namespace VentureDrake\LaravelCrm\Http\Controllers\Api\V2;

use App\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use VentureDrake\LaravelCrm\Http\Requests\Api\V2\CheckinVisitRequest;
use VentureDrake\LaravelCrm\Http\Requests\Api\V2\StoreVisitRequest;
use VentureDrake\LaravelCrm\Http\Resources\Api\V2\VisitResource;
use VentureDrake\LaravelCrm\Models\Visit;

class VisitController extends ApiController
{
    /**
     * List visit. User biasa cuma lihat punya sendiri; yang punya
     * 'edit crm visits' bisa lihat semua (filter ?user_id= & ?status=).
     */
    public function index(Request $request)
    {
        $this->authorize('viewAny', Visit::class);

        $query = Visit::query()->with(['user', 'assignedBy']);

        if ($request->user()->hasPermissionTo('edit crm visits')) {
            if ($request->filled('user_id')) {
                $query->where('user_id', $request->input('user_id'));
            }
        } else {
            $query->where('user_id', $request->user()->id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $query = $this->applySort($query, $request, ['scheduled_at', 'created_at'], '-created_at');

        $visits = $query->paginate($this->perPage($request))->withQueryString();

        return VisitResource::collection($visits);
    }

    public function show(Visit $visit)
    {
        $this->authorize('view', $visit);

        $visit->load(['user', 'assignedBy']);

        return new VisitResource($visit);
    }

    /**
     * Bikin visit baru. Kalau 'user_id' diisi dan BEDA dari user yang
     * login, berarti lagi nge-assign ke orang lain -> butuh permission
     * 'edit crm visits'. Kalau kosong/sama, berarti self-initiated.
     */
    public function store(StoreVisitRequest $request)
    {
        $this->authorize('create', Visit::class);

        $targetUserId = $request->user()->id;
        $assignedByUserId = null;

        if ($request->filled('user_id') && $request->input('user_id') != $request->user()->id) {
            if (! $request->user()->hasPermissionTo('edit crm visits')) {
                return response()->json([
                    'message' => 'Anda tidak punya izin untuk assign visit ke orang lain.',
                ], 403);
            }

            $targetUser = User::find($request->input('user_id'));
            if (! $targetUser) {
                return response()->json(['message' => 'User tujuan tidak ditemukan.'], 422);
            }

            $targetUserId = $targetUser->id;
            $assignedByUserId = $request->user()->id;
        }

        $visit = Visit::create([
            'user_id' => $targetUserId,
            'assigned_by_user_id' => $assignedByUserId,
            'title' => $request->input('title'),
            'target_latitude' => $request->input('target_latitude'),
            'target_longitude' => $request->input('target_longitude'),
            'target_radius_meters' => $request->input('target_radius_meters', 100),
            'target_address' => $request->input('target_address'),
            'scheduled_at' => $request->input('scheduled_at'),
            'notes' => $request->input('notes'),
            'status' => 'planned',
        ]);

        $visit->load(['user', 'assignedBy']);

        return (new VisitResource($visit))->response()->setStatusCode(201);
    }

    /**
     * Check-in ke visit. INI YANG VALIDASI RADIUS -- dicek di server,
     * bukan cuma di app, supaya gak bisa diakalin.
     */
    public function checkin(CheckinVisitRequest $request, Visit $visit)
    {
        $this->authorize('update', $visit);

        if ($visit->status === 'completed') {
            return response()->json(['message' => 'Visit ini sudah di-check-in sebelumnya.'], 422);
        }

        $lat = (float) $request->input('latitude');
        $lon = (float) $request->input('longitude');
        $distance = $visit->distanceTo($lat, $lon);

        if ($distance > $visit->target_radius_meters) {
            return response()->json([
                'message' => sprintf(
                    'Lokasi Anda %.0f meter dari titik tujuan (maksimal %d meter). Silakan mendekat ke lokasi yang benar.',
                    $distance,
                    $visit->target_radius_meters
                ),
                'distance_meters' => round($distance, 2),
                'allowed_radius_meters' => $visit->target_radius_meters,
            ], 422);
        }

        $photoPath = null;
        if ($request->hasFile('photo')) {
            $photoPath = $request->file('photo')->store('visits', 'public');
        }

        $visit->update([
            'checked_in_at' => now(),
            'checkin_latitude' => $lat,
            'checkin_longitude' => $lon,
            'distance_meters' => round($distance, 2),
            'photo_path' => $photoPath ?? $visit->photo_path,
            'notes' => $request->input('notes', $visit->notes),
            'status' => 'completed',
        ]);

        $visit->load(['user', 'assignedBy']);

        return new VisitResource($visit);
    }

    public function destroy(Visit $visit)
    {
        $this->authorize('delete', $visit);

        if ($visit->photo_path) {
            Storage::disk('public')->delete($visit->photo_path);
        }

        $visit->delete();

        return response()->noContent();
    }
}
