<?php

namespace VentureDrake\LaravelCrm\Support;

use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;
use VentureDrake\LaravelCrm\Models\Deal;
use VentureDrake\LaravelCrm\Models\Invoice;
use VentureDrake\LaravelCrm\Models\Lead;
use VentureDrake\LaravelCrm\Models\LeadSource;
use VentureDrake\LaravelCrm\Models\Order;
use VentureDrake\LaravelCrm\Models\Quote;
use VentureDrake\LaravelCrm\Models\Task;
use VentureDrake\LaravelCrm\Models\Visit;

/**
 * Laporan penjualan & aktivitas. Semua query memakai model ber-BelongsToTenant,
 * jadi otomatis hanya data tenant aktif. Nilai uang disimpan ×100 di database -> dibagi 100 di sini.
 *
 * Bentuk hasil: ['title' => ..., 'sections' => [['title','columns'=>[['label','type']],'rows'=>[[...]]]]]
 * type: text | money | int | percent
 */
class ReportService
{
    public const REPORTS = [
        'summary' => ['Ringkasan Penjualan', 'Angka utama lead, deal, quote, order, dan piutang'],
        'funnel' => ['Funnel & Konversi', 'Dari lead sampai deal menang, plus pipeline per tahap'],
        'forecast' => ['Forecast Pipeline', 'Deal terbuka per bulan perkiraan closing (nilai tertimbang)'],
        'sales' => ['Kinerja Sales', 'Perbandingan antar user: lead, deal, visit, task'],
        'sources' => ['Sumber Lead', 'Lead dan konversi per sumber'],
        'ar' => ['Piutang (Aging)', 'Invoice belum lunas berdasarkan umur jatuh tempo'],
        'visits' => ['Laporan Visit', 'Rekap kunjungan per petugas dan detailnya'],
    ];

    protected static function m($v): float
    {
        return round(((int) $v) / 100, 2);
    }

    protected static function pct($a, $b): float
    {
        return $b > 0 ? round($a / $b * 100, 1) : 0.0;
    }

    protected static function owner($q, ?int $userId, string $col = 'user_owner_id')
    {
        return $userId ? $q->where($col, $userId) : $q;
    }

    public static function build(string $key, Carbon $from, Carbon $to, ?int $userId = null): array
    {
        abort_unless(isset(static::REPORTS[$key]), 404);

        $data = static::{'report'.ucfirst($key)}($from->copy()->startOfDay(), $to->copy()->endOfDay(), $userId);
        $data['charts'] = static::charts($key, $data, $from->copy()->startOfDay(), $to->copy()->endOfDay(), $userId);
        $data['title'] = static::REPORTS[$key][0];
        $data['period'] = $from->format('d M Y').' - '.$to->format('d M Y');

        return $data;
    }

