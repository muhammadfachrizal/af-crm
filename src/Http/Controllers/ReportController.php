<?php

namespace VentureDrake\LaravelCrm\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use VentureDrake\LaravelCrm\Support\ReportService;
use VentureDrake\LaravelCrm\Support\SimpleXlsx;

class ReportController extends Controller
{
    protected function params(Request $request): array
    {
        $from = $this->date($request->query('from'), Carbon::now()->startOfMonth());
        $to = $this->date($request->query('to'), Carbon::now());

        if ($to->lt($from)) {
            [$from, $to] = [$to, $from];
        }

        return [$from, $to, $request->query('user') ? (int) $request->query('user') : null];
    }

    protected function date(?string $value, Carbon $default): Carbon
    {
        try {
            return $value ? Carbon::parse($value) : $default;
        } catch (\Throwable $e) {
            return $default;
        }
    }

    public function index()
    {
        return view('laravel-crm::reports.index', ['reports' => ReportService::REPORTS]);
    }

    public function show(Request $request, string $key)
    {
        [$from, $to, $userId] = $this->params($request);
        $userModel = config('auth.providers.users.model');

        return view('laravel-crm::reports.show', [
            'key' => $key,
            'report' => ReportService::build($key, $from, $to, $userId),
            'from' => $from,
            'to' => $to,
            'userId' => $userId,
            'users' => $userModel::orderBy('name')->get(['id', 'name']),
            'reports' => ReportService::REPORTS,
        ]);
    }

    public function export(Request $request, string $key, string $format)
    {
        abort_unless(in_array($format, ['xlsx', 'csv'], true), 404);

        [$from, $to, $userId] = $this->params($request);
        $report = ReportService::build($key, $from, $to, $userId);
        $file = 'laporan-'.$key.'-'.$from->format('Ymd').'-'.$to->format('Ymd');

        if ($format === 'xlsx') {
            $sheets = collect($report['sections'])->map(fn ($s) => [
                'name' => $s['title'],
                'columns' => $s['columns'],
                'rows' => $s['rows'],
            ])->all();

            $path = SimpleXlsx::build($sheets);

            return response()->download($path, $file.'.xlsx', [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ])->deleteFileAfterSend(true);
        }

        return response()->streamDownload(function () use ($report) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, [$report['title'], $report['period']]);

            foreach ($report['sections'] as $section) {
                fputcsv($out, []);
                fputcsv($out, [$section['title']]);
                fputcsv($out, array_column($section['columns'], 'label'));

                foreach ($section['rows'] as $row) {
                    fputcsv($out, $row);
                }
            }

            fclose($out);
        }, $file.'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
