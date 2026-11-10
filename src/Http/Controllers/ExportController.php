<?php

namespace VentureDrake\LaravelCrm\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use VentureDrake\LaravelCrm\Support\AttendanceRecapService;
use VentureDrake\LaravelCrm\Support\ExportRegistry;
use VentureDrake\LaravelCrm\Support\SimpleXlsx;
use VentureDrake\LaravelCrm\Models\Tenant;
use VentureDrake\LaravelCrm\Support\CurrentTenant;

/** Export data tiap halaman daftar: Excel (.xlsx), CSV, dan cetak / simpan PDF. */
class ExportController extends Controller
{
    /** Modul -> [komponen daftar Livewire, nama method yang mengembalikan paginator]. */
    protected const LISTS = [
        'leads' => ['VentureDrake\\LaravelCrm\\Livewire\\Leads\\LeadIndex', 'leads'],
        'deals' => ['VentureDrake\\LaravelCrm\\Livewire\\Deals\\DealIndex', 'deals'],
        'people' => ['VentureDrake\\LaravelCrm\\Livewire\\People\\PersonIndex', 'people'],
        'organizations' => ['VentureDrake\\LaravelCrm\\Livewire\\Organizations\\OrganizationIndex', 'organizations'],
        'quotes' => ['VentureDrake\\LaravelCrm\\Livewire\\Quotes\\QuoteIndex', 'quotes'],
        'orders' => ['VentureDrake\\LaravelCrm\\Livewire\\Orders\\OrderIndex', 'orders'],
        'invoices' => ['VentureDrake\\LaravelCrm\\Livewire\\Invoices\\InvoiceIndex', 'invoices'],
        'products' => ['VentureDrake\\LaravelCrm\\Livewire\\Products\\ProductIndex', 'products'],
        'tasks' => ['VentureDrake\\LaravelCrm\\Livewire\\Tasks\\TaskIndex', 'tasks'],
        'visits' => ['VentureDrake\\LaravelCrm\\Livewire\\Visits\\VisitIndex', 'visits'],
        'attendances' => ['VentureDrake\\LaravelCrm\\Livewire\\Attendances\\AttendanceIndex', 'attendances'],
        'users' => ['VentureDrake\\LaravelCrm\\Livewire\\Users\\UserIndex', 'users'],
    ];

    /**
     * Pakai query komponen daftar yang sama dengan layar: filter (pemilik, label, sumber, status, tanggal, dst.),
     * pencarian, urutan, dan aturan hak akses ikut persis. Halaman diambil 25 baris per iterasi sampai habis.
     */
    protected function filteredPages(Request $request, string $resource, int $limit): ?callable
    {
        [$class, $method] = self::LISTS[$resource] ?? [null, null];

        if (! $class || ! class_exists($class) || ! method_exists($class, $method)) {
            return null;
        }

        $component = new $class;
        $ref = new \ReflectionObject($component);

        foreach ($request->query() as $key => $value) {
            if (in_array($key, ['layout', 'showFilters', 'page'], true) || ! $ref->hasProperty($key)) {
                continue;
            }

            $prop = $ref->getProperty($key);
            $type = $prop->getType();

            if (! $prop->isPublic() || ! $type instanceof \ReflectionNamedType) {
                continue;
            }

            if ($type->getName() === 'string' && is_string($value)) {
                $component->$key = $value;
            } elseif ($type->getName() === 'array' && is_array($value)) {
                if ($key === 'sortBy') {
                    $col = $value['column'] ?? null;
                    $dir = strtolower((string) ($value['direction'] ?? 'desc'));

                    if (is_string($col) && preg_match('/^[A-Za-z0-9_.]+$/', $col) && in_array($dir, ['asc', 'desc'], true)) {
                        $component->sortBy = ['column' => $col, 'direction' => $dir];
                    }
                } else {
                    $component->$key = array_values(array_filter($value, 'is_scalar'));
                }
            }
        }

        return function () use ($component, $method, $limit) {
            $page = 1;
            $count = 0;

            while (true) {
                $current = $page;
                Paginator::currentPageResolver(fn () => $current);

                $paginator = $component->$method();
                $items = $paginator->getCollection();

                if ($items->isEmpty()) {
                    return;
                }

                yield $items;

                $count += $items->count();

                if ($count >= $limit || ! $paginator->hasMorePages()) {
                    return;
                }

                $page++;
            }
        };
    }

    public function download(Request $request, string $resource, string $format)
    {
        abort_unless(ExportRegistry::has($resource) && in_array($format, ['xlsx', 'csv', 'print'], true), 404);

        $def = ExportRegistry::all()[$resource];
        abort_unless($request->user()->can($def['permission']), 403);

        $from = $this->dateOrNull($request->query('from'));
        $to = $this->dateOrNull($request->query('to'));
        $limit = $format === 'print' ? 2000 : 50000;

        $pages = $this->filteredPages($request, $resource, $limit);
        $data = ExportRegistry::dataset($resource, $from, $to, $limit, $pages);
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
            'filtered' => (bool) $pages && count(array_diff_key($request->query(), ['page' => 1])) > 0,
            'period' => ($from || $to) ? trim(($from ?: '...').' s/d '.($to ?: '...')) : null,
        ]);
    }

    protected function dateOrNull(?string $v): ?string
    {
        return $v && preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) ? $v : null;
    }
}
