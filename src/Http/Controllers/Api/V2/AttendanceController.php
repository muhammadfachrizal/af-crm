<?php

namespace VentureDrake\LaravelCrm\Http\Controllers\Api\V2;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use VentureDrake\LaravelCrm\Http\Requests\Api\V2\StoreAttendanceRequest;
use VentureDrake\LaravelCrm\Http\Resources\Api\V2\AttendanceResource;
use VentureDrake\LaravelCrm\Models\Attendance;
use VentureDrake\LaravelCrm\Models\Shift;
use VentureDrake\LaravelCrm\Models\Tenant;
use VentureDrake\LaravelCrm\Support\AttendanceRules;
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

    /**
     * Jadwal untuk alarm absen di mobile: shift, zona waktu, menit pengingat,
     * tanggal libur tenant & tanggal cuti/izin user untuk N hari ke depan.
     */
    public function schedule(Request $request)
    {
        $days = min(60, max(1, (int) $request->query('days', 30)));
        $user = $request->user();
        $tenant = $user->tenant_id ? Tenant::find(\VentureDrake\LaravelCrm\Support\CurrentTenant::id() ?? $user->tenant_id) : null;
        $tz = ($tenant->timezone ?? null) ?: config('app.timezone', 'UTC');
        $from = \Carbon\Carbon::now($tz)->startOfDay();
        $to = $from->copy()->addDays($days);

        $holidays = \VentureDrake\LaravelCrm\Models\Holiday::whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->get()->map(fn ($h) => $h->date->toDateString())->values();

        $leaveDates = [];
        foreach (\VentureDrake\LaravelCrm\Models\Leave::where('user_id', $user->id)
            ->where('end_date', '>=', $from->toDateString())->where('start_date', '<=', $to->toDateString())->get() as $lv) {
            for ($d = $lv->start_date->copy(); $d->lte($lv->end_date); $d->addDay()) {
                $leaveDates[] = $d->toDateString();
            }
        }

        $shift = Shift::forUser($user);

        return response()->json(['data' => [
            'timezone' => $tz,
            'reminder_minutes' => (int) ($tenant->attendance_reminder_minutes ?? 15),
            'shift' => $shift ? [
                'name' => $shift->name,
                'start_time' => $shift->start_time,
                'end_time' => $shift->end_time,
                'working_days' => array_map('intval', $shift->working_days ?: [1, 2, 3, 4, 5]),
            ] : null,
            'holidays' => $holidays,
            'leaves' => array_values(array_unique($leaveDates)),
        ]]);
    }

    /** Shift milik user yang login (untuk ditampilkan di mobile). */
    public function shift(Request $request)
    {
        $shift = Shift::forUser($request->user());

        return response()->json(['data' => $shift ? [
            'id' => $shift->external_id,
            'name' => $shift->name,
            'start_time' => $shift->start_time,
            'end_time' => $shift->end_time,
            'working_days' => $shift->working_days ?: [1, 2, 3, 4, 5],
            'late_tolerance_minutes' => $shift->late_tolerance_minutes,
        ] : null]);
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

        // --- Anti fake GPS & kunci perangkat ---
        if ($tenant && $tenant->block_mock_location && $request->boolean('is_mocked')) {
            return response()->json(['message' => 'Lokasi palsu (mock location) terdeteksi. Matikan aplikasi fake GPS lalu coba lagi.'], 422);
        }

        if ($tenant && $tenant->bind_device && $request->filled('device_id')) {
            $bound = $request->user()->bound_device_id;

            if (! $bound) {
                \Illuminate\Support\Facades\DB::table('users')->where('id', $request->user()->id)
                    ->update(['bound_device_id' => $request->input('device_id')]);
            } elseif ($bound !== $request->input('device_id')) {
                return response()->json(['message' => 'Akun ini terikat ke perangkat lain. Hubungi admin untuk mereset perangkat.'], 422);
            }
        }

        // --- Aturan shift: telat / pulang cepat / lembur ---
        $recordedAt = \Carbon\Carbon::parse($request->input('recorded_at', now()));
        $timezone = ($tenant->timezone ?? null) ?: config('app.timezone', 'UTC');
        $shift = Shift::forUser($request->user());
        $rule = AttendanceRules::evaluate($shift, $type, $recordedAt, $timezone);

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
            'shift_id' => $shift?->id,
            'late_minutes' => $rule['late'],
            'early_leave_minutes' => $rule['early'],
            'overtime_minutes' => $rule['overtime'],
            'status' => $rule['status'],
            'is_mocked' => $request->boolean('is_mocked'),
            'device_id' => $request->input('device_id'),
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
