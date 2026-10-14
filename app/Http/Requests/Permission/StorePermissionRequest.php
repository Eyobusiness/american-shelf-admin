<?php

namespace App\Http\Requests\Permission;

use App\Models\Menu;
use App\Models\SousMenu;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePermissionRequest extends FormRequest
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
                'nullable',
                'uuid',
                Rule::exists('menus', 'id')->where(function ($query) use ($siteId) {
                    return $query->where('site_id', $siteId)->whereNull('deleted_at');
                }),
            ],
            'sous_menu_id' => [
                'nullable',
                'uuid',
                Rule::exists('sous_menus', 'id')->where(function ($query) use ($siteId, $menuId) {
                    $q = $query->where('site_id', $siteId)->whereNull('deleted_at');
                    if ($menuId) {
                        $q->where('menu_id', $menuId);
                    }
                    return $q;
                }),
            ],
            'action' => ['required', 'string', 'max:50'],
            'code' => [
                'required',
                'string',
                'max:100',
                Rule::unique('permissions', 'code')->where(function ($query) use ($siteId) {
                    return $query->where('site_id', $siteId);
                }),
            ],
            'libelle' => ['required', 'string', 'max:150'],
        ];
    }

    public function messages(): array
    {
        return [
            'menu_id.exists' => 'Le menu sélectionné est invalide ou n\'appartient pas à ce site.',
            'sous_menu_id.exists' => 'Le sous-menu sélectionné est invalide, n\'appartient pas à ce site ou n\'appartient pas au menu choisi.',
            'action.required' => 'L\'action est obligatoire (ex: CREATE, READ, UPDATE, DELETE).',
            'code.required' => 'Le code de la permission est obligatoire.',
            'code.unique' => 'Ce code de permission existe déjà pour ce site.',
            'libelle.required' => 'Le libellé de la permission est obligatoire.',
        ];
    }
}
