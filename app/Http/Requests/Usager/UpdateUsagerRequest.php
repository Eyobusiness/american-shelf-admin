<?php

namespace App\Http\Requests\Usager;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUsagerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $siteId = $this->attributes->get('current_site_id') ?? $this->user()?->site_id;
        $usagerId = $this->route('id');

        return [
            'nom' => ['sometimes', 'required', 'string', 'max:100'],
            'prenom' => ['sometimes', 'required', 'string', 'max:100'],
            'email' => [
                'nullable',
                'email',
                'max:255',
                Rule::unique('usagers', 'email')->ignore($usagerId),
            ],
            'contact' => ['nullable', 'string', 'max:50'],
            'mot_de_passe' => ['nullable', 'string', 'min:6'],
            'numero' => [
                'sometimes',
                'string',
                'max:50',
                Rule::unique('usagers', 'numero')->ignore($usagerId),
            ],
            'type' => ['nullable', 'string', 'in:USAGER,ADHERENT'],
            'categorie_usager_id' => [
                'nullable',
                'uuid',
                Rule::exists('categories_usagers', 'id')->where(function ($query) use ($siteId) {
                    return $query->where('site_id', $siteId)->whereNull('deleted_at');
                }),
            ],
            'numero_local' => ['nullable', 'string', 'max:50'],
            'date_inscription' => ['nullable', 'date'],
            'statut' => ['nullable', 'string', 'in:ACTIF,SUSPENDU,EXPIRE'],
        ];
    }

    public function messages(): array
    {
        return [
            'nom.required' => 'Le nom de l\'usager est obligatoire.',
            'prenom.required' => 'Le prénom de l\'usager est obligatoire.',
            'email.email' => 'L\'adresse email est invalide.',
            'email.unique' => 'Cette adresse email est déjà utilisée par un autre usager.',
            'mot_de_passe.min' => 'Le mot de passe doit comporter au moins 6 caractères.',
            'type.in' => 'Le type d\'usager doit être USAGER ou ADHERENT.',
            'categorie_usager_id.exists' => 'La catégorie sélectionnée est invalide ou n\'appartient pas à ce site.',
            'statut.in' => 'Le statut doit être ACTIF, SUSPENDU ou EXPIRE.',
        ];
    }
}
