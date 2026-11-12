<div class="crm-content">
    <x-mary-header title="Kirim Notifikasi" subtitle="Push notification ke aplikasi mobile" progress-indicator />

    <x-mary-card title="Status Push (FCM)" shadow class="mb-4">
        @if($diag['ok'])
            <x-mary-alert icon="o-check-circle" class="alert-success mb-3">{{ $diag['message'] }}</x-mary-alert>
        @else
            <x-mary-alert icon="o-exclamation-triangle" class="alert-warning mb-3">{{ $diag['message'] }}</x-mary-alert>
        @endif
        <div class="text-sm mb-3">Perangkat terdaftar: <strong>{{ $allDevices }}</strong> di tenant ini &middot; milik Anda: <strong>{{ $myDevices }}</strong></div>
        <x-mary-button label="Kirim tes ke perangkat saya" icon="o-paper-airplane" class="btn-sm" wire:click="testMe" spinner="testMe" />
    </x-mary-card>

    <x-mary-card shadow>
        <x-mary-form wire:submit="send">
            <x-mary-input label="Judul" wire:model="title" />
            <x-mary-textarea label="Pesan" wire:model="body" rows="3" />
            <x-mary-toggle label="Kirim ke semua user di tenant ini" wire:model.live="all" />
            @unless($all)
                <div class="max-h-64 overflow-y-auto border rounded p-3">
                    @foreach($users as $u)
                        <label class="flex items-center gap-2 py-1">
                            <input type="checkbox" class="checkbox checkbox-sm" value="{{ $u->id }}" wire:model="userIds">
                            {{ $u->name }} <span class="text-xs opacity-60">({{ $counts[$u->id] ?? 0 }} perangkat)</span>
                        </label>
                    @endforeach
                </div>
            @endunless
            <x-slot:actions>
                <x-mary-button label="Kirim" icon="o-paper-airplane" class="btn-primary text-white" type="submit" spinner="send" />
            </x-slot:actions>
        </x-mary-form>
    </x-mary-card>
</div>
