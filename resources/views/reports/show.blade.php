@php
    $fmt = function ($v, $type) {
        if ($v === null || $v === '') return '';
        return match ($type) {
            'money' => number_format((float) $v, 0, ',', '.'),
            'percent' => number_format((float) $v, 1, ',', '.').' %',
            'int' => (is_numeric($v) && floor($v) != $v) ? number_format((float) $v, 1, ',', '.') : (is_numeric($v) ? number_format((float) $v, 0, ',', '.') : e($v)),
            default => e($v),
        };
    };
    $q = ['from' => $from->format('Y-m-d'), 'to' => $to->format('Y-m-d'), 'user' => $userId];
@endphp
<x-crm::app-layout :title="$report['title']">
    <style>
        @media print {
            .no-print, nav, aside, .drawer-side, .navbar { display: none !important; }
            .crm-content { margin: 0 !important; padding: 0 !important; }
            table { font-size: 11px; }
            .grid { display: block !important; }
            svg { max-height: 260px; }
        }
    </style>

    <div class="crm-content">
        <x-mary-header :title="$report['title']" :subtitle="$report['period']" class="mb-4">
            <x-slot:actions>
                <span class="no-print flex gap-2">
                    <x-mary-button label="Excel" icon="o-table-cells" class="btn-sm btn-primary text-white" link="{{ url(route('laravel-crm.reports.export', ['key' => $key, 'format' => 'xlsx'] + $q)) }}" external />
                    <x-mary-button label="CSV" icon="o-arrow-down-tray" class="btn-sm" link="{{ url(route('laravel-crm.reports.export', ['key' => $key, 'format' => 'csv'] + $q)) }}" external />
                    <x-mary-button label="Cetak / PDF" icon="o-printer" class="btn-sm" onclick="window.print()" />
                </span>
            </x-slot:actions>
        </x-mary-header>

        <form method="GET" class="no-print flex flex-wrap items-end gap-3 mb-4">
            <div><label class="label text-xs">Dari</label><input type="date" name="from" value="{{ $from->format('Y-m-d') }}" class="input input-bordered input-sm"></div>
            <div><label class="label text-xs">Sampai</label><input type="date" name="to" value="{{ $to->format('Y-m-d') }}" class="input input-bordered input-sm"></div>
            <div>
                <label class="label text-xs">User</label>
                <select name="user" class="select select-bordered select-sm">
                    <option value="">Semua</option>
                    @foreach($users as $u)
                        <option value="{{ $u->id }}" @selected($userId == $u->id)>{{ $u->name }}</option>
                    @endforeach
                </select>
            </div>
            <button class="btn btn-sm btn-primary text-white">Tampilkan</button>
            <a href="{{ url(route('laravel-crm.reports.index')) }}" class="btn btn-sm btn-ghost">Semua laporan</a>
        </form>

        @if(! empty($report['charts']))
            <div class="grid lg:grid-cols-2 gap-4 mb-4">
                @foreach($report['charts'] as $chart)
                    <x-mary-card :title="$chart['title']" shadow class="{{ in_array($chart['type'], ['hbar']) && count($chart['labels']) > 8 ? 'lg:col-span-2' : '' }}">
                        {!! \VentureDrake\LaravelCrm\Support\SvgChart::render($chart) !!}
                    </x-mary-card>
                @endforeach
            </div>
        @endif

        @foreach($report['sections'] as $section)
            <x-mary-card :title="$section['title']" shadow class="mb-4">
                <div class="overflow-x-auto">
                    <table class="table table-zebra table-sm whitespace-nowrap">
                        <thead>
                            <tr>
                                @foreach($section['columns'] as $c)
                                    <th class="{{ in_array($c['type'], ['money', 'int', 'percent']) ? 'text-right' : '' }}">{{ $c['label'] }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                        @forelse($section['rows'] as $row)
                            <tr>
                                @foreach($section['columns'] as $i => $c)
                                    <td class="{{ in_array($c['type'], ['money', 'int', 'percent']) ? 'text-right' : '' }}">
                                        {!! $fmt($row[$i] ?? null, $c['type']) !!}
                                        @if($c['type'] === 'percent' && ($row[$i] ?? null) !== null)
                                            <progress class="progress progress-primary w-16 ml-2 no-print align-middle" value="{{ min(100, (float) $row[$i]) }}" max="100"></progress>
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                        @empty
                            <tr><td colspan="{{ count($section['columns']) }}" class="opacity-60">Tidak ada data pada periode ini.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </x-mary-card>
        @endforeach

        <p class="text-xs opacity-60">Nilai dalam mata uang data (rupiah bila mata uang deal IDR). Laporan hanya memuat data tenant Anda. Tahap/forecast memakai probabilitas tahap pipeline.</p>
    </div>
</x-crm::app-layout>
