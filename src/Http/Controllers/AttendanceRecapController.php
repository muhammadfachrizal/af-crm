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

    public function notifications()
    {
        return view('laravel-crm::notifications.index');
    }

    public function holidays()
    {
        return view('laravel-crm::holidays.index');
    }

    public function push()
    {
        return view('laravel-crm::push-notifications.index');
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

        if ($request->query('format') === 'xlsx') {
            $cols = [
                ['label' => 'User', 'type' => 'text'], ['label' => 'Shift', 'type' => 'text'],
                ['label' => 'Hari kerja', 'type' => 'int'], ['label' => 'Hadir', 'type' => 'int'], ['label' => 'Cuti/Izin', 'type' => 'int'], ['label' => 'Tidak hadir', 'type' => 'int'],
                ['label' => 'Telat (x)', 'type' => 'int'], ['label' => 'Telat (menit)', 'type' => 'int'],
                ['label' => 'Pulang cepat (x)', 'type' => 'int'], ['label' => 'Pulang cepat (menit)', 'type' => 'int'],
                ['label' => 'Lembur (menit)', 'type' => 'int'], ['label' => 'Luar radius', 'type' => 'int'], ['label' => 'GPS palsu', 'type' => 'int'],
            ];
            $data = $rows->map(fn ($r) => [$r['name'], $r['shift'], $r['workdays'], $r['present'], $r['leave_days'], $r['absent'], $r['late_count'], $r['late_minutes'], $r['early_count'], $r['early_minutes'], $r['overtime_minutes'], $r['outside_radius'], $r['mocked']])->values()->all();
            $path = \VentureDrake\LaravelCrm\Support\SimpleXlsx::build([['name' => 'Rekap '.$month, 'columns' => $cols, 'rows' => $data]]);

            return response()->download($path, "rekap-absensi-{$month}.xlsx")->deleteFileAfterSend(true);
        }

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // BOM agar Excel membaca UTF-8
            fputcsv($out, ['User', 'Shift', 'Hari kerja', 'Hadir', 'Cuti/Izin', 'Tidak hadir', 'Telat (x)', 'Telat (menit)', 'Pulang cepat (x)', 'Pulang cepat (menit)', 'Lembur (menit)', 'Luar radius', 'GPS palsu']);

            foreach ($rows as $r) {
                fputcsv($out, [$r['name'], $r['shift'], $r['workdays'], $r['present'], $r['leave_days'], $r['absent'], $r['late_count'], $r['late_minutes'], $r['early_count'], $r['early_minutes'], $r['overtime_minutes'], $r['outside_radius'], $r['mocked']]);
            }

            fclose($out);
        }, "rekap-absensi-{$month}.csv", ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
