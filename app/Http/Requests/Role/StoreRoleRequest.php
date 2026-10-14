<?php

namespace App\Http\Requests\Role;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $siteId = $this->attributes->get('current_site_id') ?? $this->user()?->site_id;

        return [
            'code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('roles', 'code')->where(function ($query) use ($siteId) {
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
            'code.required' => 'Le code du rôle est obligatoire.',
            'code.unique' => 'Ce code de rôle existe déjà pour ce site.',
            'nom.required' => 'Le nom du rôle est obligatoire.',
            'statut.in' => 'Le statut doit être ACTIF ou INACTIF.',
        ];
    }
}
