<?php

namespace App\Http\Requests\SousMenu;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSousMenuRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $siteId = $this->attributes->get('current_site_id') ?? $this->user()?->site_id;
        $id = $this->route('sous_menu') ?? $this->route('id');
        if (is_object($id)) {
            $id = $id->id;
        }

        $menuId = $this->input('menu_id') ?? $this->route('sous_menu')?->menu_id;

        return [
            'menu_id' => [
                'sometimes',
                'required',
                'uuid',
                // Le menu parent doit appartenir au même site
                Rule::exists('menus', 'id')->where(function ($query) use ($siteId) {
                    return $query->where('site_id', $siteId)->whereNull('deleted_at');
                }),
            ],
            'code' => [
                'sometimes',
                'required',
                'string',
                'max:50',
                Rule::unique('sous_menus', 'code')->where(function ($query) use ($siteId, $menuId) {
                    $q = $query->where('site_id', $siteId)->whereNull('deleted_at');
                    if ($menuId) {
                        $q->where('menu_id', $menuId);
                    }
                    return $q;
                })->ignore($id),
            ],
            'libelle' => ['sometimes', 'required', 'string', 'max:100'],
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
            'menu_id.exists' => 'Le menu parent sélectionné est invalide ou n\'appartient pas à ce site.',
            'code.unique' => 'Ce code de sous-menu existe déjà pour ce menu.',
            'statut.in' => 'Le statut doit être ACTIF ou INACTIF.',
        ];
    }
}
