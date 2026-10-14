<?php

namespace App\Http\Resources;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PresenceResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'site_id' => $this->site_id,
            'usager_id' => $this->usager_id,
            'est_anonyme' => empty($this->usager_id),
            'date_presence' => $this->date_presence ? Carbon::parse($this->date_presence)->toDateString() : null,
            'heure_arrivee' => $this->heure_arrivee,
            'heure_depart' => $this->heure_depart,
            'statut' => $this->statut,
            'est_en_cours' => empty($this->heure_depart),
            'duree_minutes' => $this->duree_minutes,
            'duree_formatee' => $this->duree_formatee,
            'motif' => $this->motif,
            'observations' => $this->observations,
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
            'created_at' => $this->created_at ? Carbon::parse($this->created_at)->toIso8601String() : null,
            'updated_at' => $this->updated_at ? Carbon::parse($this->updated_at)->toIso8601String() : null,
            'deleted_at' => $this->deleted_at ? Carbon::parse($this->deleted_at)->toIso8601String() : null,
        ];
    }
}
