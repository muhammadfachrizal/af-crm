<?php

namespace VentureDrake\LaravelCrm\Http\Resources\Api\V2;

use Illuminate\Http\Resources\Json\JsonResource;
use VentureDrake\LaravelCrm\Http\Resources\Api\V2\Related\UserBriefResource;

class AttendanceResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->external_id,
            'type' => $this->type,
            'recorded_at' => $this->recorded_at?->toIso8601String(),
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'address' => $this->address,
            'photo_url' => $this->photo_path ? asset('storage/'.$this->photo_path) : null,
            'notes' => $this->notes,
            'user' => $this->whenLoaded('user', fn () => $this->user ? new UserBriefResource($this->user) : null),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
