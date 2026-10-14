<?php

namespace App\Http\Requests\SuperAdmin;

use Illuminate\Foundation\Http\FormRequest;

class AssignSiteAdminRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nom' => ['required', 'string', 'max:100'],
            'prenom' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:150', 'unique:utilisateurs,email'],
            'mot_de_passe' => ['required', 'string', 'min:8', 'max:100'],
            'telephone' => ['nullable', 'string', 'max:50'],
            'statut' => ['nullable', 'string', 'in:ACTIF,INACTIF,BLOQUE'],
        ];
    }

    public function messages(): array
    {
        return [
            'nom.required' => 'Le nom de l\'administrateur est obligatoire.',
            'prenom.required' => 'Le prénom de l\'administrateur est obligatoire.',
            'email.required' => 'L\'adresse email est obligatoire.',
            'email.email' => 'L\'adresse email doit être valide.',
            'email.unique' => 'Cette adresse email est déjà utilisée par un autre compte.',
            'mot_de_passe.required' => 'Le mot de passe est obligatoire.',
            'mot_de_passe.min' => 'Le mot de passe doit comporter au moins 8 caractères.',
        ];
    }
}
