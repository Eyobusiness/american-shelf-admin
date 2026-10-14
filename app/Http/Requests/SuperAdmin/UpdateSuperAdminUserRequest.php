<?php

namespace App\Http\Requests\SuperAdmin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSuperAdminUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $userId = $this->route('user') ?? $this->route('id');
        if (is_object($userId)) {
            $userId = $userId->id;
        }

        return [
            'nom' => ['sometimes', 'required', 'string', 'max:100'],
            'prenom' => ['sometimes', 'required', 'string', 'max:100'],
            'email' => ['sometimes', 'required', 'email', 'max:150', Rule::unique('utilisateurs', 'email')->ignore($userId)],
            'mot_de_passe' => ['nullable', 'string', 'min:8', 'max:100'],
            'telephone' => ['nullable', 'string', 'max:50'],
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
            'mot_de_passe.min' => 'Le mot de passe doit comporter au moins 8 caractères.',
            'statut.in' => 'Le statut doit être ACTIF, INACTIF ou BLOQUE.',
        ];
    }
}
