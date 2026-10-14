<?php

namespace App\Http\Requests\Livre;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLivreRequest extends FormRequest
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
                Rule::unique('livres', 'code')->where(function ($query) use ($siteId) {
                    return $query->where('site_id', $siteId)->whereNull('deleted_at');
                }),
            ],
            'titre' => ['required', 'string', 'max:255'],
            'auteur' => ['required', 'string', 'max:255'],
            'editeur' => ['nullable', 'string', 'max:255'],
            'annee_publication' => ['nullable', 'integer', 'min:1000', 'max:' . (date('Y') + 1)],
            'isbn' => ['nullable', 'string', 'max:50'],
            'categorie_livre_id' => [
                'required',
                'uuid',
                // Règle critique d'étanchéité multi-tenant : la catégorie doit appartenir au même site
                Rule::exists('categories_livres', 'id')->where(function ($query) use ($siteId) {
                    return $query->where('site_id', $siteId)->whereNull('deleted_at');
                }),
            ],
            'visible_en_ligne' => ['nullable', 'boolean'],
            'statut' => ['nullable', 'string', 'in:ACTIF,INACTIF,ARCHIVE'],
            'image_principale' => ['nullable', 'file', 'mimes:jpeg,png,jpg,webp', 'max:5120'], // Max 5 Mo
            'fichier_numerique' => ['nullable', 'file', 'mimes:pdf,epub', 'max:51200'], // Max 50 Mo
        ];
    }

    public function messages(): array
    {
        return [
            'code.required' => 'Le code du livre est obligatoire.',
            'code.unique' => 'Ce code de livre existe déjà pour ce site.',
            'titre.required' => 'Le titre du livre est obligatoire.',
            'auteur.required' => 'L\'auteur du livre est obligatoire.',
            'categorie_livre_id.required' => 'La catégorie du livre est obligatoire.',
            'categorie_livre_id.exists' => 'La catégorie sélectionnée est invalide ou n\'appartient pas à ce site.',
            'annee_publication.integer' => 'L\'année de publication doit être un nombre entier.',
            'annee_publication.min' => 'L\'année de publication est invalide.',
            'annee_publication.max' => 'L\'année de publication ne peut pas être dans le futur lointain.',
            'statut.in' => 'Le statut doit être ACTIF, INACTIF ou ARCHIVE.',
            'image_principale.file' => 'L\'image principale doit être un fichier valide.',
            'image_principale.mimes' => 'L\'image principale doit être au format jpeg, png, jpg ou webp.',
            'image_principale.max' => 'L\'image principale ne peut pas dépasser 5 Mo.',
            'fichier_numerique.file' => 'Le fichier numérique doit être un fichier valide.',
            'fichier_numerique.mimes' => 'Le fichier numérique doit être au format PDF ou EPUB.',
            'fichier_numerique.max' => 'Le fichier numérique ne peut pas dépasser 50 Mo.',
        ];
    }
}
