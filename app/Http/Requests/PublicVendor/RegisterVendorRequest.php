<?php

namespace App\Http\Requests\PublicVendor;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegisterVendorRequest extends FormRequest
{
    /**
     * Rota publica: o fornecedor se cadastra sem estar autenticado.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Note que approval_status, source e is_active NAO estao aqui. Sao regra
     * de negocio definida pelo VendorService - se viessem do request, um
     * payload malicioso poderia se auto-aprovar.
     */
    public function rules(): array
    {
        return [
            // Dados da empresa
            'trade_name' => ['required', 'string', 'max:255'],
            'legal_name' => ['nullable', 'string', 'max:255'],
            'cnpj' => [
                'required',
                'string',
                'max:20',
                Rule::unique('vendors', 'cnpj')->whereNull('deleted_at'),
            ],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('vendors', 'email')->whereNull('deleted_at'),
            ],
            'phone' => ['nullable', 'string', 'max:20'],
            'whatsapp' => ['nullable', 'string', 'max:20'],
            'website' => ['nullable', 'url', 'max:255'],

            // Endereço
            'address' => ['nullable', 'string', 'max:500'],
            'city' => ['required', 'string', 'max:100'],
            'state' => ['required', 'string', 'size:2'],
            'zip_code' => ['nullable', 'string', 'max:10'],

            'service_radius_km' => ['nullable', 'integer', 'min:1', 'max:500'],
            'description' => ['nullable', 'string', 'max:2000'],

            // Características
            'accepts_urgent' => ['boolean'],
            'is_local_business' => ['boolean'],
            'is_sustainable' => ['boolean'],
            'is_minority_owned' => ['boolean'],

            // Contato principal
            'contact_name' => ['required', 'string', 'max:255'],
            'contact_email' => ['required', 'email', 'max:255'],
            'contact_phone' => ['required', 'string', 'max:20'],

            // Categorias
            'category_ids' => ['required', 'array', 'min:1'],
            'category_ids.*' => ['uuid', 'exists:categories,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'trade_name.required' => 'O nome fantasia é obrigatório.',
            'cnpj.required' => 'O CNPJ é obrigatório.',
            'cnpj.unique' => 'Este CNPJ já está cadastrado.',
            'email.required' => 'O e-mail é obrigatório.',
            'email.unique' => 'Este e-mail já está cadastrado.',
            'city.required' => 'A cidade é obrigatória.',
            'state.required' => 'O estado é obrigatório.',
            'contact_name.required' => 'O nome do contato é obrigatório.',
            'contact_email.required' => 'O e-mail do contato é obrigatório.',
            'contact_phone.required' => 'O telefone do contato é obrigatório.',
            'category_ids.required' => 'Selecione pelo menos uma categoria.',
            'category_ids.min' => 'Selecione pelo menos uma categoria.',
        ];
    }
}
