<?php

namespace App\Http\Requests\Utilisateur;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUtilisateurRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $siteId = $this->attributes->get('current_site_id') ?? $this->user()?->site_id;
        $id = $this->route('utilisateur') ?? $this->route('id');
        if (is_object($id)) {
            $id = $id->id;
        }

        return [
            'nom' => ['sometimes', 'required', 'string', 'max:100'],
            'prenom' => ['sometimes', 'required', 'string', 'max:100'],
            'telephone' => ['nullable', 'string', 'max:50'],
            'email' => [
                'sometimes',
                'required',
                'email',
                'max:150',
                Rule::unique('utilisateurs', 'email')->ignore($id),
            ],
            'mot_de_passe' => ['nullable', 'string', 'min:8', 'max:100'],
            'role_id' => [
                'sometimes',
                'required',
                'uuid',
                Rule::exists('roles', 'id')->where(function ($query) use ($siteId) {
                    return $query->where('site_id', $siteId)->whereNull('deleted_at');
                }),
            ],
            'statut' => ['nullable', 'string', 'in:ACTIF,INACTIF,SUSPENDU'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.email' => 'L\'adresse email doit être valide.',
            'email.unique' => 'Cette adresse email est déjà utilisée.',
            'mot_de_passe.min' => 'Le mot de passe doit comporter au moins 8 caractères.',
            'role_id.exists' => 'Le rôle sélectionné est invalide ou n\'appartient pas à ce site.',
            'statut.in' => 'Le statut doit être ACTIF, INACTIF ou SUSPENDU.',
        ];
    }
}
