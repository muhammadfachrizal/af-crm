<?php

namespace VentureDrake\LaravelCrm\Livewire\Attendances;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Mary\Traits\Toast;
use VentureDrake\LaravelCrm\Models\Attendance;
use VentureDrake\LaravelCrm\Traits\ClearsProperties;
use VentureDrake\LaravelCrm\Traits\ResetsPaginationWhenPropsChanges;

class AttendanceIndex extends Component
{
    use ClearsProperties, ResetsPaginationWhenPropsChanges, Toast, WithPagination;

    public $layout = 'index';

    #[Url]
    public ?array $user_id = [];

    #[Url]
    public ?string $type = null;

    #[Url]
    public ?string $date = null;

    #[Url]
    public array $sortBy = ['column' => 'recorded_at', 'direction' => 'desc'];

    public bool $showFilters = false;

    public function filterCount(): int
    {
        return (count($this->user_id ?? []) > 0 ? 1 : 0)
            + ($this->type ? 1 : 0)
            + ($this->date ? 1 : 0);
    }

    public function users(): Collection
    {
        $userModel = config('auth.providers.users.model');

        return $userModel::orderBy('name')->get();
    }

    public function headers(): array
    {
        return [
            ['key' => 'recorded_at', 'label' => ucfirst(__('laravel-crm::lang.date')), 'format' => fn ($row, $field) => $field->format('d M Y H:i')],
            ['key' => 'type', 'label' => ucfirst(__('laravel-crm::lang.type'))],
            ['key' => 'user.name', 'label' => ucfirst(__('laravel-crm::lang.user')), 'sortable' => false],
            ['key' => 'address', 'label' => ucfirst(__('laravel-crm::lang.location')), 'sortable' => false],
            ['key' => 'photo_path', 'label' => ucfirst(__('laravel-crm::lang.photo')), 'sortable' => false, 'format' => fn ($row, $field) => $field ? 'yes' : '-'],
        ];
    }

    public function attendances(): LengthAwarePaginator
    {
        $query = Attendance::query()->with(['user']);

        if (! auth()->user()->hasPermissionTo('manage crm attendances')) {
            $query->where('user_id', auth()->id());
        } elseif (! empty($this->user_id)) {
            $query->whereIn('user_id', $this->user_id);
        }

        if ($this->type) {
            $query->where('type', $this->type);
        }

        if ($this->date) {
            $query->whereDate('recorded_at', $this->date);
        }

        $query->orderBy($this->sortBy['column'], $this->sortBy['direction']);

        return $query->paginate(20);
    }

    public function render(): \Illuminate\View\View
    {
        return view('laravel-crm::livewire.attendances.attendance-index', [
            'attendances' => $this->attendances(),
            'headers' => $this->headers(),
            'users' => $this->users(),
            'filterCount' => $this->filterCount(),
        ]);
    }
}
