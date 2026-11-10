<?php

namespace VentureDrake\LaravelCrm\Http\Controllers;

use Illuminate\Http\Request;
use VentureDrake\LaravelCrm\Support\AttendanceRecapService;
use VentureDrake\LaravelCrm\Support\ExportRegistry;
use VentureDrake\LaravelCrm\Support\SimpleXlsx;
use VentureDrake\LaravelCrm\Models\Tenant;
use VentureDrake\LaravelCrm\Support\CurrentTenant;

/** Export data tiap halaman daftar: Excel (.xlsx), CSV, dan cetak / simpan PDF. */
class ExportController extends Controller
{
    public function download(Request $request, string $resource, string $format)
    {
        abort_unless(ExportRegistry::has($resource) && in_array($format, ['xlsx', 'csv', 'print'], true), 404);

        $def = ExportRegistry::all()[$resource];
        abort_unless($request->user()->can($def['permission']), 403);

        $from = $this->dateOrNull($request->query('from'));
        $to = $this->dateOrNull($request->query('to'));
        $limit = $format === 'print' ? 2000 : 50000;

        $data = ExportRegistry::dataset($resource, $from, $to, $limit);
        $file = 'export-'.$resource.'-'.now()->format('Ymd-His');

        if ($format === 'xlsx') {
            $path = SimpleXlsx::build([[
                'name' => $data['title'],
                'columns' => $data['columns'],
                'rows' => iterator_to_array($data['rows'], false),
            ]]);

            return response()->download($path, $file.'.xlsx', [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ])->deleteFileAfterSend(true);
        }

        if ($format === 'csv') {
            return response()->streamDownload(function () use ($data) {
                $out = fopen('php://output', 'w');
                fwrite($out, "\xEF\xBB\xBF");
                fputcsv($out, array_column($data['columns'], 'label'));

                foreach ($data['rows'] as $row) {
                    fputcsv($out, $row);
                }

                fclose($out);
            }, $file.'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
        }

        $rows = iterator_to_array($data['rows'], false);
        $tenant = CurrentTenant::id() ? Tenant::find(CurrentTenant::id()) : null;

        return view('laravel-crm::exports.print', [
            'title' => $data['title'],
            'columns' => $data['columns'],
            'rows' => $rows,
            'truncated' => count($rows) >= $limit,
            'company' => $tenant->name ?? config('app.name'),
            'printedBy' => $request->user()->name,
            'tz' => AttendanceRecapService::timezone(),
            'period' => ($from || $to) ? trim(($from ?: '...').' s/d '.($to ?: '...')) : null,
        ]);
    }

    protected function dateOrNull(?string $v): ?string
    {
        return $v && preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) ? $v : null;
    }
}
