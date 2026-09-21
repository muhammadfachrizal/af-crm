<?php

namespace VentureDrake\LaravelCrm\Http\Resources\Api\V2\Related;

use Illuminate\Http\Resources\Json\JsonResource;

class DealBriefResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->external_id,
            'deal_id' => $this->deal_id,
            'title' => $this->title,
        ];
    }
}
