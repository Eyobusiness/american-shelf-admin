<?php

namespace App\Http\Requests\UsagerSite;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUsagerSiteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $siteId = $this->attributes->get('current_site_id') ?? $this->user()?->site_id;

        return [
            'usager_id' => [
                'required',
                'uuid',
                'exists:usagers,id',
                Rule::unique('usager_sites')->where(function ($query) use ($siteId) {
                    return $query->where('site_id', $siteId)->whereNull('deleted_at');
                }),
            ],
            'type' => ['nullable', 'string', 'in:USAGER,ADHERENT'],
            'categorie_usager_id' => [
                'nullable',
                'uuid',
                Rule::exists('categories_usagers', 'id')->where(function ($query) use ($siteId) {
                    return $query->where('site_id', $siteId)->whereNull('deleted_at');
                }),
            ],
            'numero' => ['nullable', 'string', 'max:50'],
            'date_inscription' => ['nullable', 'date'],
            'statut' => ['nullable', 'string', 'in:ACTIF,SUSPENDU,EXPIRE'],
        ];
    }

    public function messages(): array
    {
        return [
            'usager_id.required' => 'L\'identifiant de l\'usager est obligatoire.',
            'usager_id.exists' => 'L\'usager spécifié est introuvable.',
            'usager_id.unique' => 'Cet usager est déjà membre de ce site.',
            'type.in' => 'Le type doit être USAGER ou ADHERENT.',
            'categorie_usager_id.exists' => 'La catégorie d\'usager sélectionnée est invalide ou n\'appartient pas à ce site.',
            'statut.in' => 'Le statut doit être ACTIF, SUSPENDU ou EXPIRE.',
        ];
    }
}
