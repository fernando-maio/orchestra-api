<?php

namespace App\Http\Requests\Vendor;

use Illuminate\Foundation\Http\FormRequest;

class NearbyVendorsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('vendors.view');
    }

    public function rules(): array
    {
        return [
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'radius_km' => ['sometimes', 'integer', 'min:1', 'max:500'],
            'category_id' => ['sometimes', 'uuid', 'exists:categories,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'latitude.required' => 'Informe a latitude.',
            'latitude.between' => 'Latitude deve estar entre -90 e 90.',
            'longitude.required' => 'Informe a longitude.',
            'longitude.between' => 'Longitude deve estar entre -180 e 180.',
            'radius_km.max' => 'O raio máximo de busca é 500 km.',
        ];
    }
}
