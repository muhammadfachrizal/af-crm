<?php

namespace VentureDrake\LaravelCrm\Livewire\WorkLocations;

use Livewire\Component;
use Mary\Traits\Toast;
use VentureDrake\LaravelCrm\Models\WorkLocation;

class WorkLocationForm extends Component
{
    use Toast;

    public ?int $locationId = null;

    public string $name = '';

    public ?string $code = null;

    public ?string $address = null;

    public $latitude = null;

    public $longitude = null;

    public $radius_meters = 100;

    public bool $is_active = true;

    public function mount(?int $id = null): void
    {
        abort_unless(auth()->user()->hasPermissionTo('edit crm attendances'), 403);

        if ($id) {
            $l = WorkLocation::findOrFail($id); // terfilter tenant -> lokasi tenant lain = 404
            $this->locationId = $l->id;
            $this->name = $l->name;
            $this->code = $l->code;
            $this->address = $l->address;
            $this->latitude = $l->latitude;
            $this->longitude = $l->longitude;
            $this->radius_meters = $l->radius_meters;
            $this->is_active = $l->is_active;
        }
    }

    public function save()
    {
        $data = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'radius_meters' => ['required', 'integer', 'min:10', 'max:100000'],
            'is_active' => ['boolean'],
        ], [
            'latitude.required' => 'Klik peta untuk memilih titik lokasi.',
        ]);

        if ($this->locationId) {
            WorkLocation::findOrFail($this->locationId)->update($data);
        } else {
            WorkLocation::create($data);
        }

        session()->flash('success', 'Lokasi kerja disimpan.');

        return redirect()->route('laravel-crm.work-locations.index');
    }

    public function render()
    {
        return view('laravel-crm::livewire.work-locations.work-location-form');
    }
}
