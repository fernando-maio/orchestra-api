<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Qualquer usuario autenticado edita o proprio perfil; nao ha permissao
        // especifica para isso.
        return $this->user() !== null;
    }

    /**
     * O e-mail nao esta aqui de proposito: e a credencial de login, e troca-lo
     * deve passar por um fluxo com verificacao. Como o controller usa
     * validated(), o campo e descartado mesmo se vier no payload.
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'O nome é obrigatório.',
            'name.max' => 'O nome não pode ter mais de 255 caracteres.',
            'phone.max' => 'O telefone não pode ter mais de 20 caracteres.',
        ];
    }
}