    /** Spesifikasi grafik per laporan (dirender SvgChart). Diturunkan dari data tabel agar angkanya selalu sama. */
    protected static function charts(string $key, array $data, Carbon $from, Carbon $to, ?int $u): array
    {
        $rows = fn (int $i) => $data['sections'][$i]['rows'] ?? [];
        $col = fn (array $r, int $c) => array_map(fn ($row) => $row[$c] ?? 0, $r);
        $labels = fn (array $r) => array_map(fn ($row) => (string) $row[0], $r);

        switch ($key) {
            case 'summary':
                $val = function (string $label) use ($rows) {
                    foreach ($rows(0) as $r) {
                        if ($r[0] === $label) {
                            return (float) $r[1];
                        }
                    }

                    return 0.0;
                };

                $months = [];
                $leadsTrend = [];
                $wonTrend = [];
                for ($i = 5; $i >= 0; $i--) {
                    $m = $to->copy()->startOfMonth()->subMonths($i);
                    $range = [$m->copy()->startOfMonth(), $m->copy()->endOfMonth()];
                    $months[] = $m->translatedFormat('M y');
                    $leadsTrend[] = static::owner(Lead::whereBetween('created_at', $range), $u)->count();
                    $wonTrend[] = static::m(static::owner(Deal::where('closed_status', 'won')->whereBetween('closed_at', $range), $u)->sum('amount'));
                }

                return [
                    ['type' => 'donut', 'title' => 'Hasil deal pada periode (menang vs kalah)', 'labels' => ['Menang', 'Kalah'],
                        'series' => [['name' => 'Deal', 'values' => [$val('Deal menang'), $val('Deal kalah')]]]],
                    ['type' => 'line', 'title' => 'Tren 6 bulan: lead baru', 'labels' => $months, 'format' => 'int',
                        'series' => [['name' => 'Lead baru', 'values' => $leadsTrend, 'color' => '#2563eb']]],
                    ['type' => 'bar', 'title' => 'Tren 6 bulan: nilai deal menang', 'labels' => $months, 'format' => 'money',
                        'series' => [['name' => 'Nilai menang', 'values' => $wonTrend]]],
                ];

            case 'funnel':
                return [
                    ['type' => 'hbar', 'title' => 'Funnel (jumlah per tahap)', 'labels' => $labels($rows(0)), 'format' => 'int',
                        'series' => [['name' => 'Jumlah', 'values' => $col($rows(0), 1)]]],
                    ['type' => 'hbar', 'title' => 'Pipeline terbuka per tahap', 'labels' => $labels($rows(1)), 'format' => 'money',
                        'series' => [['name' => 'Nilai', 'values' => $col($rows(1), 2)], ['name' => 'Tertimbang', 'values' => $col($rows(1), 4)]]],
                ];

            case 'forecast':
                return [['type' => 'bar', 'title' => 'Forecast per bulan perkiraan closing', 'labels' => $labels($rows(0)), 'format' => 'money',
                    'series' => [['name' => 'Nilai', 'values' => $col($rows(0), 2)], ['name' => 'Tertimbang', 'values' => $col($rows(0), 3)]]]];

            case 'sales':
                return [
                    ['type' => 'hbar', 'title' => 'Nilai deal menang vs nilai pipeline per user', 'labels' => $labels($rows(0)), 'format' => 'money',
                        'series' => [['name' => 'Menang', 'values' => $col($rows(0), 4)], ['name' => 'Pipeline', 'values' => $col($rows(0), 8)]]],
                    ['type' => 'hbar', 'title' => 'Visit dijadwalkan vs selesai per user', 'labels' => $labels($rows(0)), 'format' => 'int',
                        'series' => [['name' => 'Dijadwalkan', 'values' => $col($rows(0), 9)], ['name' => 'Selesai', 'values' => $col($rows(0), 10)]]],
                ];

            case 'sources':
                return [
                    ['type' => 'donut', 'title' => 'Komposisi lead per sumber', 'labels' => $labels($rows(0)), 'series' => [['name' => 'Lead', 'values' => $col($rows(0), 1)]]],
                    ['type' => 'hbar', 'title' => 'Konversi % per sumber', 'labels' => $labels($rows(0)), 'format' => 'percent', 'series' => [['name' => 'Konversi %', 'values' => $col($rows(0), 4)]]],
                ];

            case 'ar':
                return [['type' => 'bar', 'title' => 'Piutang berdasarkan umur', 'labels' => $labels($rows(0)), 'format' => 'money',
                    'series' => [['name' => 'Terutang', 'values' => $col($rows(0), 2), 'colors' => ['#16a34a', '#f59e0b', '#ea580c', '#dc2626', '#7f1d1d']]]]];

            case 'visits':
                return [['type' => 'bar', 'title' => 'Visit per petugas', 'labels' => $labels($rows(0)), 'format' => 'int',
                    'series' => [['name' => 'Dijadwalkan', 'values' => $col($rows(0), 1)], ['name' => 'Selesai', 'values' => $col($rows(0), 2)]]]];
        }

        return [];
    }

