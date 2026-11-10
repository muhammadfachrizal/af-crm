<div class="crm-content">
    <x-mary-header title="Notifikasi" subtitle="Task, visit, pengingat absen, dan pengumuman" progress-indicator>
        <x-slot:actions>
            <x-mary-select wire:model.live="filter" :options="[['id' => 'all', 'name' => 'Semua'], ['id' => 'unread', 'name' => 'Belum dibaca']]" class="select-sm" />
            <x-mary-button label="Tandai semua dibaca" icon="o-check" class="btn-sm" wire:click="markAll" responsive />
        </x-slot:actions>
    </x-mary-header>

    <x-mary-card shadow>
        @forelse($items as $n)
            <div class="flex items-start gap-3 py-3 {{ ! $loop->last ? 'border-b' : '' }} {{ $n->read_at ? 'opacity-60' : '' }}">
                <div class="mt-1">
                    @php
                        $icon = ['task' => 'o-clipboard-document-check', 'visit' => 'o-map-pin', 'attendance' => 'o-finger-print', 'broadcast' => 'o-megaphone'][$n->type] ?? 'o-bell';
                    @endphp
                    <x-mary-icon :name="$icon" class="w-6 h-6 {{ $n->read_at ? '' : 'text-primary' }}" />
                </div>
                <div class="flex-1">
                    <div class="font-semibold">{{ $n->title }} @unless($n->read_at)<x-mary-badge value="Baru" class="badge-primary badge-sm text-white" />@endunless</div>
                    @if($n->body)<div class="text-sm opacity-80">{{ $n->body }}</div>@endif
                    <div class="text-xs opacity-50 mt-1">{{ $n->created_at->translatedFormat('d M Y H:i') }} &middot; {{ $n->created_at->diffForHumans() }}</div>
                </div>
                <div class="flex gap-1">
                    @unless($n->read_at)
                        <x-mary-button icon="o-check" class="btn-ghost btn-xs" tooltip="Tandai dibaca" wire:click="markRead({{ $n->id }})" />
                    @endunless
                    <x-mary-button icon="o-trash" class="btn-ghost btn-xs text-error" wire:click="delete({{ $n->id }})" />
                </div>
            </div>
        @empty
            <div class="py-10 text-center opacity-60">Belum ada notifikasi.</div>
        @endforelse

        <div class="mt-4">{{ $items->links() }}</div>
    </x-mary-card>
</div>
