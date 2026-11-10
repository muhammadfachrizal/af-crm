<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ $title }} - {{ $company }}</title>
    <style>
        @page { size: A4 landscape; margin: 12mm; }
        body { font-family: Arial, Helvetica, sans-serif; font-size: 11px; color: #0f172a; margin: 0; }
        h1 { font-size: 18px; margin: 0 0 2px; }
        .meta { color: #64748b; margin-bottom: 12px; }
        table { border-collapse: collapse; width: 100%; }
        th { background: #e6f4f2; text-align: left; }
        th, td { border: 1px solid #cbd5e1; padding: 4px 6px; vertical-align: top; }
        td.r, th.r { text-align: right; }
        tr:nth-child(even) td { background: #f8fafc; }
        .actions { margin-bottom: 12px; }
        .actions button { padding: 6px 14px; }
        .warn { color: #b45309; margin-top: 8px; }
        @media print { .actions { display: none; } thead { display: table-header-group; } tr { page-break-inside: avoid; } }
    </style>
</head>
<body>
    <div class="actions"><button onclick="window.print()">Cetak / Simpan sebagai PDF</button> <button onclick="window.close()">Tutup</button></div>

    <h1>{{ $title }}</h1>
    <div class="meta">
        {{ $company }} &middot; Dicetak {{ now($tz)->format('d M Y H:i') }} oleh {{ $printedBy }}
        @if($period) &middot; Periode {{ $period }} @endif
        &middot; {{ count($rows) }} baris
    </div>

    <table>
        <thead>
            <tr>
                <th class="r" style="width:28px">#</th>
                @foreach($columns as $c)
                    <th class="{{ in_array($c['type'], ['money', 'int']) ? 'r' : '' }}">{{ $c['label'] }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach($rows as $i => $row)
                <tr>
                    <td class="r">{{ $i + 1 }}</td>
                    @foreach($columns as $j => $c)
                        @php $v = $row[$j] ?? null; @endphp
                        <td class="{{ in_array($c['type'], ['money', 'int']) ? 'r' : '' }}">
                            @if($v === null || $v === '')
                            @elseif($c['type'] === 'money'){{ number_format((float) $v, 0, ',', '.') }}
                            @elseif($c['type'] === 'int' && is_numeric($v)){{ number_format((float) $v, 0, ',', '.') }}
                            @else{{ $v }}@endif
                        </td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>

    @if($truncated)<div class="warn">Dibatasi 2.000 baris pertama untuk cetak. Gunakan Excel/CSV untuk data lengkap.</div>@endif

    <script>window.addEventListener('load', function () { setTimeout(function () { window.print(); }, 400); });</script>
</body>
</html>
