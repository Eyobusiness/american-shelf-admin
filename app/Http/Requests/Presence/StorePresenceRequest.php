<?php

namespace App\Http\Requests\Presence;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePresenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $siteId = $this->attributes->get('current_site_id') ?? $this->user()?->site_id;

        return [
            // usager_id est nullable pour permettre les présences anonymes
            'usager_id' => [
                'nullable',
                'uuid',
                Rule::exists('usagers', 'id')->whereNull('deleted_at'),
            ],
            'date_presence' => ['nullable', 'date'],
            'heure_arrivee' => ['required', 'date_format:H:i,H:i:s'],
            'heure_depart' => ['nullable', 'date_format:H:i,H:i:s', 'after_or_equal:heure_arrivee'],
            'motif' => ['nullable', 'string', 'max:255'],
            'observations' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'heure_arrivee.required' => 'L\'heure d\'arrivée est obligatoire.',
            'heure_arrivee.date_format' => 'L\'heure d\'arrivée doit respecter le format HH:MM ou HH:MM:SS.',
            'heure_depart.date_format' => 'L\'heure de départ doit respecter le format HH:MM ou HH:MM:SS.',
            'heure_depart.after_or_equal' => 'L\'heure de départ doit être postérieure ou égale à l\'heure d\'arrivée.',
            'usager_id.exists' => 'L\'usager sélectionné est introuvable.',
            'motif.max' => 'Le motif ne peut pas dépasser 255 caractères.',
        ];
    }
}
