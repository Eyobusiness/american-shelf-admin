<?php

namespace App\Http\Requests\Reservation;

use Illuminate\Foundation\Http\FormRequest;

class StoreReservationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'usager_id' => ['required', 'uuid', 'exists:usagers,id'],
            'livre_id' => ['required', 'uuid', 'exists:livres,id'],
            'date_reservation' => ['nullable', 'date'],
            'observations' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'usager_id.required' => "L'identifiant de l'usager est obligatoire.",
            'usager_id.exists' => "L'usager sélectionné n'existe pas.",
            'livre_id.required' => "L'identifiant du livre est obligatoire.",
            'livre_id.exists' => "Le livre sélectionné n'existe pas.",
            'date_reservation.date' => "La date de réservation doit être une date valide.",
            'observations.max' => 'Les observations ne peuvent pas dépasser 2000 caractères.',
        ];
    }
}
