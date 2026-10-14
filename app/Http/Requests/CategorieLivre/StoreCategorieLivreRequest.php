<?php

namespace App\Http\Requests\CategorieLivre;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCategorieLivreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $siteId = $this->attributes->get('current_site_id');

        return [
            'code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('categories_livres', 'code')->where(function ($query) use ($siteId) {
                    return $query->where('site_id', $siteId)->whereNull('deleted_at');
                }),
            ],
            'nom' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
            'statut' => ['nullable', 'string', 'in:ACTIF,INACTIF'],
        ];
    }

    public function messages(): array
    {
        return [
            'code.required' => 'Le code de la catégorie de livre est obligatoire.',
            'code.unique' => 'Ce code de catégorie de livre existe déjà pour ce site.',
            'nom.required' => 'Le nom de la catégorie de livre est obligatoire.',
            'statut.in' => 'Le statut doit être ACTIF ou INACTIF.',
        ];
    }
}