    protected static function reportSummary(Carbon $from, Carbon $to, ?int $u): array
    {
        $range = [$from, $to];
        $leads = static::owner(Lead::whereBetween('created_at', $range), $u)->count();
        $converted = static::owner(Lead::whereBetween('converted_at', $range), $u)->count();

        $won = static::owner(Deal::where('closed_status', 'won')->whereBetween('closed_at', $range), $u)->get();
        $lost = static::owner(Deal::where('closed_status', 'lost')->whereBetween('closed_at', $range), $u)->count();
        $open = static::owner(Deal::whereNull('closed_status'), $u)->get(['amount']);
        $created = static::owner(Deal::whereBetween('created_at', $range), $u)->count();

        $wonValue = $won->sum('amount');
        $days = $won->filter(fn ($d) => $d->closed_at)->map(fn ($d) => $d->created_at->diffInDays($d->closed_at))->avg();

        $quotes = static::owner(Quote::whereBetween('created_at', $range), $u);
        $quotesAccepted = static::owner(Quote::whereBetween('accepted_at', $range), $u)->count();
        $orders = static::owner(Order::whereBetween('created_at', $range), $u);
        $invoices = static::owner(Invoice::whereBetween('created_at', $range), $u);
        $outstanding = static::owner(Invoice::whereNull('fully_paid_at')->where('amount_due', '>', 0), $u);

        $rows = [
            ['Lead baru', $leads, null],
            ['Lead terkonversi (jadi deal)', $converted, static::pct($converted, $leads).' % dari lead baru'],
            ['Deal dibuat', $created, null],
            ['Deal menang', $won->count(), null],
            ['Nilai deal menang', static::m($wonValue), null],
            ['Deal kalah', $lost, null],
            ['Win rate', static::pct($won->count(), $won->count() + $lost), 'menang / (menang + kalah)'],
            ['Rata-rata nilai deal menang', static::m($won->count() ? $wonValue / $won->count() : 0), null],
            ['Rata-rata hari sampai closing', round((float) $days, 1), 'dari deal dibuat sampai menang'],
            ['Deal terbuka (sekarang)', $open->count(), null],
            ['Nilai pipeline terbuka (sekarang)', static::m($open->sum('amount')), null],
            ['Quote dibuat', (clone $quotes)->count(), null],
            ['Nilai quote dibuat', static::m((clone $quotes)->sum('total')), null],
            ['Quote diterima', $quotesAccepted, null],
            ['Order dibuat', (clone $orders)->count(), null],
            ['Nilai order', static::m((clone $orders)->sum('total')), null],
            ['Invoice terbit', (clone $invoices)->count(), null],
            ['Nilai invoice terbit', static::m((clone $invoices)->sum('total')), null],
            ['Piutang belum lunas (sekarang)', static::m((clone $outstanding)->sum('amount_due')), (clone $outstanding)->count().' invoice'],
        ];

        return ['sections' => [[
            'title' => 'Ringkasan',
            'columns' => [['label' => 'Indikator', 'type' => 'text'], ['label' => 'Nilai', 'type' => 'int'], ['label' => 'Keterangan', 'type' => 'text']],
            'rows' => $rows,
        ]]];
    }

    protected static function reportFunnel(Carbon $from, Carbon $to, ?int $u): array
    {
        $range = [$from, $to];
        $leads = static::owner(Lead::whereBetween('created_at', $range), $u);
        $total = (clone $leads)->count();
        $qualified = (clone $leads)->where('qualified', true)->count();
        $converted = (clone $leads)->whereNotNull('converted_at')->count();
        $deals = static::owner(Deal::whereBetween('created_at', $range), $u);
        $dealsCreated = (clone $deals)->count();
        $dealsWon = (clone $deals)->where('closed_status', 'won')->count();

        $steps = [['Lead dibuat', $total], ['Lead qualified', $qualified], ['Lead terkonversi', $converted], ['Deal dibuat', $dealsCreated], ['Deal menang', $dealsWon]];
        $rows = [];
        foreach ($steps as $i => [$label, $n]) {
            $rows[] = [$label, $n, $i ? static::pct($n, $steps[$i - 1][1]) : 100.0, static::pct($n, $steps[0][1])];
        }

        // Pipeline terbuka per tahap
        $stageRows = [];
        $open = static::owner(Deal::whereNull('closed_status'), $u)
            ->with(['pipelineStage.pipelineStageProbability', 'pipelineStage.pipeline'])->get()
            ->groupBy('pipeline_stage_id');

        foreach ($open as $stageId => $group) {
            $stage = $group->first()->pipelineStage;
            $pct = (int) optional(optional($stage)->pipelineStageProbability)->percent;
            $stageRows[] = [
                (optional(optional($stage)->pipeline)->name ?? '-').' / '.(optional($stage)->name ?? 'Tanpa tahap'),
                $group->count(),
                static::m($group->sum('amount')),
                $pct,
                static::m($group->sum('amount') * $pct / 100),
            ];
        }

        usort($stageRows, fn ($a, $b) => strcmp($a[0], $b[0]));

        return ['sections' => [
            ['title' => 'Funnel periode', 'columns' => [
                ['label' => 'Tahap', 'type' => 'text'], ['label' => 'Jumlah', 'type' => 'int'],
                ['label' => '% dari tahap sebelumnya', 'type' => 'percent'], ['label' => '% dari lead dibuat', 'type' => 'percent'],
            ], 'rows' => $rows],
            ['title' => 'Pipeline terbuka per tahap (saat ini)', 'columns' => [
                ['label' => 'Pipeline / tahap', 'type' => 'text'], ['label' => 'Jumlah deal', 'type' => 'int'],
                ['label' => 'Nilai', 'type' => 'money'], ['label' => 'Probabilitas %', 'type' => 'int'], ['label' => 'Nilai tertimbang', 'type' => 'money'],
            ], 'rows' => $stageRows],
        ]];
    }

