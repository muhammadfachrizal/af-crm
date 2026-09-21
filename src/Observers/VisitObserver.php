<?php

namespace VentureDrake\LaravelCrm\Observers;

use Ramsey\Uuid\Uuid;
use VentureDrake\LaravelCrm\Models\Visit;

class VisitObserver
{
    public function creating(Visit $visit)
    {
        $visit->external_id = Uuid::uuid4()->toString();

        if (auth()->check()) {
            $visit->user_created_id = auth()->id();

            if (empty($visit->user_id)) {
                $visit->user_id = auth()->id();
            }
        }
    }

    public function updating(Visit $visit)
    {
        if (auth()->check()) {
            $visit->user_updated_id = auth()->id();
        }
    }

    public function deleting(Visit $visit)
    {
        if (auth()->check()) {
            $visit->user_deleted_id = auth()->id();
            $visit->saveQuietly();
        }
    }
}
