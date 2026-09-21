<?php

namespace VentureDrake\LaravelCrm\Http\Resources\Api\V2\Related;

use Illuminate\Http\Resources\Json\JsonResource;

class QuoteBriefResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->external_id,
            'quote_id' => $this->quote_id,
            'title' => $this->title,
            'reference' => $this->reference,
        ];
    }
}
