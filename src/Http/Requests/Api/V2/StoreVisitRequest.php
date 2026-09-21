<?php

namespace VentureDrake\LaravelCrm\Http\Requests\Api\V2;

use Illuminate\Foundation\Http\FormRequest;

class StoreVisitRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'title' => ['nullable', 'string', 'max:255'],
            'target_latitude' => ['required', 'numeric', 'between:-90,90'],
            'target_longitude' => ['required', 'numeric', 'between:-180,180'],
            'target_radius_meters' => ['nullable', 'integer', 'min:10', 'max:5000'],
            'target_address' => ['nullable', 'string', 'max:255'],
            'scheduled_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
            // Cuma dipakai kalau assign ke orang lain (dicek permission di controller)
            'user_id' => ['nullable', 'string'],
        ];
    }
}
