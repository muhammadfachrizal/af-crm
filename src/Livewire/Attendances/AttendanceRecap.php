<?php

namespace VentureDrake\LaravelCrm\Livewire\Attendances;

use Livewire\Component;
use Mary\Traits\Toast;
use VentureDrake\LaravelCrm\Support\AttendanceRecapService;

class AttendanceRecap extends Component
{
    use Toast;

    public string $month = '';

    public function mount(): void
    {
        abort_unless(auth()->user()->hasPermissionTo('view crm attendances'), 403);
        $this->month = now(AttendanceRecapService::timezone())->format('Y-m');
    }

    public function resetDevice(int $userId): void
    {
        abort_unless(auth()->user()->hasPermissionTo('edit crm attendances'), 403);

        $userModel = config('auth.providers.users.model');
        $userModel::findOrFail($userId)->forceFill(['bound_device_id' => null])->save();
        $this->success('Perangkat direset. Absen berikutnya akan mengikat perangkat baru.');
    }

    public function render()
    {
        return view('laravel-crm::livewire.attendances.attendance-recap', [
            'rows' => AttendanceRecapService::build($this->month ?: now()->format('Y-m')),
            'canManage' => auth()->user()->hasPermissionTo('edit crm attendances'),
        ]);
    }
}
