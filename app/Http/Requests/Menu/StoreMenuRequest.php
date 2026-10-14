<?php

namespace App\Http\Requests\Menu;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMenuRequest extends FormRequest
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
                Rule::unique('menus', 'code')->where(function ($query) use ($siteId) {
                    return $query->where('site_id', $siteId)->whereNull('deleted_at');
                }),
            ],
            'libelle' => ['required', 'string', 'max:100'],
            'icon' => ['nullable', 'string', 'max:100'],
            'path' => ['nullable', 'string', 'max:255'],
            'order' => ['nullable', 'integer'],
            'reference' => ['nullable', 'string', 'max:100'],
            'permission' => ['nullable', 'string', 'max:255'],
            'statut' => ['nullable', 'string', 'in:ACTIF,INACTIF'],
        ];
    }

    public function messages(): array
    {
        return [
            'code.required' => 'Le code du menu est obligatoire.',
            'code.unique' => 'Ce code de menu existe déjà pour ce site.',
            'libelle.required' => 'Le libellé du menu est obligatoire.',
            'statut.in' => 'Le statut doit être ACTIF ou INACTIF.',
        ];
    }
}