    protected static function reportForecast(Carbon $from, Carbon $to, ?int $u): array
    {
        $deals = static::owner(Deal::whereNull('closed_status'), $u)
            ->with('pipelineStage.pipelineStageProbability')->get();

        $buckets = [];
        $today = Carbon::today();

        foreach ($deals as $d) {
            $pct = (int) optional(optional($d->pipelineStage)->pipelineStageProbability)->percent;

            if (! $d->expected_close) {
                $label = '9999-99|Tanpa tanggal perkiraan';
            } elseif ($d->expected_close->lt($today)) {
                $label = '0000-00|Lewat tanggal perkiraan';
            } else {
                $label = $d->expected_close->format('Y-m').'|'.$d->expected_close->translatedFormat('F Y');
            }

            $buckets[$label]['n'] = ($buckets[$label]['n'] ?? 0) + 1;
            $buckets[$label]['v'] = ($buckets[$label]['v'] ?? 0) + $d->amount;
            $buckets[$label]['w'] = ($buckets[$label]['w'] ?? 0) + ($d->amount * $pct / 100);
        }

        ksort($buckets);
        $rows = [];
        foreach ($buckets as $k => $b) {
            $rows[] = [explode('|', $k, 2)[1], $b['n'], static::m($b['v']), static::m($b['w'])];
        }

        $detail = $deals->sortByDesc('amount')->take(25)->map(function ($d) {
            $pct = (int) optional(optional($d->pipelineStage)->pipelineStageProbability)->percent;

            return [$d->title, optional($d->pipelineStage)->name ?? '-', optional($d->expected_close)->format('d M Y') ?? '-', static::m($d->amount), $pct, static::m($d->amount * $pct / 100)];
        })->values()->all();

        return ['sections' => [
            ['title' => 'Per bulan perkiraan closing', 'columns' => [
                ['label' => 'Bulan', 'type' => 'text'], ['label' => 'Jumlah deal', 'type' => 'int'],
                ['label' => 'Nilai', 'type' => 'money'], ['label' => 'Nilai tertimbang', 'type' => 'money'],
            ], 'rows' => $rows],
            ['title' => '25 deal terbesar yang masih terbuka', 'columns' => [
                ['label' => 'Deal', 'type' => 'text'], ['label' => 'Tahap', 'type' => 'text'], ['label' => 'Perkiraan closing', 'type' => 'text'],
                ['label' => 'Nilai', 'type' => 'money'], ['label' => 'Probabilitas %', 'type' => 'int'], ['label' => 'Tertimbang', 'type' => 'money'],
            ], 'rows' => $detail],
        ]];
    }

