<?php

namespace App\Http\Requests\SuperAdmin;

use Illuminate\Foundation\Http\FormRequest;

class StoreSiteWithAdminRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Informations du site
            'code' => ['required', 'string', 'max:50', 'unique:sites,code'],
            'nom' => ['required', 'string', 'max:150'],
            'logo' => $this->hasFile('logo')
                ? ['nullable', 'file', 'mimes:jpeg,png,jpg,webp,svg', 'max:2048']
                : ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'adresse' => ['nullable', 'string', 'max:255'],
            'telephone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:150'],
            'statut' => ['nullable', 'string', 'in:ACTIF,INACTIF,SUSPENDU'],
            'date_creation' => ['nullable', 'date'],

            // Premier administrateur obligatoire
            'admin' => ['required', 'array'],
            'admin.nom' => ['required', 'string', 'max:100'],
            'admin.prenom' => ['required', 'string', 'max:100'],
            'admin.email' => ['required', 'email', 'max:150', 'unique:utilisateurs,email'],
            'admin.mot_de_passe' => ['required', 'string', 'min:8', 'max:100'],
            'admin.telephone' => ['nullable', 'string', 'max:50'],

            // Configuration facultative
            'configuration' => ['nullable', 'array'],
            'configuration.duree_emprunt' => ['nullable', 'integer', 'min:1', 'max:365'],
            'configuration.nombre_jours_grace' => ['nullable', 'integer', 'min:0', 'max:60'],
            'configuration.duree_consultation' => ['nullable', 'integer', 'min:1', 'max:24'],
            'configuration.quota_emprunt' => ['nullable', 'integer', 'min:1', 'max:50'],
            'configuration.duree_reservation' => ['nullable', 'integer', 'min:1', 'max:30'],
        ];
    }

    public function messages(): array
    {
        return [
            'code.required' => 'Le code du site est obligatoire.',
            'code.unique' => 'Ce code de site est déjà utilisé.',
            'nom.required' => 'Le nom du site est obligatoire.',
            'statut.in' => 'Le statut du site doit être ACTIF, INACTIF ou SUSPENDU.',
            'admin.required' => 'Les informations du premier administrateur sont obligatoires.',
            'admin.nom.required' => 'Le nom de l\'administrateur est obligatoire.',
            'admin.prenom.required' => 'Le prénom de l\'administrateur est obligatoire.',
            'admin.email.required' => 'L\'adresse email de l\'administrateur est obligatoire.',
            'admin.email.email' => 'L\'adresse email de l\'administrateur doit être valide.',
            'admin.email.unique' => 'Cette adresse email administrateur est déjà utilisée.',
            'admin.mot_de_passe.required' => 'Le mot de passe de l\'administrateur est obligatoire.',
            'admin.mot_de_passe.min' => 'Le mot de passe de l\'administrateur doit comporter au moins 8 caractères.',
        ];
    }
}
