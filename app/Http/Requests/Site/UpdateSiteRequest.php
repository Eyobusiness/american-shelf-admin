<?php

namespace App\Http\Requests\Site;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSiteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $siteId = $this->route('site') ?? $this->route('id');
        if (is_object($siteId)) {
            $siteId = $siteId->id;
        }

        return [
            'code' => ['sometimes', 'required', 'string', 'max:50', Rule::unique('sites', 'code')->ignore($siteId)],
            'nom' => ['sometimes', 'required', 'string', 'max:150'],
            'logo' => $this->hasFile('logo')
                ? ['nullable', 'file', 'mimes:jpeg,png,jpg,webp,svg', 'max:2048']
                : ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'adresse' => ['nullable', 'string', 'max:255'],
            'telephone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:150'],
            'statut' => ['nullable', 'string', 'in:ACTIF,INACTIF'],
            'date_creation' => ['nullable', 'date'],
        ];
    }

    public function messages(): array
    {
        return [
            'code.unique' => 'Ce code de site est déjà utilisé.',
            'email.email' => 'L\'adresse email doit être valide.',
            'statut.in' => 'Le statut doit être ACTIF ou INACTIF.',
        ];
    }
}
