<?php

namespace App\Http\Requests\Role;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $siteId = $this->attributes->get('current_site_id') ?? $this->user()?->site_id;
        $id = $this->route('role') ?? $this->route('id');
        if (is_object($id)) {
            $id = $id->id;
        }

        return [
            'code' => [
                'sometimes',
                'required',
                'string',
                'max:50',
                Rule::unique('roles', 'code')->where(function ($query) use ($siteId) {
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
            'code.unique' => 'Ce code de rôle existe déjà pour ce site.',
            'statut.in' => 'Le statut doit être ACTIF ou INACTIF.',
        ];
    }
}
