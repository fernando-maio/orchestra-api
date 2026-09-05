<?php

namespace App\Http\Requests\Category;

use Illuminate\Foundation\Http\FormRequest;

class ReorderCategoriesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('categories.update');
    }

    public function rules(): array
    {
        return [
            'ids' => ['required', 'array'],
            'ids.*' => ['required', 'uuid', 'exists:categories,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'ids.required' => 'Informe a nova ordem das categorias.',
            'ids.*.exists' => 'Uma das categorias informadas não existe.',
        ];
    }
}
