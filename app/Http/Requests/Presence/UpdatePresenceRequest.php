<?php

namespace App\Http\Requests\Presence;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePresenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'usager_id' => [
                'nullable',
                'uuid',
                Rule::exists('usagers', 'id')->whereNull('deleted_at'),
            ],
            'date_presence' => ['sometimes', 'date'],
            'heure_arrivee' => ['sometimes', 'date_format:H:i,H:i:s'],
            'heure_depart' => ['nullable', 'date_format:H:i,H:i:s'],
            'motif' => ['nullable', 'string', 'max:255'],
            'observations' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'heure_arrivee.date_format' => 'L\'heure d\'arrivée doit respecter le format HH:MM ou HH:MM:SS.',
            'heure_depart.date_format' => 'L\'heure de départ doit respecter le format HH:MM ou HH:MM:SS.',
            'usager_id.exists' => 'L\'usager sélectionné est introuvable.',
            'motif.max' => 'Le motif ne peut pas dépasser 255 caractères.',
        ];
    }
}
