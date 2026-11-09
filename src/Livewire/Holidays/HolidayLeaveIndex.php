<?php

namespace VentureDrake\LaravelCrm\Livewire\Holidays;

use Carbon\Carbon;
use Livewire\Component;
use Mary\Traits\Toast;
use VentureDrake\LaravelCrm\Models\Holiday;
use VentureDrake\LaravelCrm\Models\Leave;

class HolidayLeaveIndex extends Component
{
    use Toast;

    public int $year;

    // Hari libur
    public ?string $h_date = null;

    public string $h_name = '';

    public bool $h_joint = false;

    public string $bulk = '';

    // Cuti / izin
    public $l_user = null;

    public string $l_type = 'cuti';

    public ?string $l_start = null;

    public ?string $l_end = null;

    public ?string $l_notes = null;

    public function mount(): void
    {
        abort_unless(auth()->user()->hasPermissionTo('view crm attendances'), 403);
        $this->year = (int) now()->format('Y');
    }

    protected function manage(): void
    {
        abort_unless(auth()->user()->hasPermissionTo('edit crm attendances'), 403);
    }

    public function addHoliday(): void
    {
        $this->manage();
        $this->validate(['h_date' => ['required', 'date'], 'h_name' => ['required', 'string', 'max:150']]);

        Holiday::updateOrCreate(['date' => $this->h_date], ['name' => $this->h_name, 'is_joint_leave' => $this->h_joint]);
        $this->reset(['h_date', 'h_name', 'h_joint']);
        $this->success('Hari libur disimpan.');
    }

    /** Tempel banyak baris: "2027-01-01 Tahun Baru" atau "2027-03-12 Cuti Bersama Nyepi | cuti". */
    public function importBulk(): void
    {
        $this->manage();
        $count = 0;

        foreach (preg_split('/\r?\n/', trim($this->bulk)) as $line) {
            if (! preg_match('/^(\d{4}-\d{2}-\d{2})\s+(.+)$/', trim($line), $m)) {
                continue;
            }

            $joint = (bool) preg_match('/\|\s*cuti\s*$/i', $m[2]);
            $name = trim(preg_replace('/\|\s*cuti\s*$/i', '', $m[2]));

            Holiday::updateOrCreate(['date' => $m[1]], ['name' => $name, 'is_joint_leave' => $joint]);
            $count++;
        }

        $this->bulk = '';
        $count ? $this->success("{$count} hari libur diimpor.") : $this->warning('Tidak ada baris valid. Format: YYYY-MM-DD Nama libur');
    }

    public function deleteHoliday(int $id): void
    {
        $this->manage();
        Holiday::findOrFail($id)->delete();
    }

    public function addLeave(): void
    {
        $this->manage();
        $this->validate([
            'l_user' => ['required'],
            'l_type' => ['required', 'in:cuti,izin,sakit,dinas'],
            'l_start' => ['required', 'date'],
            'l_end' => ['required', 'date', 'after_or_equal:l_start'],
        ]);

        $userModel = config('auth.providers.users.model');
        $userModel::findOrFail($this->l_user); // pastikan user milik tenant ini

        Leave::create(['user_id' => $this->l_user, 'type' => $this->l_type, 'start_date' => $this->l_start, 'end_date' => $this->l_end, 'notes' => $this->l_notes]);
        $this->reset(['l_user', 'l_start', 'l_end', 'l_notes']);
        $this->success('Cuti/izin disimpan.');
    }

    public function deleteLeave(int $id): void
    {
        $this->manage();
        Leave::findOrFail($id)->delete();
    }

    public function render()
    {
        $userModel = config('auth.providers.users.model');

        return view('laravel-crm::livewire.holidays.holiday-leave-index', [
            'holidays' => Holiday::whereYear('date', $this->year)->orderBy('date')->get(),
            'leaves' => Leave::with('user')->where('end_date', '>=', Carbon::create($this->year, 1, 1))
                ->where('start_date', '<=', Carbon::create($this->year, 12, 31))->orderByDesc('start_date')->get(),
            'users' => $userModel::orderBy('name')->get(['id', 'name']),
            'types' => Leave::TYPES,
            'canManage' => auth()->user()->hasPermissionTo('edit crm attendances'),
        ]);
    }
}
