<?php

namespace VentureDrake\LaravelCrm\Livewire\Visits;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Mary\Traits\Toast;
use VentureDrake\LaravelCrm\Models\Visit;
use VentureDrake\LaravelCrm\Traits\ClearsProperties;
use VentureDrake\LaravelCrm\Traits\ResetsPaginationWhenPropsChanges;

class VisitIndex extends Component
{
    use ClearsProperties, ResetsPaginationWhenPropsChanges, Toast, WithPagination;

    public $layout = 'index';

    #[Url]
    public ?array $user_id = [];

    #[Url]
    public ?string $status = null;

    #[Url]
    public array $sortBy = ['column' => 'created_at', 'direction' => 'desc'];

    public bool $showFilters = false;

    public function filterCount(): int
    {
        return (count($this->user_id ?? []) > 0 ? 1 : 0) + ($this->status ? 1 : 0);
    }

    public function users(): Collection
    {
        $userModel = config('auth.providers.users.model');

        return $userModel::orderBy('name')->get();
    }

    public function headers(): array
    {
        return [
            ['key' => 'created_at', 'label' => ucfirst(__('laravel-crm::lang.date')), 'format' => fn ($row, $field) => $field->format('d M Y H:i')],
            ['key' => 'title', 'label' => ucfirst(__('laravel-crm::lang.title'))],
            ['key' => 'user.name', 'label' => ucfirst(__('laravel-crm::lang.user')), 'sortable' => false],
            ['key' => 'status', 'label' => ucfirst(__('laravel-crm::lang.status'))],
            ['key' => 'target_address', 'label' => ucfirst(__('laravel-crm::lang.location')), 'sortable' => false],
            ['key' => 'distance_meters', 'label' => ucfirst(__('laravel-crm::lang.distance')), 'sortable' => false, 'format' => fn ($row, $field) => $field !== null ? round($field).' m' : '-'],
        ];
    }

    public function visits(): LengthAwarePaginator
    {
        $query = Visit::query()->with(['user', 'assignedBy']);

        if (! auth()->user()->hasPermissionTo('edit crm visits')) {
            $query->where('user_id', auth()->id());
        } elseif (! empty($this->user_id)) {
            $query->whereIn('user_id', $this->user_id);
        }

        if ($this->status) {
            $query->where('status', $this->status);
        }

        $query->orderBy($this->sortBy['column'], $this->sortBy['direction']);

        return $query->paginate(20);
    }

    public function render(): \Illuminate\View\View
    {
        return view('laravel-crm::livewire.visits.visit-index', [
            'visits' => $this->visits(),
            'headers' => $this->headers(),
            'users' => $this->users(),
            'filterCount' => $this->filterCount(),
        ]);
    }
}
