<?php

namespace App\Http\Requests\Acquisition;

use Illuminate\Foundation\Http\FormRequest;

class StoreAcquisitionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', 'string', 'in:ACHAT,DON'], // ex: ACHAT, DON, etc.
            'date_acquisition' => ['required', 'date'],
            'provenance' => ['nullable', 'string', 'max:255'],
            'quantite' => ['required', 'integer', 'min:1'],
            'cout' => ['nullable', 'numeric', 'min:0'],
            'reference_facture' => ['nullable', 'string', 'max:100'],
            'donateur' => ['nullable', 'string', 'max:255'],
            'nature_don' => ['nullable', 'string', 'max:255'],
            'destination' => ['nullable', 'string', 'max:255'],
            'etat' => ['nullable', 'string', 'max:50'],
            'observations' => ['nullable', 'string'],
            'piece_jointe' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,doc,docx', 'max:10240'], // Max 10MB
        ];
    }

    public function messages(): array
    {
        return [
            'type.required' => 'Le type d\'acquisition est obligatoire (ex: ACHAT, DON).',
            'type.in' => 'Le type d\'acquisition doit Ãªtre obligatoirement ACHAT ou DON.',
            'date_acquisition.required' => 'La date d\'acquisition est obligatoire.',
            'date_acquisition.date' => 'La date d\'acquisition doit Ãªtre une date valide.',
            'quantite.required' => 'La quantitÃ© est obligatoire.',
            'quantite.integer' => 'La quantitÃ© doit Ãªtre un nombre entier.',
            'quantite.min' => 'La quantitÃ© doit Ãªtre d\'au moins 1.',
            'cout.numeric' => 'Le coÃ»t doit Ãªtre une valeur numÃ©rique.',
            'cout.min' => 'Le coÃ»t ne peut pas Ãªtre nÃ©gatif.',
            'piece_jointe.file' => 'La piÃ¨ce jointe doit Ãªtre un fichier valide.',
            'piece_jointe.mimes' => 'La piÃ¨ce jointe doit Ãªtre un document de type : pdf, jpg, jpeg, png, doc, docx.',
            'piece_jointe.max' => 'La piÃ¨ce jointe ne peut pas dÃ©passer 10 Mo.',
        ];
    }
}

