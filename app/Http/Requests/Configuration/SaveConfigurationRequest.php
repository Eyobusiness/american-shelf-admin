<?php

namespace App\Http\Requests\Configuration;

use Illuminate\Foundation\Http\FormRequest;

class SaveConfigurationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'duree_emprunt' => ['required', 'integer', 'min:1', 'max:365'],
            'nombre_jours_grace' => ['required', 'integer', 'min:0', 'max:60'],
            'duree_consultation' => ['required', 'integer', 'min:1', 'max:24'],
            'quota_emprunt' => ['required', 'integer', 'min:1', 'max:50'],
            'duree_reservation' => ['required', 'integer', 'min:1', 'max:30'],
        ];
    }

    public function messages(): array
    {
        return [
            'duree_emprunt.min' => 'La durée d\'emprunt doit être d\'au moins 1 jour.',
            'nombre_jours_grace.min' => 'Le nombre de jours de grâce ne peut pas être négatif.',
            'duree_consultation.min' => 'La durée de consultation sur place doit être d\'au moins 1 heure.',
            'quota_emprunt.min' => 'Le quota d\'emprunt doit être d\'au moins 1 livre.',
            'duree_reservation.min' => 'La durée de réservation doit être d\'au moins 1 jour.',
        ];
    }
}
