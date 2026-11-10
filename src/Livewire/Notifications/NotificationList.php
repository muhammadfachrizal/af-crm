<?php

namespace VentureDrake\LaravelCrm\Livewire\Notifications;

use Livewire\Component;
use Livewire\WithPagination;
use VentureDrake\LaravelCrm\Models\InAppNotification;

class NotificationList extends Component
{
    use WithPagination;

    public string $filter = 'all'; // all | unread

    public function updatingFilter(): void
    {
        $this->resetPage();
    }

    public function markRead(int $id): void
    {
        InAppNotification::where('user_id', auth()->id())->where('id', $id)->update(['read_at' => now()]);
    }

    public function markAll(): void
    {
        InAppNotification::where('user_id', auth()->id())->whereNull('read_at')->update(['read_at' => now()]);
    }

    public function delete(int $id): void
    {
        InAppNotification::where('user_id', auth()->id())->where('id', $id)->delete();
    }

    public function render()
    {
        $q = InAppNotification::where('user_id', auth()->id())
            ->when($this->filter === 'unread', fn ($q) => $q->whereNull('read_at'))
            ->orderByDesc('id');

        return view('laravel-crm::livewire.notifications.notification-list', ['items' => $q->paginate(20)]);
    }
}