    protected static function reportSales(Carbon $from, Carbon $to, ?int $u): array
    {
        $range = [$from, $to];
        $userModel = config('auth.providers.users.model');
        $rows = [];

        foreach ($userModel::when($u, fn ($q) => $q->where('id', $u))->orderBy('name')->get() as $user) {
            $won = Deal::where('user_owner_id', $user->id)->where('closed_status', 'won')->whereBetween('closed_at', $range)->get(['amount']);
            $lost = Deal::where('user_owner_id', $user->id)->where('closed_status', 'lost')->whereBetween('closed_at', $range)->count();
            $open = Deal::where('user_owner_id', $user->id)->whereNull('closed_status')->get(['amount']);
            $visits = Visit::where('user_id', $user->id)->whereBetween('scheduled_at', $range);
            $tasksDone = Task::where('user_assigned_id', $user->id)->whereBetween('completed_at', $range)->count();

            $rows[] = [
                $user->name,
                Lead::where('user_owner_id', $user->id)->whereBetween('created_at', $range)->count(),
                Lead::where('user_owner_id', $user->id)->whereBetween('converted_at', $range)->count(),
                $won->count(),
                static::m($won->sum('amount')),
                $lost,
                static::pct($won->count(), $won->count() + $lost),
                $open->count(),
                static::m($open->sum('amount')),
                (clone $visits)->count(),
                (clone $visits)->whereNotNull('checked_in_at')->count(),
                $tasksDone,
            ];
        }

        usort($rows, fn ($a, $b) => $b[4] <=> $a[4]);

        return ['sections' => [['title' => 'Per user (urut nilai deal menang)', 'columns' => [
            ['label' => 'User', 'type' => 'text'], ['label' => 'Lead baru', 'type' => 'int'], ['label' => 'Lead terkonversi', 'type' => 'int'],
            ['label' => 'Deal menang', 'type' => 'int'], ['label' => 'Nilai menang', 'type' => 'money'], ['label' => 'Deal kalah', 'type' => 'int'],
            ['label' => 'Win rate %', 'type' => 'percent'], ['label' => 'Deal terbuka', 'type' => 'int'], ['label' => 'Nilai pipeline', 'type' => 'money'],
            ['label' => 'Visit dijadwalkan', 'type' => 'int'], ['label' => 'Visit selesai', 'type' => 'int'], ['label' => 'Task selesai', 'type' => 'int'],
        ], 'rows' => $rows]]];
    }

    protected static function reportSources(Carbon $from, Carbon $to, ?int $u): array
    {
        $range = [$from, $to];
        $rows = [];

        foreach (LeadSource::orderBy('name')->get() as $src) {
            $leads = static::owner(Lead::where('lead_source_id', $src->id)->whereBetween('created_at', $range), $u)->get(['id', 'qualified', 'converted_at']);

            if ($leads->isEmpty()) {
                continue;
            }

            $won = Deal::where('closed_status', 'won')->whereBetween('closed_at', $range)
                ->whereIn('lead_id', $leads->pluck('id'))->get(['amount']);

            $rows[] = [
                $src->name, $leads->count(), $leads->where('qualified', true)->count(),
                $leads->whereNotNull('converted_at')->count(),
                static::pct($leads->whereNotNull('converted_at')->count(), $leads->count()),
                $won->count(), static::m($won->sum('amount')),
            ];
        }

        $none = static::owner(Lead::whereNull('lead_source_id')->whereBetween('created_at', $range), $u)->count();
        if ($none) {
            $rows[] = ['(Tanpa sumber)', $none, null, null, null, null, null];
        }

        return ['sections' => [['title' => 'Per sumber lead', 'columns' => [
            ['label' => 'Sumber', 'type' => 'text'], ['label' => 'Lead', 'type' => 'int'], ['label' => 'Qualified', 'type' => 'int'],
            ['label' => 'Terkonversi', 'type' => 'int'], ['label' => 'Konversi %', 'type' => 'percent'],
            ['label' => 'Deal menang', 'type' => 'int'], ['label' => 'Nilai menang', 'type' => 'money'],
        ], 'rows' => $rows]]];
    }

