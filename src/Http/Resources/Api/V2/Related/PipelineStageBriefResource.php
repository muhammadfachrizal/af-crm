<?php

namespace VentureDrake\LaravelCrm\Http\Resources\Api\V2\Related;

use Illuminate\Http\Resources\Json\JsonResource;

class PipelineStageBriefResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->external_id,
            'name' => $this->name,
            'color' => $this->color,
            'order' => $this->order !== null ? (int) $this->order : null,
        ];
    }
}
