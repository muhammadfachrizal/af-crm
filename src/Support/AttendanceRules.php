<?php

namespace VentureDrake\LaravelCrm\Support;

use Carbon\Carbon;
use VentureDrake\LaravelCrm\Models\Shift;

/** Hitung telat / pulang cepat / lembur berdasarkan shift & zona waktu tenant. */
class AttendanceRules
{
    /**
     * @return array{late: int, early: int, overtime: int, status: string}
     */
    public static function evaluate(?Shift $shift, string $type, Carbon $recordedAt, string $timezone): array
    {
        $result = ['late' => 0, 'early' => 0, 'overtime' => 0, 'status' => 'on_time'];

        if (! $shift) {
            return $result;
        }

        $local = $recordedAt->copy()->setTimezone($timezone);
        $start = $local->copy()->setTimeFromTimeString($shift->start_time);
        $end = $local->copy()->setTimeFromTimeString($shift->end_time);

        if ($end->lessThanOrEqualTo($start)) {
            $end->addDay(); // shift melewati tengah malam
        }

        $minutes = fn (Carbon $a, Carbon $b): int => intdiv($b->timestamp - $a->timestamp, 60);

        if ($type === 'in') {
            $late = $minutes($start, $local);
            $result['late'] = $late > $shift->late_tolerance_minutes ? $late : 0;
            $result['status'] = $result['late'] > 0 ? 'late' : 'on_time';

            return $result;
        }

        if ($local->lessThan($end)) {
            $early = $minutes($local, $end);
            $result['early'] = $early > $shift->early_leave_tolerance_minutes ? $early : 0;
        } else {
            $over = $minutes($end, $local);
            $result['overtime'] = $over >= max(1, $shift->overtime_min_minutes) ? $over : 0;
        }

        $result['status'] = $result['early'] > 0 ? 'early_leave' : ($result['overtime'] > 0 ? 'overtime' : 'on_time');

        return $result;
    }
}
