<?php

namespace VentureDrake\LaravelCrm\Policies;

use App\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use VentureDrake\LaravelCrm\Models\Attendance;

class AttendancePolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any attendances.
     *
     * @return mixed
     */
    public function viewAny(User $user)
    {
        return $user->hasPermissionTo('view crm attendances');
    }

    /**
     * Determine whether the user can view the attendance.
     *
     * @return mixed
     */
    public function view(User $user, Attendance $attendance)
    {
        if (! $user->hasPermissionTo('view crm attendances')) {
            return false;
        }

        return $attendance->user_id === $user->id
            || $user->hasPermissionTo('edit crm attendances');
    }

    /**
     * Determine whether the user can create attendances.
     *
     * @return mixed
     */
    public function create(User $user)
    {
        return $user->hasPermissionTo('create crm attendances');
    }

    /**
     * Determine whether the user can update the attendance.
     * Only own same-day records, or users with edit permission
     * (e.g. Owner/Admin/Manager) for anyone's record.
     *
     * @return mixed
     */
    public function update(User $user, Attendance $attendance)
    {
        if ($user->hasPermissionTo('edit crm attendances')
            && $attendance->user_id !== $user->id) {
            return true;
        }

        return $attendance->user_id === $user->id
            && $attendance->recorded_at->isToday();
    }

    /**
     * Determine whether the user can delete the attendance.
     *
     * @return mixed
     */
    public function delete(User $user, Attendance $attendance)
    {
        return $user->hasPermissionTo('delete crm attendances');
    }
}