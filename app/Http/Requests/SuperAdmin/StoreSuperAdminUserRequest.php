<?php

namespace App\Http\Requests\SuperAdmin;

use Illuminate\Foundation\Http\FormRequest;

class StoreSuperAdminUserRequest extends FormRequest
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
            'site_id' => ['nullable', 'uuid', 'exists:sites,id'],
            'statut' => ['nullable', 'string', 'in:ACTIF,INACTIF,BLOQUE'],
        ];
    }

    public function messages(): array
    {
        return [
            'nom.required' => 'Le nom est obligatoire.',
            'prenom.required' => 'Le prénom est obligatoire.',
            'email.required' => 'L\'adresse email est obligatoire.',
            'email.email' => 'L\'adresse email doit être valide.',
            'email.unique' => 'Cette adresse email est déjà utilisée.',
            'mot_de_passe.required' => 'Le mot de passe est obligatoire.',
            'mot_de_passe.min' => 'Le mot de passe doit comporter au moins 8 caractères.',
            'site_id.exists' => 'Le site de rattachement sélectionné est introuvable.',
            'statut.in' => 'Le statut doit être ACTIF, INACTIF ou BLOQUE.',
        ];
    }
}
