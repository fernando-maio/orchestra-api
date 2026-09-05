<?php

namespace App\Http\Requests\PublicVendor;

use Illuminate\Foundation\Http\FormRequest;

class CheckCnpjRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'cnpj' => ['required', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'cnpj.required' => 'Informe o CNPJ.',
        ];
    }
}
