<?php

namespace VentureDrake\LaravelCrm\Policies;

use App\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use VentureDrake\LaravelCrm\Models\Visit;

class VisitPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user)
    {
        return $user->hasPermissionTo('view crm visits');
    }

    public function view(User $user, Visit $visit)
    {
        if (! $user->hasPermissionTo('view crm visits')) {
            return false;
        }

        return $visit->user_id === $user->id
            || $user->hasPermissionTo('edit crm visits');
    }

    public function create(User $user)
    {
        return $user->hasPermissionTo('create crm visits');
    }

    /**
     * Dipakai juga buat cek siapa yang boleh nge-assign visit ke ORANG LAIN
     * (bukan diri sendiri). Kalau gak punya 'edit crm visits', cuma bisa
     * bikin visit buat diri sendiri.
     */
    public function assignToOthers(User $user)
    {
        return $user->hasPermissionTo('edit crm visits');
    }

    public function update(User $user, Visit $visit)
    {
        if ($user->hasPermissionTo('edit crm visits')) {
            return true;
        }

        return $visit->user_id === $user->id;
    }

    public function delete(User $user, Visit $visit)
    {
        return $user->hasPermissionTo('delete crm visits');
    }
}
