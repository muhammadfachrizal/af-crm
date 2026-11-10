<?php

namespace VentureDrake\LaravelCrm\Support;

use Carbon\Carbon;
use VentureDrake\LaravelCrm\Models\Attendance;
use VentureDrake\LaravelCrm\Models\Holiday;
use VentureDrake\LaravelCrm\Models\Leave;
use VentureDrake\LaravelCrm\Models\Shift;

/** Data kartu absensi di dashboard mobile: masuk/pulang hari ini, shift, dan rekap bulan berjalan. */
class AttendanceOverview
{
    public static function forUser($user): array
    {
        $tz = AttendanceRecapService::timezone();
        $now = Carbon::now($tz);
        $dayStart = $now->copy()->startOfDay()->utc();
        $dayEnd = $now->copy()->endOfDay()->utc();

        $records = Attendance::where('user_id', $user->id)->whereBetween('recorded_at', [$dayStart, $dayEnd])->orderBy('recorded_at')->get();
        $in = $records->where('type', 'in')->first();
        $out = $records->where('type', 'out')->last();

        $holiday = Holiday::whereDate('date', $now->toDateString())->first();
        $onLeave = Leave::where('user_id', $user->id)->whereDate('start_date', '<=', $now->toDateString())->whereDate('end_date', '>=', $now->toDateString())->exists();
        $shift = Shift::forUser($user);

        $month = AttendanceRecapService::build($now->format('Y-m'), $user->id)->first() ?? [];

        $local = fn ($a) => $a ? $a->recorded_at->copy()->setTimezone($tz)->format('H:i') : null;

        return [
            'date' => $now->toDateString(),
            'today' => [
                'in_at' => $local($in),
                'out_at' => $local($out),
                'in_status' => $in?->status,
                'late_minutes' => (int) ($in?->late_minutes ?? 0),
                'out_status' => $out?->status,
                'early_leave_minutes' => (int) ($out?->early_leave_minutes ?? 0),
                'overtime_minutes' => (int) ($out?->overtime_minutes ?? 0),
                'is_holiday' => (bool) $holiday,
                'holiday_name' => $holiday?->name,
                'on_leave' => $onLeave,
                'is_workday' => $shift ? $shift->worksOn($now->dayOfWeekIso) && ! $holiday : ($now->dayOfWeekIso <= 5 && ! $holiday),
            ],
            'shift' => $shift ? ['name' => $shift->name, 'start_time' => $shift->start_time, 'end_time' => $shift->end_time] : null,
            'month' => [
                'label' => $now->translatedFormat('F'),
                'workdays' => $month['workdays'] ?? 0,
                'present' => $month['present'] ?? 0,
                'late_count' => $month['late_count'] ?? 0,
                'late_minutes' => $month['late_minutes'] ?? 0,
                'absent' => $month['absent'] ?? 0,
                'leave_days' => $month['leave_days'] ?? 0,
                'overtime_minutes' => $month['overtime_minutes'] ?? 0,
            ],
        ];
    }
}
