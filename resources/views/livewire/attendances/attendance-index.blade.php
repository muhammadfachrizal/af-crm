<div class="crm-content">
    {{-- HEADER --}}
    <x-mary-header title="{{ ucfirst(__('laravel-crm::lang.attendances')) }}" progress-indicator>
        <x-slot:actions>
            <x-mary-button label="Filters"
                           icon="o-funnel"
                           :badge="$filterCount ?? 0"
                           badge-classes="font-mono badge-primary badge-soft"
                           @click="$wire.showFilters = true"
                           responsive />
        </x-slot:actions>
    </x-mary-header>

    {{-- TABLE --}}
    <x-mary-card shadow>
        <x-mary-table :headers="$headers" :rows="$attendances" with-pagination :sort-by="$sortBy" class="whitespace-nowrap">
            @scope('cell_type', $attendance)
                @if($attendance->type === 'in')
                    <x-mary-badge value="{{ ucfirst(__('laravel-crm::lang.check_in')) }}" class="badge-success text-white" />
                @else
                    <x-mary-badge value="{{ ucfirst(__('laravel-crm::lang.check_out')) }}" class="badge-warning text-white" />
                @endif
            @endscope

            @scope('cell_photo_path', $attendance)
                @if($attendance->photo_path)
                    <a href="{{ asset('storage/'.$attendance->photo_path) }}" target="_blank">
                        <img src="{{ asset('storage/'.$attendance->photo_path) }}" class="w-10 h-10 rounded-full object-cover" />
                    </a>
                @else
                    -
                @endif
            @endscope
        </x-mary-table>
    </x-mary-card>

    {{-- FILTERS DRAWER --}}
    <x-mary-drawer wire:model="showFilters" title="{{ ucfirst(__('laravel-crm::lang.filters')) }}" right separator with-close-button class="w-11/12 lg:w-1/3">
        @can('manage crm attendances')
            <x-mary-choices label="{{ ucfirst(__('laravel-crm::lang.user')) }}" wire:model.live="user_id" :options="$users" option-label="name" option-value="id" searchable />
        @endcan

        <x-mary-select label="{{ ucfirst(__('laravel-crm::lang.type')) }}" wire:model.live="type" :options="[
            ['id' => 'in', 'name' => ucfirst(__('laravel-crm::lang.check_in'))],
            ['id' => 'out', 'name' => ucfirst(__('laravel-crm::lang.check_out'))],
        ]" placeholder="{{ ucfirst(__('laravel-crm::lang.all')) }}" />

        <x-mary-input type="date" label="{{ ucfirst(__('laravel-crm::lang.date')) }}" wire:model.live="date" />

        <x-slot:actions>
            <x-mary-button label="{{ ucfirst(__('laravel-crm::lang.clear')) }}" wire:click="clearProperties" />
        </x-slot:actions>
    </x-mary-drawer>
</div>
