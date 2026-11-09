<?php

namespace VentureDrake\LaravelCrm\Http\Controllers;

use Illuminate\Http\Request;
use VentureDrake\LaravelCrm\Support\AttendanceRecapService;

class AttendanceRecapController extends Controller
{
    public function index()
    {
        return view('laravel-crm::attendance-recap.index');
    }

    public function shifts()
    {
        return view('laravel-crm::shifts.index');
    }

    public function export(Request $request)
    {
        abort_unless($request->user()->hasPermissionTo('view crm attendances'), 403);

        $month = preg_match('/^\d{4}-\d{2}$/', (string) $request->query('month')) ? $request->query('month') : now()->format('Y-m');
        $rows = AttendanceRecapService::build($month);

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // BOM agar Excel membaca UTF-8
            fputcsv($out, ['User', 'Shift', 'Hari kerja', 'Hadir', 'Tidak hadir', 'Telat (x)', 'Telat (menit)', 'Pulang cepat (x)', 'Pulang cepat (menit)', 'Lembur (menit)', 'Luar radius', 'GPS palsu']);

            foreach ($rows as $r) {
                fputcsv($out, [$r['name'], $r['shift'], $r['workdays'], $r['present'], $r['absent'], $r['late_count'], $r['late_minutes'], $r['early_count'], $r['early_minutes'], $r['overtime_minutes'], $r['outside_radius'], $r['mocked']]);
            }

            fclose($out);
        }, "rekap-absensi-{$month}.csv", ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
