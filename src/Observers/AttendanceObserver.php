<?php

namespace VentureDrake\LaravelCrm\Observers;

use Ramsey\Uuid\Uuid;
use VentureDrake\LaravelCrm\Models\Attendance;

class AttendanceObserver
{
    /**
     * Handle the attendance "creating" event.
     *
     * @return void
     */
    public function creating(Attendance $attendance)
    {
        $attendance->external_id = Uuid::uuid4()->toString();

        if (auth()->check()) {
            $attendance->user_created_id = auth()->id();

            if (empty($attendance->user_id)) {
                $attendance->user_id = auth()->id();
            }
        }
    }

    /**
     * Handle the attendance "updating" event.
     *
     * @return void
     */
    public function updating(Attendance $attendance)
    {
        if (auth()->check()) {
            $attendance->user_updated_id = auth()->id();
        }
    }

    /**
     * Handle the attendance "deleting" event.
     *
     * @return void
     */
    public function deleting(Attendance $attendance)
    {
        if (auth()->check()) {
            $attendance->user_deleted_id = auth()->id();
            $attendance->saveQuietly();
        }
    }
}
