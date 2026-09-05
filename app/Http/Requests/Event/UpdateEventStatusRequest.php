<?php

namespace App\Http\Requests\Event;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEventStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('events.update');
    }

    /**
     * Os valores batem com o enum da migration. Atenção: é 'canceled' com um
     * L só — 'cancelled' já causou bug neste projeto.
     */
    public function rules(): array
    {
        return [
            'status' => ['required', 'string', Rule::in(['draft', 'active', 'completed', 'canceled'])],
        ];
    }

    public function messages(): array
    {
        return [
            'status.required' => 'Informe o novo status.',
            'status.in' => 'Status inválido.',
        ];
    }
}
