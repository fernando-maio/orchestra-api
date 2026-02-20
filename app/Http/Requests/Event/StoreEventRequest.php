<?php

namespace App\Http\Requests\Event;

use Illuminate\Foundation\Http\FormRequest;

class StoreEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('events.create');
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'briefing' => ['nullable', 'string', 'max:10000'],
            'start_date' => ['required', 'date', 'after_or_equal:today'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'start_time' => ['nullable', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i'],
            'venue_name' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:500'],
            'city' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'size:2'],
            'zip_code' => ['nullable', 'string', 'max:10'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'estimated_budget' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3'],
            'expected_attendees' => ['nullable', 'integer', 'min:1'],
            'required_categories' => ['nullable', 'array'],
            'required_categories.*' => ['uuid', 'exists:categories,id'],
            'settings' => ['nullable', 'array'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'O nome do evento é obrigatório.',
            'name.max' => 'O nome do evento não pode ter mais de 255 caracteres.',
            'start_date.required' => 'A data de início é obrigatória.',
            'start_date.after_or_equal' => 'A data de início deve ser a partir de hoje.',
            'end_date.after_or_equal' => 'A data de término deve ser igual ou posterior à data de início.',
            'state.size' => 'O estado deve ter exatamente 2 caracteres (ex: SP).',
            'latitude.between' => 'A latitude deve estar entre -90 e 90.',
            'longitude.between' => 'A longitude deve estar entre -180 e 180.',
            'estimated_budget.min' => 'O orçamento estimado não pode ser negativo.',
            'expected_attendees.min' => 'O número de participantes deve ser pelo menos 1.',
            'required_categories.*.exists' => 'Uma ou mais categorias selecionadas não existem.',
        ];
    }
}
