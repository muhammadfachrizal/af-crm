<?php

namespace VentureDrake\LaravelCrm\Livewire\Notifications;

use Livewire\Component;
use Mary\Traits\Toast;
use VentureDrake\LaravelCrm\Models\DeviceToken;
use VentureDrake\LaravelCrm\Support\FcmService;

class PushBroadcast extends Component
{
    use Toast;

    public string $title = '';

    public string $body = '';

    public array $userIds = [];

    public bool $all = true;

    public function mount(): void
    {
        abort_unless(auth()->user()->hasPermissionTo('edit crm settings'), 403);
    }

    public function send(): void
    {
        $this->validate([
            'title' => ['required', 'string', 'max:100'],
            'body' => ['required', 'string', 'max:300'],
        ]);

        $query = DeviceToken::query();

        if (! $this->all) {
            $query->whereIn('user_id', $this->userIds ?: [0]);
        }

        $sent = FcmService::toTokens($query->pluck('token')->all(), $this->title, $this->body, ['type' => 'broadcast']);

        $this->reset(['title', 'body']);

        $sent > 0
            ? $this->success("Notifikasi dikirim ke {$sent} perangkat.")
            : $this->warning('Tidak ada perangkat yang menerima (belum ada token, atau FCM belum dikonfigurasi).');
    }

    public function render()
    {
        $userModel = config('auth.providers.users.model');
        $counts = DeviceToken::query()->selectRaw('user_id, count(*) c')->groupBy('user_id')->pluck('c', 'user_id');

        return view('laravel-crm::livewire.notifications.push-broadcast', [
            'users' => $userModel::orderBy('name')->get(['id', 'name']),
            'counts' => $counts,
            'fcmReady' => FcmService::enabled(),
        ]);
    }
}
