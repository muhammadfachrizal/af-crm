<?php

namespace VentureDrake\LaravelCrm\Http\Resources\Api\V2;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TaskResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'external_id' => $this->external_id,
            'name' => $this->name,
            'description' => $this->description,
            'due_at' => $this->due_at,
            'completed_at' => $this->completed_at,

            'taskable_type' => $this->taskable_type,
            'taskable_id' => $this->taskable_id,

            'user_owner_id' => $this->user_owner_id,
            'user_assigned_id' => $this->user_assigned_id,

            'owner' => $this->whenLoaded('ownerUser', function () {
                return $this->ownerUser ? [
                    'id' => $this->ownerUser->id,
                    'name' => $this->ownerUser->name,
                    'email' => $this->ownerUser->email,
                ] : null;
            }),

            'assigned_to' => $this->whenLoaded('assignedToUser', function () {
                return $this->assignedToUser ? [
                    'id' => $this->assignedToUser->id,
                    'name' => $this->assignedToUser->name,
                    'email' => $this->assignedToUser->email,
                ] : null;
            }),

            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}