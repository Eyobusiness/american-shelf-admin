<?php

namespace App\Http\Requests\Operation;

use Illuminate\Foundation\Http\FormRequest;

class RetourOperationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'date_retour_effective' => ['nullable', 'date'],
            'etat_exemplaire' => ['nullable', 'string', 'in:NEUF,BON,MOYEN,DEGRADE,PERDU'],
            'observations' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'date_retour_effective.date' => 'La date de retour effective doit être une date valide.',
            'etat_exemplaire.in' => "L'état de l'exemplaire doit être NEUF, BON, MOYEN, DEGRADE ou PERDU.",
            'observations.max' => 'Les observations ne peuvent pas dépasser 2000 caractères.',
        ];
    }
}
