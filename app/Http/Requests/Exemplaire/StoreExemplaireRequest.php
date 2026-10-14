<?php

namespace App\Http\Requests\Exemplaire;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreExemplaireRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $siteId = $this->attributes->get('current_site_id') ?? $this->user()?->site_id;

        return [
            'livre_id' => [
                'required',
                'uuid',
                // Règle d'étanchéité multi-tenant : le livre doit appartenir au même site
                Rule::exists('livres', 'id')->where(function ($query) use ($siteId) {
                    return $query->where('site_id', $siteId)->whereNull('deleted_at');
                }),
            ],
            'code' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('exemplaires', 'code')->where(function ($query) use ($siteId) {
                    return $query->where('site_id', $siteId)->whereNull('deleted_at');
                }),
            ],
            'etat' => ['nullable', 'string', 'in:NEUF,BON,MOYEN,DEGRADE,PERDU'],
            'statut' => ['nullable', 'string', 'in:DISPONIBLE,EMPRUNTE,EN_CONSULTATION,RESERVE,INDISPONIBLE'],
            'localisation' => ['nullable', 'string', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'livre_id.required' => 'Le livre parent est obligatoire.',
            'livre_id.exists' => 'Le livre sélectionné est invalide ou n\'appartient pas à ce site.',
            'code.unique' => 'Ce code d\'exemplaire existe déjà pour ce site.',
            'etat.in' => 'L\'état de l\'exemplaire doit être : NEUF, BON, MOYEN, DEGRADE ou PERDU.',
            'statut.in' => 'Le statut doit être : DISPONIBLE, EMPRUNTE, EN_CONSULTATION, RESERVE ou INDISPONIBLE.',
            'localisation.max' => 'La localisation ne peut pas dépasser 100 caractères.',
        ];
    }
}
