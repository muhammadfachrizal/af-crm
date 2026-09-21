<?php

namespace VentureDrake\LaravelCrm\Http\Resources\Api\V2\Related;

use Illuminate\Http\Resources\Json\JsonResource;

class OrderBriefResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->external_id,
            'order_id' => $this->order_id,
            'reference' => $this->reference,
        ];
    }
}