    protected static function reportAr(Carbon $from, Carbon $to, ?int $u): array
    {
        $today = Carbon::today();
        $invoices = static::owner(Invoice::whereNull('fully_paid_at')->where('amount_due', '>', 0), $u)
            ->with('organization:id,name', 'person')->orderBy('due_date')->get();

        $buckets = ['Belum jatuh tempo' => [0, 0], '1-30 hari' => [0, 0], '31-60 hari' => [0, 0], '61-90 hari' => [0, 0], '> 90 hari' => [0, 0]];
        $detail = [];

        foreach ($invoices as $inv) {
            $late = $inv->due_date ? $today->diffInDays($inv->due_date, false) * -1 : 0; // positif = telat

            $b = $late <= 0 ? 'Belum jatuh tempo' : ($late <= 30 ? '1-30 hari' : ($late <= 60 ? '31-60 hari' : ($late <= 90 ? '61-90 hari' : '> 90 hari')));
            $buckets[$b][0]++;
            $buckets[$b][1] += $inv->amount_due;

            if (count($detail) < 300) {
                $detail[] = [
                    (string) ($inv->invoice_id ?? $inv->invoice_number),
                    optional($inv->organization)->name ?: optional($inv->person)->name ?: '-',
                    optional($inv->due_date)->format('d M Y') ?? '-',
                    max(0, (int) $late),
                    static::m($inv->amount_due),
                ];
            }
        }

        $summary = [];
        foreach ($buckets as $label => [$n, $v]) {
            $summary[] = [$label, $n, static::m($v)];
        }

        return ['sections' => [
            ['title' => 'Aging piutang (per hari ini)', 'columns' => [['label' => 'Umur', 'type' => 'text'], ['label' => 'Jumlah invoice', 'type' => 'int'], ['label' => 'Total terutang', 'type' => 'money']], 'rows' => $summary],
            ['title' => 'Detail invoice belum lunas', 'columns' => [
                ['label' => 'Invoice', 'type' => 'text'], ['label' => 'Pelanggan', 'type' => 'text'], ['label' => 'Jatuh tempo', 'type' => 'text'],
                ['label' => 'Terlambat (hari)', 'type' => 'int'], ['label' => 'Terutang', 'type' => 'money'],
            ], 'rows' => $detail],
        ]];
    }

    protected static function reportVisits(Carbon $from, Carbon $to, ?int $u): array
    {
        $visits = Visit::with('user:id,name')->whereBetween('scheduled_at', [$from, $to])
            ->when($u, fn ($q) => $q->where('user_id', $u))->orderBy('scheduled_at')->get();

        $perUser = [];
        foreach ($visits->groupBy('user_id') as $group) {
            $done = $group->whereNotNull('checked_in_at');
            $perUser[] = [
                optional($group->first()->user)->name ?? '-', $group->count(), $done->count(),
                static::pct($done->count(), $group->count()),
                $done->count() ? round($done->avg('distance_meters')) : null,
            ];
        }

        $detail = $visits->take(500)->map(fn ($v) => [
            optional($v->scheduled_at)->format('d M Y H:i'), optional($v->user)->name ?? '-', $v->title ?: '-', $v->target_address ?: '-',
            $v->checked_in_at ? 'Selesai '.$v->checked_in_at->format('H:i') : 'Belum', $v->distance_meters,
        ])->values()->all();

        return ['sections' => [
            ['title' => 'Per petugas', 'columns' => [
                ['label' => 'Petugas', 'type' => 'text'], ['label' => 'Dijadwalkan', 'type' => 'int'], ['label' => 'Selesai', 'type' => 'int'],
                ['label' => 'Selesai %', 'type' => 'percent'], ['label' => 'Rata-rata jarak check-in (m)', 'type' => 'int'],
            ], 'rows' => $perUser],
            ['title' => 'Detail visit (maks 500)', 'columns' => [
                ['label' => 'Jadwal', 'type' => 'text'], ['label' => 'Petugas', 'type' => 'text'], ['label' => 'Judul', 'type' => 'text'],
                ['label' => 'Alamat tujuan', 'type' => 'text'], ['label' => 'Status', 'type' => 'text'], ['label' => 'Jarak check-in (m)', 'type' => 'int'],
            ], 'rows' => $detail],
        ]];
    }
}
