<?php

namespace VentureDrake\LaravelCrm\Http\Controllers\Api\V2;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use VentureDrake\LaravelCrm\Http\Requests\Api\V2\StoreAttendanceRequest;
use VentureDrake\LaravelCrm\Http\Resources\Api\V2\AttendanceResource;
use VentureDrake\LaravelCrm\Models\Attendance;
use VentureDrake\LaravelCrm\Models\Tenant;
use VentureDrake\LaravelCrm\Models\WorkLocation;

class AttendanceController extends ApiController
{
    /**
     * List attendance records.
     * Regular users see only their own records; users with
     * 'edit crm attendances' can see everyone's (optionally
     * filtered with ?user_id=).
     */
    public function index(Request $request)
    {
        $this->authorize('viewAny', Attendance::class);

        $query = Attendance::query()->with(['user']);

        if ($request->user()->hasPermissionTo('edit crm attendances')) {
            if ($request->filled('user_id')) {
                $query->where('user_id', $request->input('user_id'));
            }
        } else {
            $query->where('user_id', $request->user()->id);
        }

        if ($request->filled('date')) {
            $query->whereDate('recorded_at', $request->input('date'));
        }

        if ($request->filled('from') && $request->filled('to')) {
            $query->whereBetween('recorded_at', [
                $request->input('from'),
                $request->input('to'),
            ]);
        }

        $query = $this->applySort($query, $request, ['recorded_at', 'created_at'], '-recorded_at');

        $attendances = $query->paginate($this->perPage($request))->withQueryString();

        return AttendanceResource::collection($attendances);
    }

    /**
     * Get the authenticated user's most recent attendance record for today.
     * Used by the mobile app to decide whether to show Check In or Check Out.
     */
    public function today(Request $request)
    {
        $attendance = Attendance::query()
            ->forUser($request->user()->id)
            ->today()
            ->latest('recorded_at')
            ->first();

        return $attendance
            ? new AttendanceResource($attendance)
            : response()->json(['data' => null]);
    }

    public function show(Attendance $attendance)
    {
        $this->authorize('view', $attendance);

        $attendance->load(['user']);

        return new AttendanceResource($attendance);
    }

    public function store(StoreAttendanceRequest $request)
    {
        $this->authorize('create', Attendance::class);

        $lastToday = Attendance::query()
        ->forUser($request->user()->id)
        ->today()
        ->latest('recorded_at')
        ->first();

        $type = $request->input('type');

        if ($type === 'in' && $lastToday && $lastToday->type === 'in') {
            return response()->json(['message' => 'Anda sudah Check In hari ini.'], 422);
        }

        if ($type === 'out' && (! $lastToday || $lastToday->type === 'out')) {
            return response()->json(['message' => 'Anda belum Check In, atau sudah menyelesaikan absensi hari ini.'], 422);
        }

        // --- Lokasi kerja per tenant ---
        $lat = $request->input('latitude');
        $lng = $request->input('longitude');
        $workLocation = null;
        $distance = null;
        $within = null;

        $locations = WorkLocation::query()->active()->get();

        if ($locations->isNotEmpty() && $lat !== null && $lng !== null) {
            foreach ($locations as $loc) {
                $d = $loc->distanceTo((float) $lat, (float) $lng);

                if ($distance === null || $d < $distance) {
                    $distance = $d;
                    $workLocation = $loc;
                }
            }

            $within = $distance <= $workLocation->radius_meters;
        }

        $tenant = $request->user()->tenant_id ? Tenant::find($request->user()->tenant_id) : null;

        if ($tenant && $tenant->attendance_enforce_radius && $locations->isNotEmpty()) {
            if ($lat === null || $lng === null) {
                return response()->json(['message' => 'Lokasi tidak terdeteksi. Aktifkan GPS lalu coba lagi.'], 422);
            }

            if (! $within) {
                return response()->json([
                    'message' => sprintf(
                        'Anda berada %d m dari %s, di luar radius %d m.',
                        round($distance),
                        $workLocation->name,
                        $workLocation->radius_meters
                    ),
                ], 422);
            }
        }

        $photoPath = null;

        if ($request->hasFile('photo')) {
            $photoPath = $request->file('photo')->store('attendances', 'public');
        }

        $attendance = Attendance::create([
            'user_id' => $request->user()->id,
            'type' => $request->input('type'),
            'recorded_at' => $request->input('recorded_at', now()),
            'latitude' => $request->input('latitude'),
            'longitude' => $request->input('longitude'),
            'address' => $request->input('address'),
            'work_location_id' => $workLocation?->id,
            'distance_meters' => $distance !== null ? (int) round($distance) : null,
            'within_radius' => $within,
            'photo_path' => $photoPath,
            'notes' => $request->input('notes'),
        ]);

        $attendance->load(['user']);

        return (new AttendanceResource($attendance))->response()->setStatusCode(201);
    }
    
    public function destroy(Attendance $attendance)
    {
        $this->authorize('delete', $attendance);

        if ($attendance->photo_path) {
            Storage::disk('public')->delete($attendance->photo_path);
        }

        $attendance->delete();

        return response()->noContent();
    }
}
