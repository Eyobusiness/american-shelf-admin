<?php

namespace App\Http\Requests\SousMenu;

use App\Models\Menu;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSousMenuRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $siteId = $this->attributes->get('current_site_id') ?? $this->user()?->site_id;
        $menuId = $this->input('menu_id');

        return [
            'menu_id' => [
                'required',
                'uuid',
                // Règle critique : Le menu parent doit appartenir au même site
                Rule::exists('menus', 'id')->where(function ($query) use ($siteId) {
                    return $query->where('site_id', $siteId)->whereNull('deleted_at');
                }),
            ],
            'code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('sous_menus', 'code')->where(function ($query) use ($siteId, $menuId) {
                    return $query->where('site_id', $siteId)
                        ->where('menu_id', $menuId)
                        ->whereNull('deleted_at');
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
            'menu_id.required' => 'Le menu parent est obligatoire.',
            'menu_id.exists' => 'Le menu parent sélectionné est invalide ou n\'appartient pas à ce site.',
            'code.required' => 'Le code du sous-menu est obligatoire.',
            'code.unique' => 'Ce code de sous-menu existe déjà pour ce menu.',
            'libelle.required' => 'Le libellé du sous-menu est obligatoire.',
            'statut.in' => 'Le statut doit être ACTIF ou INACTIF.',
        ];
    }
}
