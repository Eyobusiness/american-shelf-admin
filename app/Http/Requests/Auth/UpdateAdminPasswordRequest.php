<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAdminPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'mot_de_passe_actuel' => ['required', 'string'],
            'mot_de_passe' => ['required', 'string', 'min:8', 'max:100', 'confirmed'],
            'mot_de_passe_confirmation' => ['required', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'mot_de_passe_actuel.required' => 'Le mot de passe actuel est obligatoire.',
            'mot_de_passe.required' => 'Le nouveau mot de passe est obligatoire.',
            'mot_de_passe.min' => 'Le nouveau mot de passe doit comporter au moins 8 caractères.',
            'mot_de_passe.confirmed' => 'La confirmation du nouveau mot de passe ne correspond pas.',
            'mot_de_passe_confirmation.required' => 'La confirmation du mot de passe est obligatoire.',
        ];
    }
}