<?php

namespace VentureDrake\LaravelCrm\Http\Requests\Api\V2;

use Illuminate\Foundation\Http\FormRequest;

class StoreAttendanceRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'type' => ['required', 'in:in,out'],
            'recorded_at' => ['nullable', 'date'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'address' => ['nullable', 'string', 'max:255'],
            'photo' => ['nullable', 'image', 'max:5120'],
            'notes' => ['nullable', 'string'],
            'is_mocked' => ['nullable', 'boolean'],
            'device_id' => ['nullable', 'string', 'max:100'],
        ];
    }
}
