<?php

namespace App\Http\Requests\Site;

use Illuminate\Foundation\Http\FormRequest;

class StoreSiteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:50', 'unique:sites,code'],
            'nom' => ['required', 'string', 'max:150'],
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
            'code.required' => 'Le code du site est obligatoire.',
            'code.unique' => 'Ce code de site est déjà utilisé.',
            'nom.required' => 'Le nom du site est obligatoire.',
            'email.email' => 'L\'adresse email doit être valide.',
            'statut.in' => 'Le statut doit être ACTIF ou INACTIF.',
        ];
    }
}
