<div class="crm-content">
    <x-mary-header title="Shift Kerja" subtitle="Jam kerja, toleransi telat, dan hari kerja" progress-indicator>
        <x-slot:actions>
            @if($canManage)
                <x-mary-button label="Tambah Shift" icon="o-plus" class="btn-primary btn-sm text-white" wire:click="openCreate" responsive />
            @endif
        </x-slot:actions>
    </x-mary-header>

    <x-mary-card shadow class="mb-4">
        <div class="overflow-x-auto">
            <table class="table whitespace-nowrap">
                <thead><tr><th>Nama</th><th>Jam</th><th>Hari kerja</th><th>Toleransi telat</th><th>Toleransi pulang cepat</th><th>Lembur mulai</th><th>Status</th><th></th></tr></thead>
                <tbody>
                @forelse($shifts as $s)
                    <tr>
                        <td class="font-semibold">{{ $s->name }} @if($s->is_default)<x-mary-badge value="Default" class="badge-primary text-white badge-sm" />@endif</td>
                        <td>{{ $s->start_time }} - {{ $s->end_time }}</td>
                        <td>{{ collect($s->working_days ?: [])->map(fn ($d) => $days[(int) $d] ?? $d)->implode(', ') }}</td>
                        <td>{{ $s->late_tolerance_minutes }} mnt</td>
                        <td>{{ $s->early_leave_tolerance_minutes }} mnt</td>
                        <td>&ge; {{ $s->overtime_min_minutes }} mnt</td>
                        <td><x-mary-badge :value="$s->is_active ? 'Aktif' : 'Nonaktif'" :class="$s->is_active ? 'badge-success text-white' : 'badge-ghost'" /></td>
                        <td class="text-right">
                            @if($canManage)
                                <x-mary-button icon="o-pencil" class="btn-ghost btn-sm" wire:click="openEdit({{ $s->id }})" />
                                <x-mary-button icon="o-trash" class="btn-ghost btn-sm text-error" wire:click="delete({{ $s->id }})" wire:confirm="Hapus shift ini? User yang memakainya kembali ke shift default." />
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="opacity-60">Belum ada shift. Tanpa shift, absensi tidak menghitung telat/lembur.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </x-mary-card>

    <x-mary-card title="Penugasan Shift per User" subtitle="Kosong = memakai shift default" shadow>
        <div class="overflow-x-auto">
            <table class="table table-zebra">
                <thead><tr><th>User</th><th>Shift</th></tr></thead>
                <tbody>
                @foreach($users as $u)
                    <tr>
                        <td>{{ $u->name }}</td>
                        <td>
                            <select class="select select-bordered select-sm" @disabled(! $canManage)
                                    wire:change="assign({{ $u->id }}, $event.target.value)">
                                <option value="">(default)</option>
                                @foreach($shifts as $s)
                                    <option value="{{ $s->id }}" @selected($u->shift_id == $s->id)>{{ $s->name }} ({{ $s->start_time }}-{{ $s->end_time }})</option>
                                @endforeach
                            </select>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </x-mary-card>

    <x-mary-drawer wire:model="showForm" :title="$editingId ? 'Ubah Shift' : 'Tambah Shift'" right separator with-close-button class="w-11/12 lg:w-1/3">
        <x-mary-form wire:submit="save">
            <x-mary-input label="Nama shift" wire:model="name" placeholder="mis. Shift Pagi" />
            <div class="grid grid-cols-2 gap-3">
                <x-mary-input type="time" label="Jam masuk" wire:model="start_time" />
                <x-mary-input type="time" label="Jam pulang" wire:model="end_time" hint="Lebih kecil dari jam masuk = melewati tengah malam" />
            </div>
            <div>
                <label class="label"><span class="label-text font-semibold">Hari kerja</span></label>
                <div class="flex flex-wrap gap-3">
                    @foreach($days as $num => $label)
                        <label class="flex items-center gap-1"><input type="checkbox" class="checkbox checkbox-sm" value="{{ $num }}" wire:model="working_days"> {{ $label }}</label>
                    @endforeach
                </div>
            </div>
            <div class="grid grid-cols-3 gap-3">
                <x-mary-input type="number" label="Toleransi telat (mnt)" wire:model="late_tolerance_minutes" />
                <x-mary-input type="number" label="Toleransi pulang cepat (mnt)" wire:model="early_leave_tolerance_minutes" />
                <x-mary-input type="number" label="Lembur mulai (mnt)" wire:model="overtime_min_minutes" />
            </div>
            <x-mary-toggle label="Shift default tenant" wire:model="is_default" />
            <x-mary-toggle label="Aktif" wire:model="is_active" />
            <x-slot:actions>
                <x-mary-button label="Batal" @click="$wire.showForm = false" />
                <x-mary-button label="Simpan" class="btn-primary text-white" type="submit" spinner="save" />
            </x-slot:actions>
        </x-mary-form>
    </x-mary-drawer>
</div>
