<?php

namespace VentureDrake\LaravelCrm\Livewire\Notifications;

use Livewire\Component;
use VentureDrake\LaravelCrm\Models\InAppNotification;

/** Lonceng notifikasi di header web (auto refresh 30 detik). */
class NotificationBell extends Component
{
    public function markAll(): void
    {
        InAppNotification::where('user_id', auth()->id())->whereNull('read_at')->update(['read_at' => now()]);
    }

    public function open(int $id)
    {
        InAppNotification::where('user_id', auth()->id())->where('id', $id)->whereNull('read_at')->update(['read_at' => now()]);

        return redirect()->route('laravel-crm.notifications.index');
    }

    public function render()
    {
        $q = InAppNotification::where('user_id', auth()->id());

        return view('laravel-crm::livewire.notifications.notification-bell', [
            'unread' => (clone $q)->whereNull('read_at')->count(),
            'latest' => (clone $q)->orderByDesc('id')->limit(6)->get(),
        ]);
    }
}
