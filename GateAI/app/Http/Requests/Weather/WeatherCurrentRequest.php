<?php

namespace App\Http\Requests\Weather;

use Illuminate\Foundation\Http\FormRequest;

class WeatherCurrentRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'latitude'  => 'numeric|between:-90,90',
            'longitude' => 'numeric|between:-180,180',
        ];
    }

    public function messages(): array
    {
        return [
            'latitude.numeric'  => '纬度必须为数字',
            'latitude.between'  => '纬度范围必须在 -90 到 90 之间',
            'longitude.numeric' => '经度必须为数字',
            'longitude.between' => '经度范围必须在 -180 到 180 之间',
        ];
    }
}
