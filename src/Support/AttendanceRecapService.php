<?php

namespace VentureDrake\LaravelCrm\Support;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use VentureDrake\LaravelCrm\Models\Attendance;
use VentureDrake\LaravelCrm\Models\Shift;
use VentureDrake\LaravelCrm\Models\Tenant;

/** Rekap absensi bulanan per user (tenant aktif). */
class AttendanceRecapService
{
    public static function timezone(): string
    {
        $id = CurrentTenant::id();

        return ($id ? Tenant::find($id)?->timezone : null) ?: config('app.timezone', 'UTC');
    }

    public static function build(string $month, ?int $userId = null): Collection
    {
        $tz = static::timezone();
        $from = Carbon::createFromFormat('Y-m', $month, $tz)->startOfMonth();
        $to = $from->copy()->endOfMonth();
        $until = $to->copy()->min(Carbon::now($tz)->endOfDay()); // hari kerja dihitung sampai hari ini

        $userModel = config('auth.providers.users.model');
        $users = $userModel::query()
            ->when($userId, fn ($q) => $q->where('id', $userId))
            ->orderBy('name')
            ->get();

        $rows = Attendance::query()
            ->whereBetween('recorded_at', [$from->copy()->utc(), $to->copy()->utc()])
            ->when($userId, fn ($q) => $q->where('user_id', $userId))
            ->get()
            ->groupBy('user_id');

        $default = Shift::where('is_default', true)->first();
        $shifts = Shift::get()->keyBy('id');

        return $users->map(function ($user) use ($rows, $from, $until, $tz, $default, $shifts) {
            $shift = ($user->shift_id && isset($shifts[$user->shift_id])) ? $shifts[$user->shift_id] : $default;
            $mine = $rows->get($user->id, collect());

            $presentDays = $mine->where('type', 'in')
                ->map(fn ($a) => $a->recorded_at->copy()->setTimezone($tz)->toDateString())
                ->unique();

            $workdays = 0;
            for ($d = $from->copy(); $d->lte($until); $d->addDay()) {
                if (($shift ? $shift->worksOn($d->dayOfWeekIso) : $d->dayOfWeekIso <= 5)) {
                    $workdays++;
                }
            }

            $workdaysPresent = $presentDays->filter(function ($date) use ($shift) {
                $iso = Carbon::parse($date)->dayOfWeekIso;

                return $shift ? $shift->worksOn($iso) : $iso <= 5;
            })->count();

            return [
                'user_id' => $user->id,
                'name' => $user->name,
                'shift' => $shift?->name ?? '-',
                'workdays' => $workdays,
                'present' => $presentDays->count(),
                'absent' => max(0, $workdays - $workdaysPresent),
                'late_count' => $mine->where('type', 'in')->where('late_minutes', '>', 0)->count(),
                'late_minutes' => (int) $mine->where('type', 'in')->sum('late_minutes'),
                'early_count' => $mine->where('type', 'out')->where('early_leave_minutes', '>', 0)->count(),
                'early_minutes' => (int) $mine->where('type', 'out')->sum('early_leave_minutes'),
                'overtime_minutes' => (int) $mine->where('type', 'out')->sum('overtime_minutes'),
                'outside_radius' => $mine->where('within_radius', false)->count(),
                'mocked' => $mine->where('is_mocked', true)->count(),
                'device_bound' => ! empty($user->bound_device_id),
            ];
        });
    }
}
