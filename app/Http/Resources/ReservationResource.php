<?php

namespace App\Http\Resources;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReservationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $dateReservation = $this->date_reservation ? Carbon::parse($this->date_reservation) : null;
        $dateExpiration = $this->date_expiration ? Carbon::parse($this->date_expiration) : null;
        $now = now();

        $estExpiree = $this->statut === 'EXPIREE'
            || ($dateExpiration && $now->isAfter($dateExpiration) && in_array($this->statut, ['EN_ATTENTE', 'DISPONIBLE'], true));

        return [
            'id' => $this->id,
            'site_id' => $this->site_id,
            'usager_id' => $this->usager_id,
            'livre_id' => $this->livre_id,
            'date_reservation' => $dateReservation ? $dateReservation->toIso8601String() : null,
            'date_expiration' => $dateExpiration ? $dateExpiration->toIso8601String() : null,
            'statut' => $this->statut,
            'observations' => $this->observations,
            'est_expiree' => $estExpiree,
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
            'livre' => $this->whenLoaded('livre', function () {
                if (!$this->livre) {
                    return null;
                }
                return [
                    'id' => $this->livre->id,
                    'code' => $this->livre->code,
                    'titre' => $this->livre->titre,
                    'auteur' => $this->livre->auteur,
                    'isbn' => $this->livre->isbn,
                    'categorie' => $this->livre->categorieLivre ? [
                        'id' => $this->livre->categorieLivre->id,
                        'code' => $this->livre->categorieLivre->code,
                        'nom' => $this->livre->categorieLivre->nom,
                    ] : null,
                ];
            }),
            'created_at' => $this->created_at ? Carbon::parse($this->created_at)->toIso8601String() : null,
            'updated_at' => $this->updated_at ? Carbon::parse($this->updated_at)->toIso8601String() : null,
            'deleted_at' => $this->deleted_at ? Carbon::parse($this->deleted_at)->toIso8601String() : null,
        ];
    }
}
