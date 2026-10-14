<?php

namespace App\Http\Requests\CategorieUsager;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCategorieUsagerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $siteId = $this->attributes->get('current_site_id');
        $id = $this->route('categories_usager') ?? $this->route('id');
        if (is_object($id)) {
            $id = $id->id;
        }

        return [
            'code' => [
                'sometimes',
                'required',
                'string',
                'max:50',
                Rule::unique('categories_usagers', 'code')->where(function ($query) use ($siteId) {
                    return $query->where('site_id', $siteId)->whereNull('deleted_at');
                })->ignore($id),
            ],
            'nom' => ['sometimes', 'required', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
            'statut' => ['nullable', 'string', 'in:ACTIF,INACTIF'],
        ];
    }

    public function messages(): array
    {
        return [
            'code.unique' => 'Ce code de catégorie existe déjà pour ce site.',
            'statut.in' => 'Le statut doit être ACTIF ou INACTIF.',
        ];
    }
}
