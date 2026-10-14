<?php

namespace App\Http\Requests\Site;

use Illuminate\Foundation\Http\FormRequest;

class UploadSiteLogoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'logo' => ['required', 'file', 'mimes:jpeg,png,jpg,webp,svg', 'max:2048'],
        ];
    }

    public function messages(): array
    {
        return [
            'logo.required' => 'Le fichier image du logo est obligatoire.',
            'logo.file' => 'Le logo doit être un fichier valide.',
            'logo.mimes' => 'Le format de l\'image doit être jpeg, png, jpg, webp ou svg.',
            'logo.max' => 'La taille maximale de l\'image est de 2 Mo.',
        ];
    }
}
