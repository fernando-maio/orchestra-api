<?php

namespace App\Http\Requests\Vendor;

use Illuminate\Foundation\Http\FormRequest;

class StoreVendorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('vendors.create');
    }

    public function rules(): array
    {
        return [
            'trade_name' => ['required', 'string', 'max:255'],
            'legal_name' => ['required', 'string', 'max:255'],
            'cnpj' => ['required', 'string', 'size:18', 'unique:vendors,cnpj'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['required', 'string', 'max:20'],
            'whatsapp' => ['nullable', 'string', 'max:20'],
            'website' => ['nullable', 'url', 'max:255'],
            'address' => ['required', 'string', 'max:500'],
            'city' => ['required', 'string', 'max:100'],
            'state' => ['required', 'string', 'size:2'],
            'zip_code' => ['required', 'string', 'max:10'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'service_radius_km' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'description' => ['nullable', 'string', 'max:2000'],
            'accepts_urgent' => ['boolean'],
            'is_local_business' => ['boolean'],
            'is_sustainable' => ['boolean'],
            'is_minority_owned' => ['boolean'],
            'is_active' => ['boolean'],
            'category_ids' => ['required', 'array', 'min:1'],
            'category_ids.*' => ['required', 'uuid', 'exists:categories,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'trade_name.required' => 'O nome fantasia é obrigatório.',
            'legal_name.required' => 'A razão social é obrigatória.',
            'cnpj.required' => 'O CNPJ é obrigatório.',
            'cnpj.size' => 'O CNPJ deve ter 18 caracteres (XX.XXX.XXX/XXXX-XX).',
            'cnpj.unique' => 'Este CNPJ já está cadastrado.',
            'email.required' => 'O e-mail é obrigatório.',
            'email.email' => 'Informe um e-mail válido.',
            'phone.required' => 'O telefone é obrigatório.',
            'address.required' => 'O endereço é obrigatório.',
            'city.required' => 'A cidade é obrigatória.',
            'state.required' => 'O estado é obrigatório.',
            'state.size' => 'O estado deve ter 2 caracteres (UF).',
            'zip_code.required' => 'O CEP é obrigatório.',
            'category_ids.required' => 'Selecione pelo menos uma categoria.',
            'category_ids.min' => 'Selecione pelo menos uma categoria.',
        ];
    }
}
