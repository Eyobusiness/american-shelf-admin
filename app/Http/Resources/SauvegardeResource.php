<?php

namespace App\Http\Resources;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SauvegardeResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $dateSauvegarde = $this->date_sauvegarde ? Carbon::parse($this->date_sauvegarde) : null;

        return [
            'id' => $this->id,
            'site_id' => $this->site_id,
            'nom_fichier' => $this->nom_fichier,
            'chemin_fichier' => $this->chemin_fichier,
            'taille' => (int) $this->taille,
            'date_sauvegarde' => $dateSauvegarde ? $dateSauvegarde->toIso8601String() : null,
            'statut' => $this->statut,
            'created_at' => $this->created_at ? Carbon::parse($this->created_at)->toIso8601String() : null,
            'deleted_at' => $this->deleted_at ? Carbon::parse($this->deleted_at)->toIso8601String() : null,
        ];
    }
}
