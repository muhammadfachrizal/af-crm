<div class="crm-content">
    <x-mary-header title="Rekap Absensi" subtitle="Kehadiran, keterlambatan, dan lembur per bulan" progress-indicator>
        <x-slot:actions>
            <x-mary-input type="month" wire:model.live="month" class="input-sm" />
            <x-mary-button label="Excel" icon="o-table-cells" class="btn-sm btn-primary text-white" link="{{ url(route('laravel-crm.attendance-recap.export', ['month' => $month, 'format' => 'xlsx'])) }}" external responsive />
            <x-mary-button label="Export CSV" icon="o-arrow-down-tray" class="btn-sm" link="{{ url(route('laravel-crm.attendance-recap.export', ['month' => $month])) }}" external responsive />
        </x-slot:actions>
    </x-mary-header>

    <x-mary-card shadow>
        <div class="overflow-x-auto">
            <table class="table table-zebra whitespace-nowrap">
                <thead>
                    <tr>
                        <th>User</th><th>Shift</th>
                        <th class="text-right">Hari kerja</th><th class="text-right">Hadir</th><th class="text-right">Cuti/Izin</th><th class="text-right">Tidak hadir</th>
                        <th class="text-right">Telat (x / mnt)</th><th class="text-right">Pulang cepat (x / mnt)</th>
                        <th class="text-right">Lembur (mnt)</th><th class="text-right">Luar radius</th><th class="text-right">GPS palsu</th><th>Perangkat</th>
                    </tr>
                </thead>
                <tbody>
                @foreach($rows as $r)
                    <tr>
                        <td class="font-semibold">{{ $r['name'] }}</td>
                        <td>{{ $r['shift'] }}</td>
                        <td class="text-right">{{ $r['workdays'] }}</td>
                        <td class="text-right">{{ $r['present'] }}</td>
                        <td class="text-right">{{ $r['leave_days'] }}</td>
                        <td class="text-right @if($r['absent']) text-error font-semibold @endif">{{ $r['absent'] }}</td>
                        <td class="text-right">{{ $r['late_count'] }} / {{ $r['late_minutes'] }}</td>
                        <td class="text-right">{{ $r['early_count'] }} / {{ $r['early_minutes'] }}</td>
                        <td class="text-right">{{ $r['overtime_minutes'] }}</td>
                        <td class="text-right @if($r['outside_radius']) text-warning @endif">{{ $r['outside_radius'] }}</td>
                        <td class="text-right @if($r['mocked']) text-error font-semibold @endif">{{ $r['mocked'] }}</td>
                        <td>
                            @if($r['device_bound'])
                                <span class="text-xs">Terikat</span>
                                @if($canManage)
                                    <x-mary-button icon="o-arrow-path" class="btn-ghost btn-xs" tooltip="Reset perangkat" wire:click="resetDevice({{ $r['user_id'] }})" wire:confirm="Reset perangkat user ini?" />
                                @endif
                            @else
                                <span class="text-xs opacity-60">-</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <p class="text-xs opacity-60 mt-3">Hari kerja dihitung dari awal bulan sampai hari ini sesuai hari kerja shift user. Hari libur tenant dan cuti/izin (menu Hari Libur & Cuti) tidak dihitung sebagai hari kerja/tidak hadir.</p>
    </x-mary-card>
</div>
