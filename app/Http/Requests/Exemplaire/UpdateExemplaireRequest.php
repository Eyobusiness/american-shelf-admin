<?php

namespace App\Http\Requests\Exemplaire;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateExemplaireRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $siteId = $this->attributes->get('current_site_id') ?? $this->user()?->site_id;
        $exemplaireId = $this->route('id');

        return [
            'code' => [
                'sometimes',
                'required',
                'string',
                'max:50',
                Rule::unique('exemplaires', 'code')->where(function ($query) use ($siteId) {
                    return $query->where('site_id', $siteId)->whereNull('deleted_at');
                })->ignore($exemplaireId),
            ],
            'etat' => ['nullable', 'string', 'in:NEUF,BON,MOYEN,DEGRADE,PERDU'],
            'statut' => ['nullable', 'string', 'in:DISPONIBLE,EMPRUNTE,EN_CONSULTATION,RESERVE,INDISPONIBLE'],
            'localisation' => ['nullable', 'string', 'max:100'],
            'livre_id' => [
                'sometimes',
                'required',
                'uuid',
                Rule::exists('livres', 'id')->where(function ($query) use ($siteId) {
                    return $query->where('site_id', $siteId)->whereNull('deleted_at');
                }),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'code.required' => 'Le code d\'exemplaire est obligatoire.',
            'code.unique' => 'Ce code d\'exemplaire existe déjà pour ce site.',
            'etat.in' => 'L\'état de l\'exemplaire doit être : NEUF, BON, MOYEN, DEGRADE ou PERDU.',
            'statut.in' => 'Le statut doit être : DISPONIBLE, EMPRUNTE, EN_CONSULTATION, RESERVE ou INDISPONIBLE.',
            'localisation.max' => 'La localisation ne peut pas dépasser 100 caractères.',
            'livre_id.exists' => 'Le livre sélectionné est invalide ou n\'appartient pas à ce site.',
        ];
    }
}
