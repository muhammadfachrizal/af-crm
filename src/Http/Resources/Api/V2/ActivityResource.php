<?php

namespace VentureDrake\LaravelCrm\Http\Resources\Api\V2;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ActivityResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $record = $this->recordable;

        return [
            'id' => $this->id,

            'type' => $record
                ? strtolower(class_basename($record))
                : null,

            'description' => $this->description,

            'user' => $this->causeable ? [
                'id' => $this->causeable->id,
                'name' => $this->causeable->name,
            ] : null,

            'timelineable' => $this->timelineable ? [
                'id' => $this->timelineable->id,
                'type' => strtolower(class_basename($this->timelineable)),
                'name' => $this->getModelName($this->timelineable),
            ] : null,

            'record' => $record
                ? $this->getRecordData($record)
                : null,

            'created_at' => $this->created_at?->toISOString(),
        ];
    }

    private function getModelName($model): ?string
    {
        return $model->name
            ?? $model->title
            ?? $model->lead_id
            ?? $model->deal_id
            ?? null;
    }

    private function getRecordData($record): array
    {
        return [
            'id' => $record->id,
            'type' => strtolower(class_basename($record)),
            'name' => $record->name
                ?? $record->title
                ?? $record->content
                ?? $record->original_name
                ?? null,
        ];
    }
}