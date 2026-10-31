<div class="crm-content">
    <x-mary-header title="Dashboard Super Admin" subtitle="Ringkasan semua tenant" progress-indicator>
        <x-slot:actions>
            <x-mary-button label="Kelola Tenant" icon="o-building-office" class="btn-primary btn-sm text-white" link="{{ url(route('laravel-crm.tenants.index')) }}" responsive />
        </x-slot:actions>
    </x-mary-header>

    <div class="grid lg:grid-cols-4 gap-4 mb-4">
        <x-mary-stat title="Total Tenant" :value="$total" icon="o-building-office" />
        <x-mary-stat title="Tenant Aktif" :value="$active" icon="o-check-circle" color="text-success" />
        <x-mary-stat title="Nonaktif / Kedaluwarsa" :value="$suspended.' / '.$expired" icon="o-no-symbol" color="text-error" />
        <x-mary-stat title="Hampir Kedaluwarsa (30 hari)" :value="$expiringSoon->count()" icon="o-clock" color="text-warning" />
    </div>

    <div class="grid lg:grid-cols-4 gap-4 mb-6">
        <x-mary-stat title="Total User" :value="$totalUsers" icon="o-users" />
        <x-mary-stat title="Total Lead" :value="$totalLeads" icon="o-light-bulb" />
        <x-mary-stat title="Total Deal" :value="$totalDeals" icon="o-currency-dollar" />
        <x-mary-stat title="Absensi Hari Ini" :value="$attendanceToday" description="user unik" icon="o-finger-print" />
    </div>

    @if($expiringSoon->isNotEmpty())
        <x-mary-alert icon="o-exclamation-triangle" class="alert-warning mb-4">
            Langganan hampir habis:
            @foreach($expiringSoon as $r)
                <strong>{{ $r['tenant']->name }}</strong> ({{ $r['days_left'] }} hari)@if(! $loop->last), @endif
            @endforeach
        </x-mary-alert>
    @endif

    <x-mary-card title="Per Tenant" shadow>
        <div class="overflow-x-auto">
            <table class="table table-zebra whitespace-nowrap">
                <thead>
                    <tr>
                        <th>Tenant</th><th>Status</th><th>Kedaluwarsa</th>
                        <th class="text-right">User</th><th class="text-right">Lead</th><th class="text-right">Deal</th>
                        <th class="text-right">Kontak</th><th class="text-right">Visit</th><th class="text-right">Absen hari ini</th>
                    </tr>
                </thead>
                <tbody>
                @foreach($rows as $r)
                    <tr>
                        <td class="font-semibold">{{ $r['tenant']->name }} <span class="text-xs opacity-60">{{ $r['tenant']->code }}</span></td>
                        <td>
                            @if($r['usable']) <x-mary-badge value="Aktif" class="badge-success text-white" />
                            @elseif($r['tenant']->status === 'active') <x-mary-badge value="Kedaluwarsa" class="badge-warning text-white" />
                            @else <x-mary-badge value="Nonaktif" class="badge-error text-white" /> @endif
                        </td>
                        <td>{{ $r['tenant']->expires_at ? $r['tenant']->expires_at->format('d M Y') : '-' }}</td>
                        <td class="text-right">{{ $r['users'] }}@if($r['tenant']->max_users) / {{ $r['tenant']->max_users }}@endif</td>
                        <td class="text-right">{{ $r['leads'] }}</td>
                        <td class="text-right">{{ $r['deals'] }}</td>
                        <td class="text-right">{{ $r['people'] }}</td>
                        <td class="text-right">{{ $r['visits'] }}</td>
                        <td class="text-right">{{ $r['attendance_today'] }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </x-mary-card>
</div>
