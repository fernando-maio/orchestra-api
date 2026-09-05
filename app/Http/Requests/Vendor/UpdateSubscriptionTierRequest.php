<?php

namespace App\Http\Requests\Vendor;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSubscriptionTierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('vendors.update');
    }

    public function rules(): array
    {
        return [
            'subscription_tier' => ['required', Rule::in(['free', 'featured', 'premium'])],
        ];
    }

    public function messages(): array
    {
        return [
            'subscription_tier.required' => 'Informe o plano.',
            'subscription_tier.in' => 'Plano inválido. Use free, featured ou premium.',
        ];
    }
}
