<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class ReportFilterRequest extends FormRequest
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
            'date_debut' => ['nullable', 'date_format:Y-m-d'],
            'date_fin' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_debut'],
            'mois' => ['nullable', 'integer', 'between:1,12'],
            'annee' => ['nullable', 'integer', 'between:2000,2100'],
            'categorie_livre_id' => ['nullable', 'uuid', 'exists:categories_livres,id'],
            'livre_id' => ['nullable', 'uuid', 'exists:livres,id'],
            'usager_id' => ['nullable', 'uuid', 'exists:usagers,id'],
            'etat' => ['nullable', 'string', 'in:NEUF,BON,MOYEN,DEGRADE,PERDU'],
            'statut' => ['nullable', 'string'],
            'type' => ['nullable', 'string'],
            'limit' => ['nullable', 'integer', 'between:1,100'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'between:1,100'],
            'format' => ['nullable', 'string', 'in:pdf,xlsx,csv'],
            'search' => ['nullable', 'string', 'max:150'],
        ];
    }

    /**
     * Messages de validation personnalisés.
     */
    public function messages(): array
    {
        return [
            'date_fin.after_or_equal' => 'La date de fin doit être postérieure ou égale à la date de début.',
            'mois.between' => 'Le mois doit être compris entre 1 et 12.',
            'annee.between' => 'L\'année doit être comprise entre 2000 et 2100.',
            'categorie_livre_id.exists' => 'La catégorie de livre sélectionnée est introuvable.',
            'livre_id.exists' => 'Le livre sélectionné est introuvable.',
            'usager_id.exists' => 'L\'usager sélectionné est introuvable.',
            'etat.in' => 'L\'état spécifié n\'est pas valide (valeurs acceptées : NEUF, BON, MOYEN, DEGRADE, PERDU).',
            'format.in' => 'Le format d\'export demandé n\'est pas supporté (formats valides : pdf, xlsx, csv).',
        ];
    }

    /**
     * Formatage uniforme des erreurs de validation JSON.
     */
    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => 'Les paramètres du filtre de rapport sont invalides.',
            'errors' => $validator->errors(),
        ], 422));
    }
}
