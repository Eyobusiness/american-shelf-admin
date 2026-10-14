<?php

namespace App\Http\Resources;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NotificationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $dateEnvoi = $this->date_envoi ? Carbon::parse($this->date_envoi) : null;
        $dateLecture = $this->date_lecture ? Carbon::parse($this->date_lecture) : null;

        return [
            'id' => $this->id,
            'site_id' => $this->site_id,
            'usager_id' => $this->usager_id,
            'utilisateur_id' => $this->utilisateur_id,
            'destinataire_type' => $this->destinataire_type ?? 'USAGER',
            'type' => $this->type,
            'objet' => $this->objet,
            'message' => $this->message,
            'email_destinataire' => $this->email_destinataire,
            'date_envoi' => $dateEnvoi ? $dateEnvoi->toIso8601String() : null,
            'date_lecture' => $dateLecture ? $dateLecture->toIso8601String() : null,
            'is_lu' => !is_null($this->date_lecture),
            'statut' => $this->statut,
            'entite_type' => $this->entite_type,
            'entite_id' => $this->entite_id,
            'action_url' => $this->action_url,
            'usager' => $this->whenLoaded('usager', function () {
                if (!$this->usager) {
                    return null;
                }
                return [
                    'id' => $this->usager->id,
                    'numero' => $this->usager->numero,
                    'nom' => $this->usager->nom,
                    'prenom' => $this->usager->prenom,
                    'email' => $this->usager->email,
                    'contact' => $this->usager->contact,
                ];
            }),
            'utilisateur' => $this->whenLoaded('utilisateur', function () {
                if (!$this->utilisateur) {
                    return null;
                }
                return [
                    'id' => $this->utilisateur->id,
                    'nom' => $this->utilisateur->nom,
                    'prenom' => $this->utilisateur->prenom,
                    'email' => $this->utilisateur->email,
                ];
            }),
            'created_at' => $this->created_at ? Carbon::parse($this->created_at)->toIso8601String() : null,
            'updated_at' => $this->updated_at ? Carbon::parse($this->updated_at)->toIso8601String() : null,
            'deleted_at' => $this->deleted_at ? Carbon::parse($this->deleted_at)->toIso8601String() : null,
        ];
    }
}