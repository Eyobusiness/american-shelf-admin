<?php

namespace App\Http\Requests\Role;

use Illuminate\Foundation\Http\FormRequest;

class SyncRolePermissionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'permission_ids' => ['present', 'array'],
            'permission_ids.*' => ['uuid', 'distinct'],
        ];
    }

    public function messages(): array
    {
        return [
            'permission_ids.present' => 'Le champ permission_ids est obligatoire.',
            'permission_ids.array' => 'Le champ permission_ids doit être un tableau d\'identifiants.',
            'permission_ids.*.uuid' => 'Chaque permission doit être un identifiant UUID valide.',
        ];
    }
}
