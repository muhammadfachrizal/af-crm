<?php

namespace VentureDrake\LaravelCrm\Livewire\WorkLocations;

use Livewire\Component;
use Livewire\WithPagination;
use Mary\Traits\Toast;
use VentureDrake\LaravelCrm\Models\Tenant;
use VentureDrake\LaravelCrm\Models\WorkLocation;

class WorkLocationIndex extends Component
{
    use Toast, WithPagination;

    public bool $enforce = false;

    public function mount(): void
    {
        abort_unless(auth()->user()->hasPermissionTo('view crm attendances'), 403);

        $this->enforce = (bool) optional($this->tenant())->attendance_enforce_radius;
    }

    protected function tenant(): ?Tenant
    {
        return auth()->user()->tenant_id ? Tenant::find(auth()->user()->tenant_id) : null;
    }

    public function canManage(): bool
    {
        return auth()->user()->hasPermissionTo('edit crm attendances');
    }

    public function updatedEnforce($value): void
    {
        abort_unless($this->canManage(), 403);

        if ($t = $this->tenant()) {
            $t->update(['attendance_enforce_radius' => (bool) $value]);
            $this->success($value ? 'Absensi sekarang wajib dalam radius.' : 'Radius hanya dicatat (tidak memblokir).');
        }
    }

    public function toggleActive(int $id): void
    {
        abort_unless($this->canManage(), 403);

        $l = WorkLocation::findOrFail($id);
        $l->update(['is_active' => ! $l->is_active]);
    }

    public function delete(int $id): void
    {
        abort_unless($this->canManage(), 403);

        WorkLocation::findOrFail($id)->delete();
        $this->success('Lokasi kerja dihapus.');
    }

    public function render()
    {
        return view('laravel-crm::livewire.work-locations.work-location-index', [
            'locations' => WorkLocation::orderBy('name')->paginate(15),
            'headers' => [
                ['key' => 'name', 'label' => 'Nama'],
                ['key' => 'code', 'label' => 'Kode'],
                ['key' => 'address', 'label' => 'Alamat', 'sortable' => false],
                ['key' => 'radius_meters', 'label' => 'Radius (m)', 'sortable' => false],
                ['key' => 'is_active', 'label' => 'Status', 'sortable' => false],
                ['key' => 'actions', 'label' => '', 'sortable' => false],
            ],
            'canManage' => $this->canManage(),
            'hasTenant' => (bool) auth()->user()->tenant_id,
        ]);
    }
}
