<?php

namespace App\Http\Requests\Notification;

use Illuminate\Foundation\Http\FormRequest;

class StoreNotificationRequest extends FormRequest
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
            'usager_id' => ['nullable', 'uuid', 'exists:usagers,id'],
            'type' => ['nullable', 'string', 'max:50'],
            'objet' => ['required', 'string', 'max:255'],
            'message' => ['required', 'string'],
            'email_destinataire' => ['nullable', 'email', 'max:255'],
        ];
    }

    /**
     * Custom messages for validation errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'usager_id.exists' => 'L\'usager sélectionné est introuvable.',
            'objet.required' => 'L\'objet de la notification est obligatoire.',
            'objet.max' => 'L\'objet ne peut pas dépasser 255 caractères.',
            'message.required' => 'Le contenu du message est obligatoire.',
            'email_destinataire.email' => 'L\'adresse email du destinataire n\'est pas valide.',
        ];
    }
}
