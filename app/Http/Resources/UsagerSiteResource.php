<?php

namespace App\Http\Resources;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UsagerSiteResource extends JsonResource
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
            'numero' => $this->numero,
            'date_inscription' => $this->date_inscription ? Carbon::parse($this->date_inscription)->toDateString() : null,
            'type' => $this->type,
            'categorie_usager_id' => $this->categorie_usager_id,
            'statut' => $this->statut,
            'usager' => $this->whenLoaded('usager', function () {
                return [
                    'id' => $this->usager->id,
                    'numero' => $this->usager->numero,
                    'nom' => $this->usager->nom,
                    'prenom' => $this->usager->prenom,
                    'email' => $this->usager->email,
                    'contact' => $this->usager->contact,
                ];
            }),
            'categorie_usager' => $this->whenLoaded('categorieUsager', function () {
                return [
                    'id' => $this->categorieUsager->id,
                    'code' => $this->categorieUsager->code,
                    'nom' => $this->categorieUsager->nom,
                ];
            }),
            'created_at' => $this->created_at ? Carbon::parse($this->created_at)->toIso8601String() : null,
            'updated_at' => $this->updated_at ? Carbon::parse($this->updated_at)->toIso8601String() : null,
            'deleted_at' => $this->deleted_at ? Carbon::parse($this->deleted_at)->toIso8601String() : null,
        ];
    }
}
