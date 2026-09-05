<?php

namespace App\Http\Requests\Vendor;

use Illuminate\Foundation\Http\FormRequest;

class RejectVendorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('vendors.approve');
    }

    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'reason.required' => 'Informe o motivo da rejeição.',
            'reason.max' => 'O motivo não pode ter mais de 1000 caracteres.',
        ];
    }
}
