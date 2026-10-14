<?php

namespace App\Http\Requests\Exemplaire;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreExemplaireBulkRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $siteId = $this->attributes->get('current_site_id') ?? $this->user()?->site_id;
        $routeLivreId = $this->route('id');

        $livreIdRules = [
            'uuid',
            Rule::exists('livres', 'id')->where(function ($query) use ($siteId) {
                return $query->where('site_id', $siteId)->whereNull('deleted_at');
            }),
        ];

        if ($routeLivreId) {
            array_unshift($livreIdRules, 'nullable');
        } else {
            array_unshift($livreIdRules, 'required');
        }

        return [
            'livre_id' => $livreIdRules,
            'nombre' => ['required', 'integer', 'min:1', 'max:100'],
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
            'nombre.required' => 'Le nombre d\'exemplaires à générer est obligatoire.',
            'nombre.integer' => 'Le nombre d\'exemplaires doit être un entier.',
            'nombre.min' => 'Vous devez générer au moins 1 exemplaire.',
            'nombre.max' => 'Vous ne pouvez pas générer plus de 100 exemplaires en une seule opération.',
            'etat.in' => 'L\'état doit être : NEUF, BON, MOYEN, DEGRADE ou PERDU.',
            'statut.in' => 'Le statut doit être : DISPONIBLE, EMPRUNTE, EN_CONSULTATION, RESERVE ou INDISPONIBLE.',
            'localisation.max' => 'La localisation ne peut pas dépasser 100 caractères.',
        ];
    }
}
