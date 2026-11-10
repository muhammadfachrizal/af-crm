<div class="crm-content">
    <x-mary-header title="Lokasi Kerja" subtitle="Titik & radius absensi untuk tenant ini" progress-indicator>
        <x-slot:actions>
            @if($canManage)
                <x-mary-button label="Tambah Lokasi" icon="o-plus" class="btn-primary btn-sm text-white" link="{{ url(route('laravel-crm.work-locations.create')) }}" responsive />
            @endif
        </x-slot:actions>
    </x-mary-header>

    @if($hasTenant && $canManage)
        <x-mary-card shadow class="mb-4">
            <x-mary-toggle label="Absensi wajib berada dalam radius lokasi kerja"
                           hint="Nyala: check-in/out di luar radius ditolak. Mati: jarak hanya dicatat."
                           wire:model.live="enforce" />
        </x-mary-card>
    @endif

    <x-mary-card shadow>
        <x-mary-table :headers="$headers" :rows="$locations" with-pagination class="whitespace-nowrap">
            @scope('cell_is_active', $l)
                <x-mary-badge :value="$l->is_active ? 'Aktif' : 'Nonaktif'" :class="$l->is_active ? 'badge-success text-white' : 'badge-ghost'" />
            @endscope
            @scope('cell_actions', $l)
                @if($canManage)
                    <div class="flex gap-1 justify-end">
                        <x-mary-button icon="o-pencil" class="btn-ghost btn-sm" link="{{ url(route('laravel-crm.work-locations.edit', $l->id)) }}" />
                        <x-mary-button icon="o-power" class="btn-ghost btn-sm" wire:click="toggleActive({{ $l->id }})" />
                        <x-mary-button icon="o-trash" class="btn-ghost btn-sm text-error" wire:click="delete({{ $l->id }})" wire:confirm="Hapus lokasi kerja ini?" />
                    </div>
                @endif
            @endscope
        </x-mary-table>
    </x-mary-card>
</div>
