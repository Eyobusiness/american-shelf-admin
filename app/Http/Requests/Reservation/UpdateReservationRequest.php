<?php

namespace App\Http\Requests\Reservation;

use Illuminate\Foundation\Http\FormRequest;

class UpdateReservationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'date_expiration' => ['nullable', 'date'],
            'statut' => ['nullable', 'string', 'in:EN_ATTENTE,DISPONIBLE,HONOREE,EXPIREE,ANNULEE'],
            'observations' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'date_expiration.date' => "La date d'expiration doit être une date valide.",
            'statut.in' => "Le statut doit être EN_ATTENTE, DISPONIBLE, HONOREE, EXPIREE ou ANNULEE.",
            'observations.max' => 'Les observations ne peuvent pas dépasser 2000 caractères.',
        ];
    }
}
