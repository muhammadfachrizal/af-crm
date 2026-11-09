<div class="crm-content">
    <x-mary-header title="Hari Libur & Cuti" subtitle="Tidak dihitung sebagai hari kerja di rekap absensi dan tidak memicu alarm/pengingat" progress-indicator>
        <x-slot:actions>
            <x-mary-select wire:model.live="year" :options="collect(range(now()->year - 1, now()->year + 2))->map(fn ($y) => ['id' => $y, 'name' => $y])->all()" class="select-sm" />
        </x-slot:actions>
    </x-mary-header>

    <div class="grid lg:grid-cols-2 gap-4">
        <x-mary-card title="Hari Libur {{ $year }}" shadow>
            @if($canManage)
                <div class="grid grid-cols-3 gap-2 mb-2">
                    <x-mary-input type="date" wire:model="h_date" />
                    <x-mary-input placeholder="Nama libur" wire:model="h_name" class="col-span-2" />
                </div>
                <div class="flex items-center justify-between mb-4">
                    <x-mary-toggle label="Cuti bersama" wire:model="h_joint" />
                    <x-mary-button label="Tambah" icon="o-plus" class="btn-primary btn-sm text-white" wire:click="addHoliday" />
                </div>
                <x-mary-textarea label="Impor banyak (satu baris satu hari)" wire:model="bulk" rows="3" placeholder="2027-01-01 Tahun Baru Masehi&#10;2027-03-12 Cuti Bersama Nyepi | cuti" hint="Format: YYYY-MM-DD Nama [| cuti]. Sesuaikan dengan SKB 3 Menteri tahun berjalan." />
                <x-mary-button label="Impor" class="btn-sm mt-2" wire:click="importBulk" />
                <hr class="my-4">
            @endif
            <table class="table table-sm">
                <tbody>
                @forelse($holidays as $h)
                    <tr>
                        <td class="whitespace-nowrap">{{ $h->date->translatedFormat('D, d M Y') }}</td>
                        <td>{{ $h->name }} @if($h->is_joint_leave)<x-mary-badge value="Cuti bersama" class="badge-warning badge-sm" />@endif</td>
                        <td class="text-right">@if($canManage)<x-mary-button icon="o-trash" class="btn-ghost btn-xs text-error" wire:click="deleteHoliday({{ $h->id }})" wire:confirm="Hapus hari libur ini?" />@endif</td>
                    </tr>
                @empty
                    <tr><td class="opacity-60">Belum ada hari libur untuk {{ $year }}.</td></tr>
                @endforelse
                </tbody>
            </table>
        </x-mary-card>

        <x-mary-card title="Cuti / Izin / Sakit / Dinas" subtitle="Opsional, diinput admin. Pengajuan karyawan dilakukan di aplikasi HRIS, bukan di CRM." shadow>
            @if($canManage)
                <div class="grid grid-cols-2 gap-2 mb-2">
                    <x-mary-select placeholder="Pilih user" wire:model="l_user" :options="$users" option-label="name" option-value="id" />
                    <x-mary-select wire:model="l_type" :options="collect($types)->map(fn ($n, $k) => ['id' => $k, 'name' => $n])->values()->all()" />
                    <x-mary-input type="date" label="Mulai" wire:model="l_start" />
                    <x-mary-input type="date" label="Sampai" wire:model="l_end" />
                </div>
                <x-mary-input placeholder="Catatan (opsional)" wire:model="l_notes" />
                <x-mary-button label="Tambah" icon="o-plus" class="btn-primary btn-sm text-white mt-2" wire:click="addLeave" />
                <hr class="my-4">
            @endif
            <table class="table table-sm">
                <tbody>
                @forelse($leaves as $l)
                    <tr>
                        <td class="font-semibold">{{ $l->user->name ?? '-' }}</td>
                        <td>{{ $types[$l->type] ?? $l->type }}</td>
                        <td class="whitespace-nowrap">{{ $l->start_date->format('d M') }} - {{ $l->end_date->format('d M Y') }}</td>
                        <td class="text-right">@if($canManage)<x-mary-button icon="o-trash" class="btn-ghost btn-xs text-error" wire:click="deleteLeave({{ $l->id }})" wire:confirm="Hapus?" />@endif</td>
                    </tr>
                @empty
                    <tr><td class="opacity-60">Belum ada data.</td></tr>
                @endforelse
                </tbody>
            </table>
        </x-mary-card>
    </div>
</div>
