<?php

namespace VentureDrake\LaravelCrm\Http\Resources\Api\V2;

use Illuminate\Http\Resources\Json\JsonResource;
use VentureDrake\LaravelCrm\Http\Resources\Api\V2\Related\UserBriefResource;

class VisitResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->external_id,
            'title' => $this->title,
            'status' => $this->status,
            'target_latitude' => $this->target_latitude,
            'target_longitude' => $this->target_longitude,
            'target_radius_meters' => $this->target_radius_meters,
            'target_address' => $this->target_address,
            'scheduled_at' => $this->scheduled_at?->toIso8601String(),
            'checked_in_at' => $this->checked_in_at?->toIso8601String(),
            'checkin_latitude' => $this->checkin_latitude,
            'checkin_longitude' => $this->checkin_longitude,
            'distance_meters' => $this->distance_meters,
            'photo_url' => $this->photo_path ? asset('storage/'.$this->photo_path) : null,
            'notes' => $this->notes,
            'user' => $this->whenLoaded('user', fn () => $this->user ? new UserBriefResource($this->user) : null),
            'assigned_by' => $this->whenLoaded('assignedBy', fn () => $this->assignedBy ? new UserBriefResource($this->assignedBy) : null),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
