<?php

namespace VentureDrake\LaravelCrm\Http\Resources\Api\V2\Related;

use Illuminate\Http\Resources\Json\JsonResource;

class TaxRateBriefResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => (string) $this->id,
            'name' => $this->name,
            'rate' => $this->rate !== null ? (float) $this->rate : null,
        ];
    }
}
