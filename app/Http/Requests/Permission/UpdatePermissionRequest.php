<?php

namespace App\Http\Requests\Permission;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePermissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $siteId = $this->attributes->get('current_site_id') ?? $this->user()?->site_id;
        $id = $this->route('permission') ?? $this->route('id');
        if (is_object($id)) {
            $id = $id->id;
        }

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
            'action' => ['sometimes', 'required', 'string', 'max:50'],
            'code' => [
                'sometimes',
                'required',
                'string',
                'max:100',
                Rule::unique('permissions', 'code')->where(function ($query) use ($siteId) {
                    return $query->where('site_id', $siteId);
                })->ignore($id),
            ],
            'libelle' => ['sometimes', 'required', 'string', 'max:150'],
        ];
    }

    public function messages(): array
    {
        return [
            'menu_id.exists' => 'Le menu sélectionné est invalide ou n\'appartient pas à ce site.',
            'sous_menu_id.exists' => 'Le sous-menu sélectionné est invalide, n\'appartient pas à ce site ou n\'appartient pas au menu choisi.',
            'code.unique' => 'Ce code de permission existe déjà pour ce site.',
        ];
    }
}
