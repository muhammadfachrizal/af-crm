<?php

namespace VentureDrake\LaravelCrm\Livewire\Shifts;

use Livewire\Component;
use Mary\Traits\Toast;
use VentureDrake\LaravelCrm\Models\Shift;

class ShiftIndex extends Component
{
    use Toast;

    public bool $showForm = false;

    public ?int $editingId = null;

    public string $name = '';

    public string $start_time = '08:00';

    public string $end_time = '17:00';

    public array $working_days = [1, 2, 3, 4, 5];

    public $late_tolerance_minutes = 0;

    public $early_leave_tolerance_minutes = 0;

    public $overtime_min_minutes = 30;

    public bool $is_default = false;

    public bool $is_active = true;

    public function mount(): void
    {
        abort_unless(auth()->user()->hasPermissionTo('view crm attendances'), 403);
    }

    protected function canManage(): bool
    {
        return auth()->user()->hasPermissionTo('edit crm attendances');
    }

    public function openCreate(): void
    {
        abort_unless($this->canManage(), 403);
        $this->reset(['editingId', 'name']);
        $this->start_time = '08:00';
        $this->end_time = '17:00';
        $this->working_days = [1, 2, 3, 4, 5];
        $this->late_tolerance_minutes = 0;
        $this->early_leave_tolerance_minutes = 0;
        $this->overtime_min_minutes = 30;
        $this->is_default = ! Shift::where('is_default', true)->exists();
        $this->is_active = true;
        $this->resetValidation();
        $this->showForm = true;
    }

    public function openEdit(int $id): void
    {
        abort_unless($this->canManage(), 403);
        $s = Shift::findOrFail($id);
        $this->editingId = $s->id;
        $this->name = $s->name;
        $this->start_time = $s->start_time;
        $this->end_time = $s->end_time;
        $this->working_days = array_map('intval', $s->working_days ?: [1, 2, 3, 4, 5]);
        $this->late_tolerance_minutes = $s->late_tolerance_minutes;
        $this->early_leave_tolerance_minutes = $s->early_leave_tolerance_minutes;
        $this->overtime_min_minutes = $s->overtime_min_minutes;
        $this->is_default = $s->is_default;
        $this->is_active = $s->is_active;
        $this->resetValidation();
        $this->showForm = true;
    }

    public function save(): void
    {
        abort_unless($this->canManage(), 403);

        $data = $this->validate([
            'name' => ['required', 'string', 'max:100'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i'],
            'working_days' => ['required', 'array', 'min:1'],
            'late_tolerance_minutes' => ['required', 'integer', 'min:0', 'max:240'],
            'early_leave_tolerance_minutes' => ['required', 'integer', 'min:0', 'max:240'],
            'overtime_min_minutes' => ['required', 'integer', 'min:1', 'max:480'],
            'is_default' => ['boolean'],
            'is_active' => ['boolean'],
        ]);

        $data['working_days'] = array_values(array_map('intval', $data['working_days']));

        if ($this->editingId) {
            Shift::findOrFail($this->editingId)->update($data);
        } else {
            Shift::create($data);
        }

        $this->showForm = false;
        $this->success('Shift disimpan.');
    }

    public function delete(int $id): void
    {
        abort_unless($this->canManage(), 403);

        $userModel = config('auth.providers.users.model');
        $userModel::where('shift_id', $id)->update(['shift_id' => null]);
        Shift::findOrFail($id)->delete();
        $this->success('Shift dihapus.');
    }

    public function assign(int $userId, $shiftId): void
    {
        abort_unless($this->canManage(), 403);

        $userModel = config('auth.providers.users.model');
        $user = $userModel::findOrFail($userId); // terfilter tenant
        $user->forceFill(['shift_id' => $shiftId ?: null])->save();
        $this->success('Penugasan shift disimpan.');
    }

    public function render()
    {
        $userModel = config('auth.providers.users.model');

        return view('laravel-crm::livewire.shifts.shift-index', [
            'shifts' => Shift::orderBy('name')->get(),
            'users' => $userModel::orderBy('name')->get(['id', 'name', 'shift_id']),
            'canManage' => $this->canManage(),
            'days' => [1 => 'Sen', 2 => 'Sel', 3 => 'Rab', 4 => 'Kam', 5 => 'Jum', 6 => 'Sab', 7 => 'Min'],
        ]);
    }
}
