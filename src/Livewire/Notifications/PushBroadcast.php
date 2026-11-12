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

        $userModel = config('auth.providers.users.model');
        $ids = $this->all ? $userModel::pluck('id')->all() : array_map('intval', $this->userIds);

        \VentureDrake\LaravelCrm\Support\Notifier::send($ids, $this->title, $this->body, 'broadcast');

        $devices = DeviceToken::whereIn('user_id', $ids ?: [0])->count();
        $this->reset(['title', 'body', 'userIds']);

        $this->success(count($ids).' user menerima notifikasi di aplikasi'.($devices && FcmService::enabled() ? " dan push ke {$devices} perangkat." : '.'));
    }

    public function testMe(): void
    {
        $mine = DeviceToken::where('user_id', auth()->id())->count();

        if ($mine === 0) {
            $this->warning('Akun Anda belum punya perangkat terdaftar. Login di aplikasi mobile lalu buka Profil > Pengaturan > Status push.');

            return;
        }

        FcmService::$lastError = null;
        $sent = FcmService::toUsers([auth()->id()], 'Tes notifikasi', 'Jika ini muncul di HP, push FCM sudah berfungsi.', ['type' => 'test']);

        $sent > 0
            ? $this->success("Terkirim ke {$sent} perangkat Anda.")
            : $this->error(FcmService::$lastError ?: 'Tidak terkirim. Lihat storage/logs/laravel.log.');
    }

    public function render()
    {
        $userModel = config('auth.providers.users.model');
        $counts = DeviceToken::query()->selectRaw('user_id, count(*) c')->groupBy('user_id')->pluck('c', 'user_id');

        return view('laravel-crm::livewire.notifications.push-broadcast', [
            'users' => $userModel::orderBy('name')->get(['id', 'name']),
            'counts' => $counts,
            'fcmReady' => FcmService::enabled(),
            'diag' => FcmService::diagnose(),
            'myDevices' => DeviceToken::where('user_id', auth()->id())->count(),
            'allDevices' => DeviceToken::count(),
        ]);
    }
}
