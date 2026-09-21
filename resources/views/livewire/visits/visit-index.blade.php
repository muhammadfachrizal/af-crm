<div class="crm-content">
    <x-mary-header title="{{ ucfirst(__('laravel-crm::lang.visits')) }}" progress-indicator>
        <x-slot:actions>
            <x-mary-button label="{{ ucfirst(__('laravel-crm::lang.add')) }}" icon="o-plus" link="{{ url(route('laravel-crm.visits.create')) }}" class="btn-primary text-white" responsive />
            <x-mary-button label="Filters"
                           icon="o-funnel"
                           :badge="$filterCount ?? 0"
                           badge-classes="font-mono badge-primary badge-soft"
                           @click="$wire.showFilters = true"
                           responsive />
        </x-slot:actions>
    </x-mary-header>

    <x-mary-card shadow>
        <x-mary-table :headers="$headers" :rows="$visits" with-pagination :sort-by="$sortBy" class="whitespace-nowrap">
            @scope('cell_status', $visit)
                @if($visit->status === 'completed')
                    <x-mary-badge value="{{ ucfirst(__('laravel-crm::lang.completed')) }}" class="badge-success text-white" />
                @elseif($visit->status === 'cancelled')
                    <x-mary-badge value="{{ ucfirst(__('laravel-crm::lang.cancelled')) }}" class="badge-error text-white" />
                @else
                    <x-mary-badge value="{{ ucfirst(__('laravel-crm::lang.planned')) }}" class="badge-warning text-white" />
                @endif
            @endscope
        </x-mary-table>
    </x-mary-card>

    <x-mary-drawer wire:model="showFilters" title="{{ ucfirst(__('laravel-crm::lang.filters')) }}" right separator with-close-button class="w-11/12 lg:w-1/3">
        @can('edit crm visits')
            <x-mary-choices label="{{ ucfirst(__('laravel-crm::lang.user')) }}" wire:model.live="user_id" :options="$users" option-label="name" option-value="id" searchable />
        @endcan

        <x-mary-select label="{{ ucfirst(__('laravel-crm::lang.status')) }}" wire:model.live="status" :options="[
            ['id' => 'planned', 'name' => ucfirst(__('laravel-crm::lang.planned'))],
            ['id' => 'completed', 'name' => ucfirst(__('laravel-crm::lang.completed'))],
            ['id' => 'cancelled', 'name' => ucfirst(__('laravel-crm::lang.cancelled'))],
        ]" placeholder="{{ ucfirst(__('laravel-crm::lang.all')) }}" />

        <x-slot:actions>
            <x-mary-button label="{{ ucfirst(__('laravel-crm::lang.clear')) }}" wire:click="clearProperties" />
        </x-slot:actions>
    </x-mary-drawer>
</div>
