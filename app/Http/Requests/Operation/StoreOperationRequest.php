<?php

namespace App\Http\Requests\Operation;

use Illuminate\Foundation\Http\FormRequest;

class StoreOperationRequest extends FormRequest
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
            'usager_id' => ['required', 'uuid', 'exists:usagers,id'],
            'exemplaire_id' => ['required', 'uuid', 'exists:exemplaires,id'],
            'type' => ['required', 'string', 'in:CONSULTATION,EMPRUNT'],
            'date_operation' => ['nullable', 'date'],
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
            'usager_id.required' => "L'identifiant de l'usager est obligatoire.",
            'usager_id.uuid' => "L'identifiant de l'usager doit être un UUID valide.",
            'usager_id.exists' => "L'usager spécifié n'existe pas.",
            'exemplaire_id.required' => "L'identifiant de l'exemplaire est obligatoire.",
            'exemplaire_id.uuid' => "L'identifiant de l'exemplaire doit être un UUID valide.",
            'exemplaire_id.exists' => "L'exemplaire spécifié n'existe pas.",
            'type.required' => "Le type d'opération est obligatoire.",
            'type.in' => "Le type d'opération doit être soit CONSULTATION soit EMPRUNT.",
            'date_operation.date' => "La date de l'opération doit être une date valide.",
            'observations.max' => 'Les observations ne peuvent pas dépasser 2000 caractères.',
        ];
    }
}
