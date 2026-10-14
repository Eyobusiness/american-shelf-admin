<?php

namespace App\Http\Resources;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UsagerResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $currentSiteId = $request->attributes->get('current_site_id') ?? $request->user()?->site_id;

        return [
            'id' => $this->id,
            'numero' => $this->numero,
            'nom' => $this->nom,
            'prenom' => $this->prenom,
            'email' => $this->email,
            'contact' => $this->contact,
            'usager_site' => $this->whenLoaded('usagerSites', function () use ($currentSiteId) {
                $membership = $this->usagerSites->first(function ($us) use ($currentSiteId) {
                    return $currentSiteId ? (string) $us->site_id === (string) $currentSiteId : true;
                });
                return $membership ? [
                    'id' => $membership->id,
                    'site_id' => $membership->site_id,
                    'numero' => $membership->numero,
                    'type' => $membership->type,
                    'categorie_usager_id' => $membership->categorie_usager_id,
                    'categorie' => $membership->categorieUsager ? [
                        'id' => $membership->categorieUsager->id,
                        'code' => $membership->categorieUsager->code,
                        'nom' => $membership->categorieUsager->nom,
                    ] : null,
                    'statut' => $membership->statut,
                    'date_inscription' => $membership->date_inscription ? Carbon::parse($membership->date_inscription)->toDateString() : null,
                ] : null;
            }),
            'created_at' => $this->created_at ? Carbon::parse($this->created_at)->toIso8601String() : null,
            'updated_at' => $this->updated_at ? Carbon::parse($this->updated_at)->toIso8601String() : null,
        ];
    }
}
