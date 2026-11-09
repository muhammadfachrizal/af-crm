<div class="crm-content">
    <x-mary-header title="Tenant" subtitle="Perusahaan pelanggan (multi tenant)" progress-indicator>
        <x-slot:middle class="!justify-end">
            <x-mary-input placeholder="Cari nama / kode..." wire:model.live.debounce="search" icon="o-magnifying-glass" clearable />
        </x-slot:middle>
        <x-slot:actions>
            <x-mary-button label="Dashboard" icon="o-chart-bar" link="{{ url(route('laravel-crm.tenants.dashboard')) }}" class="btn-sm" responsive />
            <x-mary-button label="Tambah Tenant" icon="o-plus" class="btn-primary btn-sm text-white" wire:click="openCreate" responsive />
        </x-slot:actions>
    </x-mary-header>

    @if($createdInfo)
        <x-mary-alert icon="o-check-circle" class="alert-success mb-4" dismissible>
            {{ $createdInfo }} <span class="font-semibold">(catat password ini, tidak ditampilkan lagi)</span>
        </x-mary-alert>
    @endif

    <x-mary-card shadow>
        <x-mary-table :headers="$headers" :rows="$tenants" with-pagination class="whitespace-nowrap">
            @scope('cell_status_label', $tenant)
                @if($tenant->status_label === 'Aktif')
                    <x-mary-badge value="Aktif" class="badge-success text-white" />
                @elseif($tenant->status_label === 'Kedaluwarsa')
                    <x-mary-badge value="Kedaluwarsa" class="badge-warning text-white" />
                @else
                    <x-mary-badge value="Nonaktif" class="badge-error text-white" />
                @endif
            @endscope

            @scope('cell_expires_at', $tenant)
                {{ $tenant->expires_at ? $tenant->expires_at->format('d M Y') : '-' }}
            @endscope

            @scope('cell_actions', $tenant)
                <div class="flex gap-1 justify-end">
                    <x-mary-button icon="o-arrow-right-end-on-rectangle" class="btn-ghost btn-sm" tooltip="Bekerja sebagai tenant ini" wire:click="enter({{ $tenant->id }})" />
                    <x-mary-button icon="o-pencil" class="btn-ghost btn-sm" tooltip="Ubah" wire:click="openEdit({{ $tenant->id }})" />
                    <x-mary-button :icon="$tenant->status === 'active' ? 'o-no-symbol' : 'o-check-circle'" class="btn-ghost btn-sm"
                                   :tooltip="$tenant->status === 'active' ? 'Nonaktifkan' : 'Aktifkan'"
                                   wire:click="toggleStatus({{ $tenant->id }})"
                                   wire:confirm="Ubah status tenant ini?" />
                </div>
            @endscope
        </x-mary-table>
    </x-mary-card>

    <x-mary-drawer wire:model="showForm" :title="$editingId ? 'Ubah Tenant' : 'Tambah Tenant'" right separator with-close-button class="w-11/12 lg:w-1/3">
        <x-mary-form wire:submit="save">
            <x-mary-input label="Nama perusahaan" wire:model="name" />
            <x-mary-input label="Kode" wire:model="code" hint="Kosongkan = dibuat otomatis dari nama" />
            <div class="grid grid-cols-2 gap-3">
                <x-mary-input label="Email" wire:model="email" />
                <x-mary-input label="Telepon" wire:model="phone" />
            </div>
            <x-mary-textarea label="Alamat" wire:model="address" rows="2" />
            <x-mary-select label="Status" wire:model="status" :options="[['id' => 'active', 'name' => 'Aktif'], ['id' => 'suspended', 'name' => 'Nonaktif']]" />
            <div class="grid grid-cols-2 gap-3">
                <x-mary-input type="date" label="Mulai" wire:model="starts_at" />
                <x-mary-input type="date" label="Kedaluwarsa" wire:model="expires_at" />
            </div>
            <x-mary-input type="number" label="Batas jumlah user" wire:model="max_users" hint="Kosongkan = tanpa batas" />
            <x-mary-select label="Zona waktu" wire:model="timezone" :options="[['id' => 'Asia/Jakarta', 'name' => 'WIB (Asia/Jakarta)'], ['id' => 'Asia/Makassar', 'name' => 'WITA (Asia/Makassar)'], ['id' => 'Asia/Jayapura', 'name' => 'WIT (Asia/Jayapura)']]" />
            <x-mary-toggle label="Absensi wajib dalam radius Lokasi Kerja" wire:model="attendance_enforce_radius" />
            <x-mary-toggle label="Blokir lokasi palsu (fake GPS)" wire:model="block_mock_location" />
            <x-mary-toggle label="Kunci akun ke satu perangkat" wire:model="bind_device" />

            @if(! $editingId)
                <hr class="my-2">
                <x-mary-select label="Salin pengaturan default dari" wire:model="template_id" :options="$templates" option-label="name" option-value="id" hint="Settings, pipeline, tipe alamat/kontak, industri, pajak" />
                <x-mary-toggle label="Buat akun admin awal" wire:model.live="create_admin" />
                @if($create_admin)
                    <x-mary-input label="Nama admin" wire:model="admin_name" />
                    <x-mary-input label="Email admin" wire:model="admin_email" />
                    <x-mary-input label="Password admin" wire:model="admin_password" />
                @endif
            @endif

            <x-slot:actions>
                <x-mary-button label="Batal" @click="$wire.showForm = false" />
                <x-mary-button label="Simpan" class="btn-primary text-white" type="submit" spinner="save" />
            </x-slot:actions>
        </x-mary-form>
    </x-mary-drawer>
</div>
