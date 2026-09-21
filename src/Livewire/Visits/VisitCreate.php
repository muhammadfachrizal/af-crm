<?php

namespace VentureDrake\LaravelCrm\Livewire\Visits;

use App\User;
use Illuminate\Support\Collection;
use Livewire\Component;
use Mary\Traits\Toast;
use VentureDrake\LaravelCrm\Models\Visit;

class VisitCreate extends Component
{
    use Toast;

    public ?string $title = null;
    public ?float $target_latitude = null;
    public ?float $target_longitude = null;
    public int $target_radius_meters = 100;
    public ?string $target_address = null;
    public ?string $scheduled_at = null;
    public ?string $notes = null;
    public ?int $assign_user_id = null;

    public function mount()
    {
        $this->assign_user_id = auth()->id();
    }

    public function canAssignOthers(): bool
    {
        return auth()->user()->hasPermissionTo('edit crm visits');
    }

    public function users(): Collection
    {
        $userModel = config('auth.providers.users.model');

        return $userModel::orderBy('name')->get();
    }

    protected function rules(): array
    {
        return [
            'title' => ['nullable', 'string', 'max:255'],
            'target_latitude' => ['required', 'numeric', 'between:-90,90'],
            'target_longitude' => ['required', 'numeric', 'between:-180,180'],
            'target_radius_meters' => ['required', 'integer', 'min:10', 'max:5000'],
            'target_address' => ['nullable', 'string', 'max:255'],
            'scheduled_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ];
    }

    protected function messages(): array
    {
        return [
            'target_latitude.required' => 'Silakan pilih lokasi di peta dulu.',
            'target_longitude.required' => 'Silakan pilih lokasi di peta dulu.',
        ];
    }

    public function save()
    {
        $this->authorize('create', Visit::class);

        $this->validate();

        $targetUserId = auth()->id();

        if ($this->assign_user_id && $this->assign_user_id != auth()->id()) {
            if (! $this->canAssignOthers()) {
                $this->error('Anda tidak punya izin untuk assign visit ke orang lain.');

                return;
            }

            $targetUser = User::find($this->assign_user_id);

            if (! $targetUser) {
                $this->error('User tujuan tidak ditemukan.');

                return;
            }

            $targetUserId = $targetUser->id;
        }

        Visit::create([
            'user_id' => $targetUserId,
            'assigned_by_user_id' => $targetUserId !== auth()->id() ? auth()->id() : null,
            'title' => $this->title,
            'target_latitude' => $this->target_latitude,
            'target_longitude' => $this->target_longitude,
            'target_radius_meters' => $this->target_radius_meters,
            'target_address' => $this->target_address,
            'scheduled_at' => $this->scheduled_at,
            'notes' => $this->notes,
            'status' => 'planned',
        ]);

        $this->success(
            'Visit berhasil dibuat.',
            redirectTo: route('laravel-crm.visits.index')
        );
    }

    public function render()
    {
        return view('laravel-crm::livewire.visits.visit-create');
    }
}
