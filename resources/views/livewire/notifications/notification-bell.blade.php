<div wire:poll.30s>
    <x-mary-dropdown right>
        <x-slot:trigger>
            <button class="btn btn-ghost btn-sm relative" aria-label="Notifikasi">
                <x-mary-icon name="o-bell" class="w-5 h-5" />
                @if($unread > 0)
                    <span class="badge badge-error badge-xs text-white absolute -top-1 -right-1">{{ $unread > 99 ? '99+' : $unread }}</span>
                @endif
            </button>
        </x-slot:trigger>

        <div class="w-80 max-w-[90vw]">
            <div class="flex items-center justify-between px-3 py-2 border-b">
                <span class="font-semibold">Notifikasi</span>
                @if($unread > 0)
                    <button class="text-xs link" wire:click="markAll">Tandai semua dibaca</button>
                @endif
            </div>

            @forelse($latest as $n)
                <a href="#" wire:click.prevent="open({{ $n->id }})" class="block px-3 py-2 hover:bg-base-200 {{ $n->read_at ? 'opacity-60' : '' }}">
                    <div class="text-sm font-medium">{{ $n->title }}</div>
                    @if($n->body)<div class="text-xs opacity-70 line-clamp-2">{{ $n->body }}</div>@endif
                    <div class="text-[11px] opacity-50 mt-0.5">{{ $n->created_at->diffForHumans() }}</div>
                </a>
            @empty
                <div class="px-3 py-6 text-sm text-center opacity-60">Belum ada notifikasi.</div>
            @endforelse

            <a href="{{ url(route('laravel-crm.notifications.index')) }}" class="block text-center text-sm py-2 border-t link">Lihat semua</a>
        </div>
    </x-mary-dropdown>
</div>
