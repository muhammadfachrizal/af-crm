<?php

namespace VentureDrake\LaravelCrm\Http\Requests\Api\V2;

use Illuminate\Foundation\Http\FormRequest;

class CheckinVisitRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'photo' => ['nullable', 'image', 'max:5120'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
